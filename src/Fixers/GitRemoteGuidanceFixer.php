<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Fixers;

use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;

final class GitRemoteGuidanceFixer implements FixerInterface
{
    public function supports(CheckResult $result): bool
    {
        return $result->key === 'git-remote';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Advisory;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Print the recommended command for adding a remote repository?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        return new FixResult(
            name: 'Git remote guidance',
            status: ActionStatus::Warning,
            message: 'Remote repositories should be configured manually to avoid pointing at the wrong project.',
            nextStep: 'Run: git remote add origin <repository-url>',
        );
    }
}
