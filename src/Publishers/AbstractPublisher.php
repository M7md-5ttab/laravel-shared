<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Publishers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\DTOs\PublishContext;
use M7md5ttab\LaravelShared\Support\IndexPhpPatcher;
use M7md5ttab\LaravelShared\Support\PathHelper;

abstract class AbstractPublisher
{
    public function __construct(
        protected readonly Filesystem $files,
        protected readonly IndexPhpPatcher $patcher,
        protected readonly Repository $config,
    ) {
    }

    /**
     * @return array<int, string>
     */
    protected function copyPublicDirectory(PublishContext $context): array
    {
        if ($context->dryRun) {
            return ["[dry-run] Would synchronize public assets from [{$context->environment->publicPath}] to [{$context->targetPath}]."];
        }

        $this->files->ensureDirectoryExists($context->targetPath);
        $expectedFiles = [];
        $expectedDirectories = ['.' => true];

        foreach ($this->files->allFiles($context->environment->publicPath, true) as $file) {
            $relative = str_replace($context->environment->publicPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $normalized = str_replace('\\', '/', $relative);

            if ($normalized === 'storage' || str_starts_with($normalized, 'storage/')) {
                continue;
            }

            $target = PathHelper::join($context->targetPath, $relative);
            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->copy($file->getPathname(), $target);
            $expectedFiles[$normalized] = true;
            $this->registerExpectedDirectories($expectedDirectories, $normalized);
        }

        $this->pruneUnexpectedTargetPaths($context->targetPath, $expectedFiles, $expectedDirectories);

        return ["Synchronized public assets into [{$context->targetPath}]."];
    }

    /**
     * @return array<int, string>
     */
    protected function patchIndexFile(PublishContext $context): array
    {
        $sourceIndex = PathHelper::join($context->environment->publicPath, 'index.php');
        $targetIndex = PathHelper::join($context->targetPath, 'index.php');

        if ($context->dryRun) {
            return ["[dry-run] Would patch index.php to point at [{$context->relativeBasePath}]."];
        }

        $this->patcher->patch($sourceIndex, $targetIndex, $context->relativeBasePath);

        return ["Patched [{$targetIndex}] to load the Laravel application from [{$context->relativeBasePath}]."];
    }

    /**
     * @return array<int, string>
     */
    protected function copyStorageAssets(PublishContext $context): array
    {
        $source = PathHelper::join(
            $context->environment->basePath,
            (string) $this->config->get('hosting-shared.storage_public_directory', 'storage/app/public'),
        );
        $target = PathHelper::join($context->targetPath, 'storage');

        if (! $this->files->isDirectory($source)) {
            return ['No storage/app/public directory was found, so no storage assets were copied.'];
        }

        if ($context->dryRun) {
            return ["[dry-run] Would copy storage assets from [{$source}] to [{$target}]."];
        }

        if (is_link($target) || $this->files->isFile($target)) {
            $this->files->delete($target);
        } else {
            $this->files->ensureDirectoryExists($target);
        }

        foreach ($this->files->allFiles($source, true) as $file) {
            $relative = str_replace($source . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $destination = PathHelper::join($target, $relative);
            $this->files->ensureDirectoryExists(dirname($destination));
            $this->files->copy($file->getPathname(), $destination);
        }

        return ["Copied storage assets into [{$target}]."];
    }

    /**
     * @return array<int, string>
     */
    protected function verifyIndexPatch(PublishContext $context): array
    {
        if ($context->dryRun) {
            return ['[dry-run] Skipped index.php verification because files were not written.'];
        }

        $issues = $this->patcher->verify(
            PathHelper::join($context->targetPath, 'index.php'),
            $context->environment->basePath,
            $context->relativeBasePath,
        );

        return $issues === []
            ? ['Verified autoload and bootstrap path patching.']
            : $issues;
    }

    /**
     * @param  array<string, bool>  $expectedDirectories
     */
    private function registerExpectedDirectories(array &$expectedDirectories, string $relativeFilePath): void
    {
        $directory = dirname($relativeFilePath);

        while ($directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
            $expectedDirectories[str_replace('\\', '/', $directory)] = true;
            $directory = dirname($directory);
        }
    }

    /**
     * @param  array<string, bool>  $expectedFiles
     * @param  array<string, bool>  $expectedDirectories
     */
    private function pruneUnexpectedTargetPaths(string $targetPath, array $expectedFiles, array $expectedDirectories): void
    {
        foreach ($this->files->allFiles($targetPath, true) as $file) {
            $relativePath = $this->relativePath($targetPath, $file->getPathname());

            if ($this->shouldPreserve($relativePath) || isset($expectedFiles[$relativePath])) {
                continue;
            }

            $this->files->delete($file->getPathname());
        }

        $this->pruneUnexpectedDirectories($targetPath, $targetPath, $expectedDirectories);
    }

    /**
     * @param  array<string, bool>  $expectedDirectories
     */
    private function pruneUnexpectedDirectories(string $rootPath, string $directory, array $expectedDirectories): void
    {
        foreach ($this->files->directories($directory) as $childDirectory) {
            $relativePath = $this->relativePath($rootPath, $childDirectory);

            if ($this->shouldPreserve($relativePath)) {
                continue;
            }

            $this->pruneUnexpectedDirectories($rootPath, $childDirectory, $expectedDirectories);

            if (isset($expectedDirectories[$relativePath])) {
                continue;
            }

            if ($this->files->isEmptyDirectory($childDirectory, true)) {
                $this->files->deleteDirectory($childDirectory);
            }
        }
    }

    private function relativePath(string $rootPath, string $path): string
    {
        $relativePath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $path);

        return str_replace('\\', '/', $relativePath);
    }

    private function shouldPreserve(string $relativePath): bool
    {
        if ($relativePath === 'storage' || str_starts_with($relativePath, 'storage/')) {
            return true;
        }

        foreach ((array) $this->config->get('hosting-shared.deployment.preserve_paths', []) as $preservedPath) {
            $normalized = trim(str_replace('\\', '/', (string) $preservedPath), '/');

            if ($normalized === '') {
                continue;
            }

            if ($relativePath === $normalized || str_starts_with($relativePath, $normalized . '/')) {
                return true;
            }
        }

        return false;
    }
}
