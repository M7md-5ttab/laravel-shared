<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

final class ProcessResult
{
    public function __construct(
        public readonly array $command,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
