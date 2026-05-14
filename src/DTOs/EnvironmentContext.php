<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\HostingProvider;

final class EnvironmentContext
{
    public function __construct(
        public readonly string $basePath,
        public readonly string $publicPath,
        public readonly string $storagePath,
        public readonly string $bootstrapCachePath,
        public readonly string $phpVersion,
        public readonly string $laravelVersion,
        public readonly string $operatingSystem,
        public readonly HostingProvider $hostingProvider,
        public readonly ?string $publicHtmlPath,
    ) {
    }
}
