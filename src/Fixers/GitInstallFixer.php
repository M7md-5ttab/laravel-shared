<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Fixers;

use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;

final class GitInstallFixer implements FixerInterface
{
    public function supports(CheckResult $result): bool
    {
        return $result->key === 'git-installed';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Advisory;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Show the safest Git installation command for this operating system?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        $instruction = $this->manualInstruction($context->operatingSystem);

        return new FixResult(
            name: 'Git installation',
            status: ActionStatus::Warning,
            message: 'Git must be installed manually before snapshot, tag, and rollback features are available.',
            nextStep: $instruction,
        );
    }

    private function manualInstruction(string $operatingSystem): string
    {
        return match ($operatingSystem) {
            'Linux' => 'Install Git manually with your package manager, for example: sudo apt-get update && sudo apt-get install -y git',
            'Darwin' => 'Install Git manually with Homebrew: brew install git',
            'Windows' => 'Install Git manually with winget: winget install --id Git.Git -e --source winget',
            default => 'Install Git manually and make sure it is available in your PATH.',
        };
    }
}
