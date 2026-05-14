<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\ActionStatus;

final class FixResult
{
    public function __construct(
        public readonly string $name,
        public readonly ActionStatus $status,
        public readonly string $message,
        public readonly bool $performed = false,
        public readonly ?string $nextStep = null,
    ) {
    }
}
