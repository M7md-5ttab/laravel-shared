<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Support\HostingProviderDetector;

final class HostingProviderCheck implements CheckInterface
{
    public function __construct(private readonly HostingProviderDetector $providerDetector)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        if ($context->hostingProvider !== HostingProvider::Generic) {
            return new CheckResult(
                key: 'hosting-provider',
                name: 'Hosting provider detection',
                status: CheckStatus::Passed,
                message: 'Detected hosting provider: ' . $context->hostingProvider->label() . '.',
                recommendedFix: $this->providerDetector->guidance($context->hostingProvider),
                blocking: false,
            );
        }

        return new CheckResult(
            key: 'hosting-provider',
            name: 'Hosting provider detection',
            status: CheckStatus::Info,
            message: 'No known hosting provider signature was detected.',
            recommendedFix: $this->providerDetector->guidance($context->hostingProvider),
            blocking: false,
        );
    }
}
