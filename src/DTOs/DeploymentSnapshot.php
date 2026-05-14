<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

final class DeploymentSnapshot
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $tagName,
        public readonly bool $gitCommitted,
        public readonly string $createdAt,
        public readonly ?string $commitHash = null,
        public readonly ?string $previousCommitHash = null,
    ) {
    }
}
