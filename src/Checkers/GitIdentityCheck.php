<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Git\GitService;

final class GitIdentityCheck implements CheckInterface
{
    public function __construct(private readonly GitService $git)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        if (! $this->git->isInstalled() || ! $this->git->isRepository($context->basePath)) {
            return new CheckResult(
                key: 'git-identity',
                name: 'Git identity',
                status: CheckStatus::Info,
                message: 'Git identity validation was skipped because the project is not ready for git commits.',
                recommendedFix: 'Create or detect a git repository first, then set git user.name and user.email if commits are required.',
                blocking: false,
            );
        }

        if ($this->git->hasIdentity($context->basePath)) {
            return new CheckResult(
                key: 'git-identity',
                name: 'Git identity',
                status: CheckStatus::Passed,
                message: 'Git user.name and user.email are configured for this repository.',
            );
        }

        return new CheckResult(
            key: 'git-identity',
            name: 'Git identity',
            status: CheckStatus::Failed,
            message: 'Git identity is not configured for deployment snapshot commits.',
            recommendedFix: 'Run git config user.name "Your Name" && git config user.email "you@example.com" inside this repository.',
        );
    }
}
