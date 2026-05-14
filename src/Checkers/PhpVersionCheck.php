<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use Illuminate\Contracts\Config\Repository;
use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class PhpVersionCheck implements CheckInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        $requiredVersion = (string) $this->config->get('hosting-shared.required_php_version', '8.2.0');

        if (version_compare($context->phpVersion, $requiredVersion, '>=')) {
            return new CheckResult(
                key: 'php-version',
                name: 'PHP version compatibility',
                status: CheckStatus::Passed,
                message: "PHP {$context->phpVersion} satisfies the package requirement ({$requiredVersion}+).",
            );
        }

        return new CheckResult(
            key: 'php-version',
            name: 'PHP version compatibility',
            status: CheckStatus::Failed,
            message: "PHP {$context->phpVersion} is below the required version {$requiredVersion}.",
            recommendedFix: 'Upgrade the server or CLI PHP version before deploying.',
        );
    }
}
