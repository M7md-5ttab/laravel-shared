<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Publishers;

use Carbon\CarbonImmutable;
use M7md5ttab\LaravelShared\Contracts\PublisherInterface;
use M7md5ttab\LaravelShared\DTOs\PublishContext;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Support\PathHelper;

final class SymlinkPublisher extends AbstractPublisher implements PublisherInterface
{
    public function mode(): DeploymentMode
    {
        return DeploymentMode::Symlink;
    }

    public function publish(PublishContext $context): PublishResult
    {
        $messages = [
            ...$this->copyPublicDirectory($context),
            ...$this->patchIndexFile($context),
        ];

        $storageLinkPath = PathHelper::join($context->targetPath, 'storage');
        $storageSourcePath = PathHelper::join(
            $context->environment->basePath,
            (string) $this->config->get('hosting-shared.storage_public_directory', 'storage/app/public'),
        );

        if ($context->dryRun) {
            $messages[] = "[dry-run] Would create a storage symlink from [{$storageLinkPath}] to [{$storageSourcePath}].";
        } else {
            $this->files->ensureDirectoryExists($storageSourcePath);
            $relativeStoragePath = PathHelper::relativePath($context->targetPath, $storageSourcePath);

            if ($this->files->exists($storageLinkPath) && ! is_link($storageLinkPath)) {
                $backupPath = $storageLinkPath . '.backup-' . CarbonImmutable::now()->format('YmdHis');
                $this->files->move($storageLinkPath, $backupPath);
                $messages[] = "Backed up the existing storage directory to [{$backupPath}] before creating a symlink.";
            }

            if (is_link($storageLinkPath) && ! $this->linkMatches($storageLinkPath, $relativeStoragePath, $storageSourcePath)) {
                $this->files->delete($storageLinkPath);
            }

            if (! is_link($storageLinkPath)) {
                $this->files->link($relativeStoragePath, $storageLinkPath);
            }

            $messages[] = "Created a storage symlink at [{$storageLinkPath}].";
        }

        $messages = [...$messages, ...$this->verifyIndexPatch($context)];

        $issues = array_values(array_filter(
            $messages,
            static fn (string $message): bool => str_contains($message, 'missing') || str_contains($message, 'not patched'),
        ));

        return new PublishResult(
            status: $issues === [] ? ActionStatus::Success : ActionStatus::Warning,
            mode: $this->mode(),
            targetPath: $context->targetPath,
            message: $issues === []
                ? 'Application assets were prepared in symlink mode.'
                : 'Symlink mode completed with verification warnings.',
            messages: $messages,
            snapshot: $context->snapshot,
            storageLinked: true,
            dryRun: $context->dryRun,
        );
    }

    private function linkMatches(string $linkPath, string $expectedRelativePath, string $expectedAbsolutePath): bool
    {
        $currentTarget = readlink($linkPath);

        if ($currentTarget === false) {
            return false;
        }

        if ($currentTarget === $expectedRelativePath) {
            return true;
        }

        $resolvedCurrent = realpath(dirname($linkPath) . DIRECTORY_SEPARATOR . $currentTarget);
        $resolvedExpected = realpath($expectedAbsolutePath);

        return $resolvedCurrent !== false && $resolvedExpected !== false && $resolvedCurrent === $resolvedExpected;
    }
}
