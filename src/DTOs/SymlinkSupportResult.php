<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

final class SymlinkSupportResult
{
    public function __construct(
        public readonly bool $supported,
        public readonly string $message,
    ) {
    }
}
