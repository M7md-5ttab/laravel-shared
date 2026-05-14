<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Support\HostingProviderDetector;
use M7md5ttab\LaravelShared\Support\SymlinkSupportDetector;

final class SymlinkSupportCheck implements CheckInterface
{
    public function __construct(
        private readonly SymlinkSupportDetector $symlinkSupport,
        private readonly HostingProviderDetector $providerDetector,
    ) {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        $probe = $this->symlinkSupport->detect($context);

        if ($probe->supported) {
            return new CheckResult(
                key: 'symlink-support',
                name: 'Symlink support',
                status: CheckStatus::Passed,
                message: $probe->message,
                blocking: false,
            );
        }

        return new CheckResult(
            key: 'symlink-support',
            name: 'Symlink support',
            status: CheckStatus::Warning,
            message: $probe->message,
            recommendedFix: $this->providerDetector->guidance($context->hostingProvider),
            blocking: false,
        );
    }
}
