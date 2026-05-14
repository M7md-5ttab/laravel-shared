<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Fixers;

use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;
use M7md5ttab\LaravelShared\Git\GitService;

final class GitRepositoryFixer implements FixerInterface
{
    public function __construct(private readonly GitService $git)
    {
    }

    public function supports(CheckResult $result): bool
    {
        return $result->key === 'git-repository';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Automatic;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Initialize a git repository in the Laravel application root?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        if (! $this->git->isInstalled()) {
            return new FixResult(
                name: 'Git repository initialization',
                status: ActionStatus::Warning,
                message: 'Git is not installed, so the repository cannot be initialized yet.',
            );
        }

        if ($this->git->isRepository($context->basePath)) {
            return new FixResult(
                name: 'Git repository initialization',
                status: ActionStatus::Success,
                message: 'A git repository already exists.',
            );
        }

        $this->git->initializeRepository($context->basePath);

        return new FixResult(
            name: 'Git repository initialization',
            status: ActionStatus::Success,
            message: 'Initialized a git repository in the application root.',
            performed: true,
        );
    }
}
