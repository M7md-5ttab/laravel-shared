<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class CheckResult
{
    /**
     * @param  array<int, string>  $details
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly CheckStatus $status,
        public readonly string $message,
        public readonly ?string $recommendedFix = null,
        public readonly array $details = [],
        public readonly bool $blocking = true,
    ) {
    }

    public function isPassed(): bool
    {
        return $this->status === CheckStatus::Passed;
    }

    public function needsAttention(): bool
    {
        return $this->status === CheckStatus::Warning || $this->status === CheckStatus::Failed;
    }
}
