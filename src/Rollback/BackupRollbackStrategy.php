<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Rollback;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\Contracts\RollbackStrategyInterface;
use M7md5ttab\LaravelShared\DTOs\DeploymentManifest;
use M7md5ttab\LaravelShared\DTOs\RollbackContext;
use M7md5ttab\LaravelShared\DTOs\RollbackResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Support\PathHelper;

final class BackupRollbackStrategy implements RollbackStrategyInterface
{
    private const START_MARKER = '# laravel-shared:start';

    private const END_MARKER = '# laravel-shared:end';

    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
        private readonly ManifestRepositoryInterface $manifests,
        private readonly GitService $git,
    ) {
    }

    public function name(): string
    {
        return 'cleanup';
    }

    public function supports(RollbackContext $context): bool
    {
        return $context->manifest !== null;
    }

    public function rollback(RollbackContext $context): RollbackResult
    {
        $manifest = $context->manifest;

        if ($manifest === null) {
            return new RollbackResult(
                status: ActionStatus::Failure,
                strategy: $this->name(),
                message: 'No deployment manifest was available for backup rollback.',
            );
        }

        $preflightFailure = $this->preflightGitCleanup($context, $manifest);

        if ($preflightFailure !== null) {
            return $preflightFailure;
        }

        $messages = [];
        $warnings = [];

        $this->cleanupDeploymentTarget($manifest, $messages);
        $this->cleanupStorageLink($context, $messages);
        $this->cleanupGitignore($context, $messages);
        $this->cleanupPackageFiles($context, $manifest, $messages);

        try {
            $this->cleanupGitState($context, $manifest, $messages, $warnings);
        } catch (\Throwable $throwable) {
            return new RollbackResult(
                status: ActionStatus::Failure,
                strategy: $this->name(),
                message: $throwable->getMessage(),
                messages: $messages,
                reference: $manifest->snapshotName,
            );
        }

        if ($warnings !== []) {
            $messages = [...$messages, ...array_map(
                static fn (string $warning): string => 'Warning: ' . $warning,
                $warnings,
            )];
        }

        return new RollbackResult(
            status: $warnings === [] ? ActionStatus::Success : ActionStatus::Warning,
            strategy: $this->name(),
            message: "Removed the deployment artifacts for snapshot [{$manifest->snapshotName}] and cleaned package-managed changes.",
            messages: $messages,
            reference: $manifest->snapshotName,
        );
    }

    private function preflightGitCleanup(RollbackContext $context, DeploymentManifest $manifest): ?RollbackResult
    {
        $basePath = $context->environment->basePath;

        if (! $this->git->isInstalled() || ! $this->git->isRepository($basePath)) {
            return null;
        }

        $snapshotCommitHash = $this->resolveSnapshotCommitHash($context, $manifest);

        if ($snapshotCommitHash === null) {
            return null;
        }

        if ($this->git->isDirty($basePath) && ! $context->force) {
            return new RollbackResult(
                status: ActionStatus::Failure,
                strategy: $this->name(),
                message: 'The git working tree has uncommitted changes.',
                messages: ['Re-run with --force if you want to discard local changes while cleaning package artifacts.'],
                reference: $manifest->snapshotName,
            );
        }

        $headCommitHash = $this->git->headCommitHash($basePath);

        if ($headCommitHash !== null && $headCommitHash !== $snapshotCommitHash && ! $context->force) {
            return new RollbackResult(
                status: ActionStatus::Failure,
                strategy: $this->name(),
                message: 'The repository moved beyond the deployment snapshot commit.',
                messages: ['Re-run with --force to discard commits created after the published snapshot.'],
                reference: $manifest->snapshotName,
            );
        }

        return null;
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function cleanupDeploymentTarget(DeploymentManifest $manifest, array &$messages): void
    {
        $target = $manifest->targetPath;

        if (is_link($target) || $this->files->isFile($target)) {
            $this->files->delete($target);
            $messages[] = "Removed the published target file or symlink at [{$target}].";
        } elseif ($this->files->isDirectory($target)) {
            $this->files->deleteDirectory($target);
            $messages[] = "Removed the published target directory [{$target}].";
        } else {
            $messages[] = 'The published target directory was already absent.';
        }

        foreach ($this->files->glob($target . '.current-*') as $path) {
            if ($this->files->isDirectory($path)) {
                $this->files->deleteDirectory($path);
            } else {
                $this->files->delete($path);
            }

            $messages[] = "Removed the previous rollback residue at [{$path}].";
        }
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function cleanupStorageLink(RollbackContext $context, array &$messages): void
    {
        $linkPath = PathHelper::join(
            $context->environment->basePath,
            (string) $this->config->get('hosting-shared.public_storage_link', 'public/storage'),
        );
        $sourcePath = PathHelper::join(
            $context->environment->basePath,
            (string) $this->config->get('hosting-shared.storage_public_directory', 'storage/app/public'),
        );

        if (! is_link($linkPath)) {
            return;
        }

        if (! $this->linkMatches($linkPath, $sourcePath)) {
            return;
        }

        $this->files->delete($linkPath);
        $messages[] = "Removed the package-managed storage symlink at [{$linkPath}].";
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function cleanupGitignore(RollbackContext $context, array &$messages): void
    {
        $gitignorePath = PathHelper::join($context->environment->basePath, '.gitignore');

        if (! $this->files->exists($gitignorePath)) {
            return;
        }

        $content = $this->files->get($gitignorePath);
        $updated = $this->removeMarkedGitignoreBlock($content);

        if ($updated === $content) {
            return;
        }

        $this->files->put($gitignorePath, $this->normalizeGitignore($updated));
        $messages[] = 'Removed Laravel Shared .gitignore additions.';
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function cleanupPackageFiles(RollbackContext $context, DeploymentManifest $manifest, array &$messages): void
    {
        if ($this->files->isDirectory($manifest->backupPath)) {
            $this->files->deleteDirectory($manifest->backupPath);
            $messages[] = "Removed the deployment backup directory [{$manifest->backupPath}].";
        }

        if ($this->manifests->deleteBySnapshot($manifest->snapshotName)) {
            $messages[] = "Removed the deployment manifest for [{$manifest->snapshotName}].";
        }

        $backupDirectoryRoot = PathHelper::join(
            $context->environment->storagePath,
            (string) $this->config->get('hosting-shared.deployment.backup_directory', 'app/hosting-shared/backups'),
        );
        $manifestDirectoryRoot = PathHelper::join(
            $context->environment->storagePath,
            (string) $this->config->get('hosting-shared.deployment.manifest_directory', 'app/hosting-shared/manifests'),
        );
        $packageStorageRoot = PathHelper::join($context->environment->storagePath, 'app/hosting-shared');

        $this->pruneEmptyDirectory($backupDirectoryRoot);
        $this->pruneEmptyDirectory($manifestDirectoryRoot);
        $this->pruneEmptyDirectory($packageStorageRoot);

        $probeDirectory = PathHelper::join($context->environment->storagePath, 'framework/cache/hosting-shared');

        if ($this->files->isDirectory($probeDirectory)) {
            $this->files->deleteDirectory($probeDirectory);
            $messages[] = "Removed the symlink probe cache directory [{$probeDirectory}].";
        }

        $logFile = PathHelper::join(
            $context->environment->storagePath,
            (string) $this->config->get('hosting-shared.deployment.log_file', 'logs/hosting-shared.log'),
        );

        if ($this->files->exists($logFile)) {
            $this->files->delete($logFile);
            $messages[] = "Removed the deployment log file [{$logFile}].";
        }
    }

    /**
     * @param  array<int, string>  $messages
     * @param  array<int, string>  $warnings
     */
    private function cleanupGitState(
        RollbackContext $context,
        DeploymentManifest $manifest,
        array &$messages,
        array &$warnings,
    ): void {
        $basePath = $context->environment->basePath;

        if (! $this->git->isInstalled() || ! $this->git->isRepository($basePath)) {
            return;
        }

        $snapshotCommitHash = $this->resolveSnapshotCommitHash($context, $manifest);

        if ($snapshotCommitHash === null) {
            $warnings[] = 'Could not resolve the publish snapshot commit automatically, so git history was left unchanged.';
        } else {
            $previousCommitHash = $manifest->previousGitCommitHash ?? $this->git->parentCommitHash($basePath, $snapshotCommitHash);

            if ($previousCommitHash === null) {
                $warnings[] = 'There was no previous commit before the published snapshot, so git history was left unchanged.';
            } else {
                $this->git->resetHard($basePath, $previousCommitHash);
                $messages[] = "Reset the repository to the commit before snapshot [{$manifest->snapshotName}].";
            }
        }

        if ($manifest->tagName !== null && in_array($manifest->tagName, $this->git->tags($basePath), true)) {
            $this->git->deleteTag($basePath, $manifest->tagName);
            $messages[] = "Deleted the deployment tag [{$manifest->tagName}].";
        }
    }

    private function resolveSnapshotCommitHash(RollbackContext $context, DeploymentManifest $manifest): ?string
    {
        if ($manifest->gitCommitHash !== null && $manifest->gitCommitHash !== '') {
            return $manifest->gitCommitHash;
        }

        if ($manifest->tagName !== null) {
            $tagCommitHash = $this->git->commitHash($context->environment->basePath, $manifest->tagName);

            if ($tagCommitHash !== null) {
                return $tagCommitHash;
            }
        }

        return $this->git->findCommitByMessage(
            $context->environment->basePath,
            "chore: hosting deployment snapshot [{$manifest->snapshotName}]",
        );
    }

    private function removeMarkedGitignoreBlock(string $content): string
    {
        $pattern = '/\R?' . preg_quote(self::START_MARKER, '/') . '\R.*?\R' . preg_quote(self::END_MARKER, '/') . '\R?/s';

        return (string) preg_replace($pattern, PHP_EOL, $content);
    }

    private function normalizeGitignore(string $content): string
    {
        $content = preg_replace("/(\R){3,}/", PHP_EOL . PHP_EOL, $content) ?? $content;

        return rtrim($content) . PHP_EOL;
    }

    private function linkMatches(string $linkPath, string $sourcePath): bool
    {
        $currentTarget = readlink($linkPath);

        if ($currentTarget === false) {
            return false;
        }

        $resolvedCurrent = realpath(dirname($linkPath) . DIRECTORY_SEPARATOR . $currentTarget) ?: realpath($currentTarget);
        $resolvedExpected = realpath($sourcePath);

        return $resolvedCurrent !== false && $resolvedExpected !== false && $resolvedCurrent === $resolvedExpected;
    }

    private function pruneEmptyDirectory(string $directory): void
    {
        $current = $directory;

        while ($this->files->isDirectory($current) && $this->files->isEmptyDirectory($current, true)) {
            $this->files->deleteDirectory($current);
            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }
    }
}
