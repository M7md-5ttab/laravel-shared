<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use Illuminate\Contracts\Config\Repository;
use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class LaravelVersionCheck implements CheckInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        $minimumVersion = (string) $this->config->get('hosting-shared.minimum_laravel_version', '11.0.0');

        if (version_compare($context->laravelVersion, $minimumVersion, '>=')) {
            return new CheckResult(
                key: 'laravel-version',
                name: 'Laravel version',
                status: CheckStatus::Passed,
                message: "Laravel {$context->laravelVersion} is supported by this package.",
            );
        }

        return new CheckResult(
            key: 'laravel-version',
            name: 'Laravel version',
            status: CheckStatus::Failed,
            message: "Laravel {$context->laravelVersion} is below the supported version {$minimumVersion}.",
            recommendedFix: 'Upgrade Laravel before relying on this package in production.',
        );
    }
}
