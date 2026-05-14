<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\SymlinkSupportResult;

final class SymlinkSupportDetector
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function detect(EnvironmentContext $context): SymlinkSupportResult
    {
        if (! function_exists('symlink')) {
            return new SymlinkSupportResult(false, 'The PHP symlink function is unavailable.');
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        if (in_array('symlink', $disabled, true)) {
            return new SymlinkSupportResult(false, 'The symlink function is disabled by PHP configuration.');
        }

        $probeDirectory = PathHelper::join($context->storagePath, 'framework/cache/hosting-shared');
        $source = PathHelper::join($probeDirectory, 'source.txt');
        $link = PathHelper::join($probeDirectory, 'link.txt');

        try {
            $this->files->ensureDirectoryExists($probeDirectory);
            $this->files->put($source, 'hosting-shared');

            if ($this->files->exists($link) || is_link($link)) {
                $this->files->delete($link);
            }

            $this->files->link($source, $link);

            $supported = is_link($link) || $this->files->exists($link);

            $this->cleanup($source, $link);

            return new SymlinkSupportResult(
                $supported,
                $supported
                    ? 'Symlink creation succeeded in a safe probe directory.'
                    : 'A symlink probe completed without creating a usable link.',
            );
        } catch (\Throwable $throwable) {
            $this->cleanup($source, $link);

            return new SymlinkSupportResult(false, 'Symlink probe failed: ' . $throwable->getMessage());
        }
    }

    private function cleanup(string $source, string $link): void
    {
        if ($this->files->exists($link) || is_link($link)) {
            $this->files->delete($link);
        }

        if ($this->files->exists($source)) {
            $this->files->delete($source);
        }
    }
}
