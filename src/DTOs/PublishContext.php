<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\DeploymentMode;

final class PublishContext
{
    public function __construct(
        public readonly EnvironmentContext $environment,
        public readonly DeploymentMode $mode,
        public readonly string $targetPath,
        public readonly string $backupPath,
        public readonly string $relativeBasePath,
        public readonly DeploymentSnapshot $snapshot,
        public readonly bool $dryRun = false,
    ) {
    }
}
