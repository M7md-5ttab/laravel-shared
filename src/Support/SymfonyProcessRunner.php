<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\DTOs\ProcessResult;
use Symfony\Component\Process\Process;

final class SymfonyProcessRunner implements ProcessRunnerInterface
{
    public function run(
        array $command,
        ?string $workingDirectory = null,
        array $environment = [],
        ?int $timeout = 60,
    ): ProcessResult {
        $process = new Process($command, $workingDirectory, $environment === [] ? null : $environment, null, $timeout);
        $process->run();

        return new ProcessResult(
            command: $command,
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
