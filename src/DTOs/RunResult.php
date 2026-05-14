<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;

final class RunResult
{
    /**
     * @param  array<int, FixResult>  $automaticFixes
     * @param  array<int, FixResult>  $advisoryItems
     */
    public function __construct(
        public readonly ActionStatus $status,
        public readonly string $message,
        public readonly ReadinessReport $initialReport,
        public readonly ReadinessReport $finalReport,
        public readonly array $automaticFixes = [],
        public readonly array $advisoryItems = [],
        public readonly ?PublishResult $deployment = null,
        public readonly ?string $targetPath = null,
        public readonly ?DeploymentMode $mode = null,
        public readonly bool $dryRun = false,
        public readonly string $gitSnapshotStatus = 'Git snapshot status is unavailable.',
        public readonly ?string $tagStatus = null,
    ) {
    }
}
