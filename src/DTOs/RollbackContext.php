<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

final class RollbackContext
{
    public function __construct(
        public readonly EnvironmentContext $environment,
        public readonly ?string $tagName = null,
        public readonly ?DeploymentManifest $manifest = null,
        public readonly bool $force = false,
        public readonly ?string $preferredStrategy = null,
    ) {
    }
}
