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

final class GitIdentityFixer implements FixerInterface
{
    private const DEFAULT_NAME = 'Laravel Shared Tool';

    private const DEFAULT_EMAIL = 'tool@local';

    public function __construct(private readonly GitService $git)
    {
    }

    public function supports(CheckResult $result): bool
    {
        return $result->key === 'git-identity';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Interactive;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Git identity not configured. Do you want to auto-config it?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        if (! $this->git->isInstalled() || ! $this->git->isRepository($context->basePath)) {
            return new FixResult(
                name: 'Git identity',
                status: ActionStatus::Warning,
                message: 'Git identity could not be configured because this project is not ready for git commits yet.',
            );
        }

        if ($this->git->hasIdentity($context->basePath)) {
            return new FixResult(
                name: 'Git identity',
                status: ActionStatus::Success,
                message: 'Git identity is already configured.',
            );
        }

        $this->git->configureIdentity($context->basePath, self::DEFAULT_NAME, self::DEFAULT_EMAIL);

        return new FixResult(
            name: 'Git identity',
            status: ActionStatus::Success,
            message: 'Configured a local git identity for this repository.',
            performed: true,
        );
    }
}
