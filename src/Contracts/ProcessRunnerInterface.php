<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Contracts;

use M7md5ttab\LaravelShared\DTOs\ProcessResult;

interface ProcessRunnerInterface
{
    public function run(
        array $command,
        ?string $workingDirectory = null,
        array $environment = [],
        ?int $timeout = 60,
    ): ProcessResult;
}
