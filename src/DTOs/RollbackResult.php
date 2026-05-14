<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\ActionStatus;

final class RollbackResult
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public readonly ActionStatus $status,
        public readonly string $strategy,
        public readonly string $message,
        public readonly array $messages = [],
        public readonly ?string $reference = null,
    ) {
    }
}
