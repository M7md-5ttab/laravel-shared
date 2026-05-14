<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Git\GitService;

final class GitRepositoryCheck implements CheckInterface
{
    public function __construct(private readonly GitService $git)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        if (! $this->git->isInstalled()) {
            return new CheckResult(
                key: 'git-repository',
                name: 'Git repository detected',
                status: CheckStatus::Warning,
                message: 'Repository check was skipped because Git is unavailable.',
                recommendedFix: 'Install Git and initialize a repository for safer deployments.',
                blocking: false,
            );
        }

        if ($this->git->isRepository($context->basePath)) {
            return new CheckResult(
                key: 'git-repository',
                name: 'Git repository detected',
                status: CheckStatus::Passed,
                message: 'The current Laravel application is inside a git repository.',
            );
        }

        return new CheckResult(
            key: 'git-repository',
            name: 'Git repository detected',
            status: CheckStatus::Warning,
            message: 'No git repository was detected for this Laravel application.',
            recommendedFix: 'Run hosting:run to initialize git and create an initial snapshot.',
            blocking: false,
        );
    }
}
