<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\Contracts\PublisherInterface;
use M7md5ttab\LaravelShared\DTOs\DeploymentManifest;
use M7md5ttab\LaravelShared\DTOs\DeploymentSnapshot;
use M7md5ttab\LaravelShared\DTOs\PublishContext;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Exceptions\HostingException;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Support\DeploymentNameGenerator;
use M7md5ttab\LaravelShared\Support\PathHelper;
use M7md5ttab\LaravelShared\Support\ProjectEnvironmentResolver;
use M7md5ttab\LaravelShared\Support\PublicHtmlLocator;
use M7md5ttab\LaravelShared\Support\SymlinkSupportDetector;

class DeploymentManager
{
    /**
     * @param  array<string, class-string<PublisherInterface>>  $publishers
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $publishers,
        private readonly ProjectEnvironmentResolver $environmentResolver,
        private readonly PublicHtmlLocator $publicHtmlLocator,
        private readonly SymlinkSupportDetector $symlinkSupport,
        private readonly DeploymentNameGenerator $nameGenerator,
        private readonly ManifestRepositoryInterface $manifests,
        private readonly GitService $git,
    ) {
    }

    public function publish(
        ?DeploymentMode $requestedMode = null,
        ?string $target = null,
        bool $dryRun = false,
        bool $createTag = false,
        ?string $tagName = null,
    ): PublishResult {
        $environment = $this->environmentResolver->resolve();
        $targetPath = $this->resolveTargetPath($environment, $target);
        $this->assertTargetPathCanBePrepared($targetPath);

        $symlinkProbe = $this->symlinkSupport->detect($environment);
        $autoMode = $requestedMode === null;
        $mode = $requestedMode ?? ($symlinkProbe->supported ? DeploymentMode::Symlink : DeploymentMode::Copy);

        $snapshotName = $this->nameGenerator->generate($environment->basePath);
        $snapshot = $this->prepareSnapshot($environment->basePath, $snapshotName, $dryRun, $createTag, $tagName);
        $backupPath = $this->backupPath($snapshotName);
        $relativeBasePath = PathHelper::relativePath($targetPath, $environment->basePath);

        if (! $dryRun) {
            $this->backupTargetDirectory($targetPath, $backupPath);
        }

        $context = new PublishContext(
            environment: $environment,
            mode: $mode,
            targetPath: $targetPath,
            backupPath: $backupPath,
            relativeBasePath: $relativeBasePath,
            snapshot: $snapshot,
            dryRun: $dryRun,
        );

        try {
            $result = $this->resolvePublisher($mode)->publish($context);
        } catch (\Throwable $throwable) {
            if (! $autoMode || $mode !== DeploymentMode::Symlink) {
                throw $throwable;
            }

            $result = $this->publishWithCopyFallback($context, 'Symlink mode failed: ' . $throwable->getMessage());
        }

        if ($result->status === ActionStatus::Failure && $autoMode && $mode === DeploymentMode::Symlink) {
            $result = $this->publishWithCopyFallback($context, $result->message);
        }

        if ($dryRun) {
            return new PublishResult(
                status: $result->status,
                mode: $result->mode,
                targetPath: $result->targetPath,
                message: 'Dry run completed. No files were changed.',
                messages: $this->mergeSnapshotNotes($result->messages, $snapshot, true),
                snapshot: $snapshot,
                storageLinked: $result->storageLinked,
                dryRun: true,
            );
        }

        $manifest = new DeploymentManifest(
            snapshotName: $snapshot->name,
            tagName: $snapshot->tagName,
            mode: $result->mode,
            targetPath: $targetPath,
            backupPath: $backupPath,
            deployedAt: CarbonImmutable::now()->toIso8601String(),
            publicSourcePath: $environment->publicPath,
            relativeBasePath: $relativeBasePath,
            storageLinked: $result->storageLinked,
            gitCommitHash: $snapshot->commitHash,
            previousGitCommitHash: $snapshot->previousCommitHash,
        );

        $manifestPath = $this->manifests->save($manifest);
        $messages = $this->mergeSnapshotNotes($result->messages, $snapshot, false);
        $messages[] = "Saved deployment manifest to [{$manifestPath}].";
        $this->writeLog($snapshot->name, $messages);

        return new PublishResult(
            status: $result->status,
            mode: $result->mode,
            targetPath: $result->targetPath,
            message: $result->message,
            messages: $messages,
            snapshot: $snapshot,
            manifestPath: $manifestPath,
            storageLinked: $result->storageLinked,
            dryRun: false,
        );
    }

    private function prepareSnapshot(
        string $basePath,
        string $snapshotName,
        bool $dryRun,
        bool $createTag,
        ?string $tagName,
    ): DeploymentSnapshot {
        if ($dryRun || ! $this->git->isInstalled() || ! $this->git->isRepository($basePath)) {
            return new DeploymentSnapshot(
                name: $snapshotName,
                tagName: $createTag ? ($tagName ?? $snapshotName) : null,
                gitCommitted: false,
                createdAt: CarbonImmutable::now()->toIso8601String(),
            );
        }

        $previousCommitHash = $this->git->headCommitHash($basePath);
        $message = "chore: hosting deployment snapshot [{$snapshotName}]";
        $this->git->createSnapshotCommit($basePath, $message);
        $commitHash = $this->git->headCommitHash($basePath);

        $resolvedTag = null;

        if ($createTag || $tagName !== null) {
            $resolvedTag = $tagName ?? $snapshotName;
            $this->git->createAnnotatedTag($basePath, $resolvedTag, $message);
        }

        return new DeploymentSnapshot(
            name: $snapshotName,
            tagName: $resolvedTag,
            gitCommitted: true,
            createdAt: CarbonImmutable::now()->toIso8601String(),
            commitHash: $commitHash,
            previousCommitHash: $previousCommitHash,
        );
    }

    private function backupTargetDirectory(string $targetPath, string $backupPath): void
    {
        $files = $this->files();
        $files->ensureDirectoryExists($backupPath);

        if (! $files->isDirectory($targetPath)) {
            return;
        }

        $files->copyDirectory($targetPath, $backupPath);
    }

    private function backupPath(string $snapshotName): string
    {
        return PathHelper::join(
            $this->app()->storagePath(),
            (string) $this->container['config']->get('hosting-shared.deployment.backup_directory', 'app/hosting-shared/backups'),
            $snapshotName,
        );
    }

    private function resolveTargetPath(\M7md5ttab\LaravelShared\DTOs\EnvironmentContext $environment, ?string $target): string
    {
        if ($target !== null) {
            return $this->publicHtmlLocator->resolvePath($environment->basePath, $target);
        }

        if ($environment->publicHtmlPath !== null) {
            return $environment->publicHtmlPath;
        }

        return $this->publicHtmlLocator->defaultPath($environment->basePath);
    }

    private function assertTargetPathCanBePrepared(string $targetPath): void
    {
        $files = $this->files();

        if ($files->isDirectory($targetPath) || is_link($targetPath)) {
            return;
        }

        if ($files->isFile($targetPath)) {
            throw new HostingException(
                "The target path [{$targetPath}] already exists as a file. Choose a directory path instead.",
            );
        }

        $parent = dirname($targetPath);

        if (! $files->isDirectory($parent)) {
            throw new HostingException(
                "The parent directory [{$parent}] does not exist. Create it first or choose a different --target path.",
            );
        }
    }

    /**
     * @param  array<int, string>  $messages
     * @return array<int, string>
     */
    private function mergeSnapshotNotes(array $messages, DeploymentSnapshot $snapshot, bool $dryRun): array
    {
        if ($dryRun) {
            $messages[] = 'Dry run skipped git snapshot commits, backups, and manifest writes.';

            return $messages;
        }

        if ($snapshot->gitCommitted) {
            $messages[] = "Created git snapshot commit [{$snapshot->name}].";
        } else {
            $messages[] = 'Git snapshot commit was skipped because git is unavailable or the app is not a repository.';
        }

        if ($snapshot->tagName !== null) {
            $messages[] = "Created deployment tag [{$snapshot->tagName}].";
        }

        return $messages;
    }

    private function writeLog(string $snapshotName, array $messages): void
    {
        $files = $this->files();
        $logFile = PathHelper::join(
            $this->app()->storagePath(),
            (string) $this->container['config']->get('hosting-shared.deployment.log_file', 'logs/hosting-shared.log'),
        );

        $files->ensureDirectoryExists(dirname($logFile));
        $files->append($logFile, '[' . CarbonImmutable::now()->toDateTimeString() . "] {$snapshotName}" . PHP_EOL);

        foreach ($messages as $message) {
            $files->append($logFile, ' - ' . $message . PHP_EOL);
        }
    }

    private function publishWithCopyFallback(PublishContext $context, string $reason): PublishResult
    {
        $fallbackContext = new PublishContext(
            environment: $context->environment,
            mode: DeploymentMode::Copy,
            targetPath: $context->targetPath,
            backupPath: $context->backupPath,
            relativeBasePath: $context->relativeBasePath,
            snapshot: $context->snapshot,
            dryRun: $context->dryRun,
        );

        $fallbackResult = $this->resolvePublisher(DeploymentMode::Copy)->publish($fallbackContext);
        $messages = [
            'Symlink mode was not completed automatically. Falling back to copy mode.',
            'Reason: ' . $reason,
            ...$fallbackResult->messages,
        ];

        return new PublishResult(
            status: $fallbackResult->status,
            mode: $fallbackResult->mode,
            targetPath: $fallbackResult->targetPath,
            message: $fallbackResult->message,
            messages: $messages,
            snapshot: $fallbackResult->snapshot,
            manifestPath: $fallbackResult->manifestPath,
            storageLinked: $fallbackResult->storageLinked,
            dryRun: $fallbackResult->dryRun,
        );
    }

    private function resolvePublisher(DeploymentMode $mode): PublisherInterface
    {
        /** @var class-string<PublisherInterface>|null $class */
        $class = $this->publishers[$mode->value] ?? null;

        if ($class === null) {
            throw new \RuntimeException("No publisher is registered for deployment mode [{$mode->value}].");
        }

        return $this->container->make($class);
    }

    private function files(): Filesystem
    {
        return $this->container->make(Filesystem::class);
    }

    private function app(): Application
    {
        return $this->container->make(Application::class);
    }
}
