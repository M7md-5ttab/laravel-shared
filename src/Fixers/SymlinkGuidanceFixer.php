<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Fixers;

use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;
use M7md5ttab\LaravelShared\Support\HostingProviderDetector;

final class SymlinkGuidanceFixer implements FixerInterface
{
    public function __construct(private readonly HostingProviderDetector $providerDetector)
    {
    }

    public function supports(CheckResult $result): bool
    {
        return $result->key === 'symlink-support';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Advisory;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Show hosting-provider guidance for working around missing symlink support?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        return new FixResult(
            name: 'Symlink guidance',
            status: ActionStatus::Warning,
            message: 'Symlink support cannot be enabled safely from inside the package.',
            nextStep: $this->providerDetector->guidance($context->hostingProvider),
        );
    }
}
