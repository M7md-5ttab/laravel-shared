<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Git\GitService;

final class GitRemoteCheck implements CheckInterface
{
    public function __construct(private readonly GitService $git)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        if (! $this->git->isInstalled() || ! $this->git->isRepository($context->basePath)) {
            return new CheckResult(
                key: 'git-remote',
                name: 'Git remote detected',
                status: CheckStatus::Info,
                message: 'Remote validation was skipped because the project is not ready for git inspection.',
                recommendedFix: 'Create a repository first, then add a remote such as origin.',
                blocking: false,
            );
        }

        if ($this->git->hasRemote($context->basePath)) {
            return new CheckResult(
                key: 'git-remote',
                name: 'Git remote detected',
                status: CheckStatus::Passed,
                message: 'At least one git remote is configured.',
            );
        }

        return new CheckResult(
            key: 'git-remote',
            name: 'Git remote detected',
            status: CheckStatus::Info,
            message: 'No remote repository is configured.',
            recommendedFix: 'Add a remote with: git remote add origin <repository-url>',
            blocking: false,
        );
    }
}
