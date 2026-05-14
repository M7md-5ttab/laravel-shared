<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Fixers;

use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;

final class PublicHtmlGuidanceFixer implements FixerInterface
{
    public function supports(CheckResult $result): bool
    {
        return $result->key === 'public-html';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Advisory;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Print guidance for creating or selecting a public_html deployment target?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        return new FixResult(
            name: 'public_html guidance',
            status: ActionStatus::Warning,
            message: 'The package does not create public_html automatically because hosting layouts vary.',
            nextStep: 'Create a sibling public_html directory or pass --target=/absolute/path/to/public_html when running a deployment.',
        );
    }
}
