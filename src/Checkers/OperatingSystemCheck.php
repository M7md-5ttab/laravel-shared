<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class OperatingSystemCheck implements CheckInterface
{
    public function check(EnvironmentContext $context): CheckResult
    {
        return new CheckResult(
            key: 'operating-system',
            name: 'Operating system',
            status: CheckStatus::Passed,
            message: "Detected operating system: {$context->operatingSystem}.",
            blocking: false,
        );
    }
}
