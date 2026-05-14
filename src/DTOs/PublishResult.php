<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;

final class PublishResult
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public readonly ActionStatus $status,
        public readonly DeploymentMode $mode,
        public readonly string $targetPath,
        public readonly string $message,
        public readonly array $messages = [],
        public readonly ?DeploymentSnapshot $snapshot = null,
        public readonly ?string $manifestPath = null,
        public readonly bool $storageLinked = false,
        public readonly bool $dryRun = false,
    ) {
    }
}
