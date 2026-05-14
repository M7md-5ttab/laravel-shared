<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Services;

use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\DTOs\RunResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Exceptions\HostingException;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Support\PublicHtmlLocator;

class RunManager
{
    public function __construct(
        private readonly CheckPipeline $checkPipeline,
        private readonly FixManager $fixManager,
        private readonly DeploymentManager $deploymentManager,
        private readonly PublicHtmlLocator $publicHtmlLocator,
        private readonly Filesystem $files,
        private readonly GitService $git,
    ) {
    }

    public function prepare(
        ?DeploymentMode $requestedMode = null,
        ?string $target = null,
        bool $dryRun = false,
        bool $createTag = false,
        ?string $tagName = null,
    ): RunResult {
        $initialReport = $this->checkPipeline->run();
        $automaticFixes = $dryRun
            ? $this->fixManager->previewAutomaticFixes($initialReport)
            : $this->fixManager->applyAutomaticFixes($initialReport);
        $targetPreparation = $this->prepareTargetDirectory($initialReport->environment, $target, $dryRun);

        if ($targetPreparation !== null) {
            $automaticFixes[] = $targetPreparation;
        }

        $finalReport = $dryRun ? $initialReport : $this->fixManager->rerunChecks();

        if ($target !== null || $targetPreparation !== null) {
            $finalReport = $this->markTargetAsResolved(
                $finalReport,
                $this->resolveTargetPath($finalReport->environment, $target),
            );
        }

        $advisoryItems = [];
        $repositoryWillExist = $this->repositoryWillExistAfterPreparation($initialReport, $finalReport, $dryRun);

        try {
            $gitSnapshotStatus = $this->gitSnapshotStatus($finalReport->environment, $repositoryWillExist, $dryRun);
            $resolvedTagStatus = $this->tagStatus($createTag, $tagName);

            $this->assertTagPrerequisites($createTag, $tagName, $repositoryWillExist);

            if ($finalReport->hasBlockingFailures()) {
                return new RunResult(
                    status: ActionStatus::Failure,
                    message: 'Deployment is blocked until the remaining blocking issues are resolved.',
                    initialReport: $initialReport,
                    finalReport: $finalReport,
                    automaticFixes: $automaticFixes,
                    advisoryItems: $advisoryItems,
                    dryRun: $dryRun,
                    gitSnapshotStatus: $gitSnapshotStatus,
                    tagStatus: $resolvedTagStatus,
                );
            }

            $targetPath = $this->resolveTargetPath($finalReport->environment, $target);
            $mode = $this->resolveMode($finalReport, $requestedMode);

            if ($dryRun) {
                $preview = $this->deploymentManager->publish(
                    requestedMode: $requestedMode,
                    target: $target,
                    dryRun: true,
                    createTag: $createTag || $tagName !== null,
                    tagName: $tagName,
                );

                return $this->resultWithDeployment(
                    message: 'Dry run completed. No files were changed.',
                    initialReport: $initialReport,
                    finalReport: $finalReport,
                    automaticFixes: $automaticFixes,
                    advisoryItems: $advisoryItems,
                    deployment: $preview,
                    targetPath: $targetPath,
                    mode: $mode,
                    dryRun: true,
                    gitSnapshotStatus: $gitSnapshotStatus,
                    tagStatus: $resolvedTagStatus,
                );
            }

            return new RunResult(
                status: ActionStatus::Success,
                message: 'The project is ready for deployment.',
                initialReport: $initialReport,
                finalReport: $finalReport,
                automaticFixes: $automaticFixes,
                advisoryItems: $advisoryItems,
                targetPath: $targetPath,
                mode: $mode,
                dryRun: false,
                gitSnapshotStatus: $gitSnapshotStatus,
                tagStatus: $resolvedTagStatus,
            );
        } catch (\Throwable $throwable) {
            return new RunResult(
                status: ActionStatus::Failure,
                message: $throwable->getMessage(),
                initialReport: $initialReport,
                finalReport: $finalReport,
                automaticFixes: $automaticFixes,
                advisoryItems: $advisoryItems,
                dryRun: $dryRun,
            );
        }
    }

    public function deploy(
        RunResult $prepared,
        ?DeploymentMode $requestedMode = null,
        ?string $target = null,
        bool $createTag = false,
        ?string $tagName = null,
    ): RunResult {
        $deployment = $this->deploymentManager->publish(
            requestedMode: $requestedMode,
            target: $target,
            dryRun: false,
            createTag: $createTag || $tagName !== null,
            tagName: $tagName,
        );

        return $this->resultWithDeployment(
            message: $deployment->status === ActionStatus::Warning
                ? 'Deployment completed with warnings.'
                : $deployment->message,
            initialReport: $prepared->initialReport,
            finalReport: $prepared->finalReport,
            automaticFixes: $prepared->automaticFixes,
            advisoryItems: $prepared->advisoryItems,
            deployment: $deployment,
            targetPath: $prepared->targetPath,
            mode: $prepared->mode,
            dryRun: false,
            gitSnapshotStatus: $prepared->gitSnapshotStatus,
            tagStatus: $prepared->tagStatus,
        );
    }

    private function resolveTargetPath(EnvironmentContext $environment, ?string $target): string
    {
        $targetPath = $target !== null
            ? $this->publicHtmlLocator->resolvePath($environment->basePath, $target)
            : ($environment->publicHtmlPath ?? $this->publicHtmlLocator->defaultPath($environment->basePath));

        $this->assertTargetPathCanBeCreated($targetPath);

        return $targetPath;
    }

    private function assertTargetPathCanBeCreated(string $targetPath): void
    {
        if ($this->files->isDirectory($targetPath) || is_link($targetPath)) {
            return;
        }

        if ($this->files->isFile($targetPath)) {
            throw new HostingException(
                "The target path [{$targetPath}] already exists as a file. Choose a directory path instead.",
            );
        }

        $parent = dirname($targetPath);

        if (! $this->files->isDirectory($parent)) {
            throw new HostingException(
                "The parent directory [{$parent}] does not exist. Create it first or choose a different --target path.",
            );
        }
    }

    private function prepareTargetDirectory(EnvironmentContext $environment, ?string $target, bool $dryRun): ?FixResult
    {
        $targetPath = $target !== null
            ? $this->publicHtmlLocator->resolvePath($environment->basePath, $target)
            : ($environment->publicHtmlPath ?? $this->publicHtmlLocator->defaultPath($environment->basePath));

        if ($this->files->isDirectory($targetPath) || is_link($targetPath)) {
            return null;
        }

        if ($this->files->isFile($targetPath)) {
            return null;
        }

        $parent = dirname($targetPath);

        if (! $this->files->isDirectory($parent)) {
            return null;
        }

        if ($dryRun) {
            return new FixResult(
                name: 'public_html directory',
                status: ActionStatus::Skipped,
                message: "[dry-run] Would create the deployment target directory at [{$targetPath}].",
            );
        }

        $this->files->ensureDirectoryExists($targetPath);

        return new FixResult(
            name: 'public_html directory',
            status: ActionStatus::Success,
            message: "Created the deployment target directory at [{$targetPath}].",
            performed: true,
        );
    }

    private function markTargetAsResolved(ReadinessReport $report, string $targetPath): ReadinessReport
    {
        $environment = new EnvironmentContext(
            basePath: $report->environment->basePath,
            publicPath: $report->environment->publicPath,
            storagePath: $report->environment->storagePath,
            bootstrapCachePath: $report->environment->bootstrapCachePath,
            phpVersion: $report->environment->phpVersion,
            laravelVersion: $report->environment->laravelVersion,
            operatingSystem: $report->environment->operatingSystem,
            hostingProvider: $report->environment->hostingProvider,
            publicHtmlPath: $targetPath,
        );

        $results = [];
        $replaced = false;

        foreach ($report->results as $result) {
            if ($result->key !== 'public-html') {
                $results[] = $result;

                continue;
            }

            $results[] = new CheckResult(
                key: 'public-html',
                name: 'public_html directory',
                status: CheckStatus::Passed,
                message: "Detected shared hosting target directory at [{$targetPath}].",
                blocking: false,
            );
            $replaced = true;
        }

        if (! $replaced) {
            $results[] = new CheckResult(
                key: 'public-html',
                name: 'public_html directory',
                status: CheckStatus::Passed,
                message: "Detected shared hosting target directory at [{$targetPath}].",
                blocking: false,
            );
        }

        return new ReadinessReport($environment, $results);
    }

    private function resolveMode(ReadinessReport $report, ?DeploymentMode $requestedMode): DeploymentMode
    {
        if ($requestedMode !== null) {
            return $requestedMode;
        }

        $symlinkCheck = $this->resultByKey($report, 'symlink-support');

        return $symlinkCheck?->status === CheckStatus::Passed
            ? DeploymentMode::Symlink
            : DeploymentMode::Copy;
    }

    private function gitSnapshotStatus(EnvironmentContext $environment, bool $repositoryWillExist, bool $dryRun): string
    {
        if (! $this->git->isInstalled()) {
            return 'Git snapshot commit will be skipped because Git is unavailable.';
        }

        if (! $repositoryWillExist) {
            return 'Git snapshot commit will be skipped because this project is not a git repository.';
        }

        if ($dryRun) {
            return $this->git->isRepository($environment->basePath)
                ? 'A deployment snapshot commit would be created.'
                : 'A deployment snapshot commit would be created after automatic fixes.';
        }

        return 'A deployment snapshot commit will be created.';
    }

    private function tagStatus(bool $createTag, ?string $tagName): ?string
    {
        if (! $createTag && $tagName === null) {
            return null;
        }

        if ($tagName !== null) {
            return "Deployment tag [{$tagName}] will be created.";
        }

        return 'A deployment tag will be created from the generated snapshot name.';
    }

    private function assertTagPrerequisites(bool $createTag, ?string $tagName, bool $repositoryWillExist): void
    {
        if (! $createTag && $tagName === null) {
            return;
        }

        if (! $this->git->isInstalled()) {
            throw new HostingException(
                'Git is required when --tag or --tag-name is used. Install Git or rerun without deployment tags.',
            );
        }

        if (! $repositoryWillExist) {
            throw new HostingException(
                'A git repository is required when --tag or --tag-name is used. Initialize git first or rerun without deployment tags.',
            );
        }
    }

    private function repositoryWillExistAfterPreparation(
        ReadinessReport $initialReport,
        ReadinessReport $finalReport,
        bool $dryRun,
    ): bool {
        if (! $this->git->isInstalled()) {
            return false;
        }

        if ($this->git->isRepository($finalReport->environment->basePath)) {
            return true;
        }

        if (! $dryRun) {
            return false;
        }

        $repositoryCheck = $this->resultByKey($initialReport, 'git-repository');

        return $repositoryCheck !== null && $repositoryCheck->status !== CheckStatus::Passed;
    }

    private function resultByKey(ReadinessReport $report, string $key): ?CheckResult
    {
        foreach ($report->results as $result) {
            if ($result->key === $key) {
                return $result;
            }
        }

        return null;
    }

    private function resultWithDeployment(
        string $message,
        ReadinessReport $initialReport,
        ReadinessReport $finalReport,
        array $automaticFixes,
        array $advisoryItems,
        PublishResult $deployment,
        ?string $targetPath,
        ?DeploymentMode $mode,
        bool $dryRun,
        string $gitSnapshotStatus,
        ?string $tagStatus,
    ): RunResult {
        return new RunResult(
            status: $deployment->status,
            message: $message,
            initialReport: $initialReport,
            finalReport: $finalReport,
            automaticFixes: $automaticFixes,
            advisoryItems: $advisoryItems,
            deployment: $deployment,
            targetPath: $targetPath,
            mode: $mode,
            dryRun: $dryRun,
            gitSnapshotStatus: $gitSnapshotStatus,
            tagStatus: $tagStatus,
        );
    }
}
