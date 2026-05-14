<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use Illuminate\Contracts\Foundation\Application;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;

final class ProjectEnvironmentResolver
{
    public function __construct(
        private readonly Application $app,
        private readonly HostingProviderDetector $providerDetector,
        private readonly PublicHtmlLocator $publicHtmlLocator,
    ) {
    }

    public function resolve(): EnvironmentContext
    {
        $basePath = $this->app->basePath();
        $publicPath = $this->app->publicPath();
        $storagePath = $this->app->storagePath();
        $bootstrapCachePath = $basePath . '/bootstrap/cache';

        return new EnvironmentContext(
            basePath: $basePath,
            publicPath: $publicPath,
            storagePath: $storagePath,
            bootstrapCachePath: $bootstrapCachePath,
            phpVersion: PHP_VERSION,
            laravelVersion: $this->normalizeLaravelVersion($this->app->version()),
            operatingSystem: PHP_OS_FAMILY,
            hostingProvider: $this->providerDetector->detect($basePath),
            publicHtmlPath: $this->publicHtmlLocator->detect($basePath),
        );
    }

    private function normalizeLaravelVersion(string $version): string
    {
        if (preg_match('/(\d+\.\d+\.\d+)/', $version, $matches) === 1) {
            return $matches[1];
        }

        return $version;
    }
}
