<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Exceptions\HostingException;

final class IndexPhpPatcher
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function patch(string $sourceIndexPath, string $targetIndexPath, string $relativeBasePath): void
    {
        if (! $this->files->exists($sourceIndexPath)) {
            throw new HostingException("Source index.php was not found at [{$sourceIndexPath}].");
        }

        $content = $this->files->get($sourceIndexPath);
        $maintenance = "__DIR__.'" . $this->relativeFilePath($relativeBasePath, 'storage/framework/maintenance.php') . "'";
        $autoload = "__DIR__.'" . $this->relativeFilePath($relativeBasePath, 'vendor/autoload.php') . "'";
        $bootstrap = "__DIR__.'" . $this->relativeFilePath($relativeBasePath, 'bootstrap/app.php') . "'";

        $content = str_replace(
            ["__DIR__.'/../storage/framework/maintenance.php'", '__DIR__."/../storage/framework/maintenance.php"'],
            [$maintenance, $maintenance],
            $content,
        );

        $content = str_replace(
            ["__DIR__.'/../vendor/autoload.php'", '__DIR__."/../vendor/autoload.php"'],
            [$autoload, $autoload],
            $content,
        );

        $content = str_replace(
            ["__DIR__.'/../bootstrap/app.php'", '__DIR__."/../bootstrap/app.php"'],
            [$bootstrap, $bootstrap],
            $content,
        );

        $this->files->put($targetIndexPath, $content);
    }

    /**
     * @return array<int, string>
     */
    public function verify(string $targetIndexPath, string $basePath, string $relativeBasePath): array
    {
        $issues = [];

        if (! $this->files->exists($targetIndexPath)) {
            $issues[] = 'Target index.php was not generated.';

            return $issues;
        }

        $content = $this->files->get($targetIndexPath);
        $expectedMaintenance = "__DIR__.'" . $this->relativeFilePath($relativeBasePath, 'storage/framework/maintenance.php') . "'";
        $expectedAutoload = "__DIR__.'" . $this->relativeFilePath($relativeBasePath, 'vendor/autoload.php') . "'";
        $expectedBootstrap = "__DIR__.'" . $this->relativeFilePath($relativeBasePath, 'bootstrap/app.php') . "'";

        if (! str_contains($content, $expectedMaintenance)) {
            $issues[] = 'Maintenance path was not patched as expected.';
        }

        if (! str_contains($content, $expectedAutoload)) {
            $issues[] = 'Autoload path was not patched as expected.';
        }

        if (! str_contains($content, $expectedBootstrap)) {
            $issues[] = 'bootstrap/app.php path was not patched as expected.';
        }

        if (! $this->files->exists(PathHelper::join($basePath, 'vendor/autoload.php'))) {
            $issues[] = 'vendor/autoload.php is missing from the Laravel application.';
        }

        if (! $this->files->exists(PathHelper::join($basePath, 'bootstrap/app.php'))) {
            $issues[] = 'bootstrap/app.php is missing from the Laravel application.';
        }

        if (! $this->files->isDirectory(PathHelper::join($basePath, 'storage/framework'))) {
            $issues[] = 'storage/framework is missing from the Laravel application.';
        }

        return $issues;
    }

    private function relativeFilePath(string $relativeBasePath, string $suffix): string
    {
        $basePath = trim($relativeBasePath, '/');

        if ($basePath === '' || $basePath === '.') {
            return './' . $suffix;
        }

        return '/' . $basePath . '/' . $suffix;
    }
}
