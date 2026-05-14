<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Git\GitService;

final class GitInstalledCheck implements CheckInterface
{
    public function __construct(private readonly GitService $git)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        if ($this->git->isInstalled()) {
            return new CheckResult(
                key: 'git-installed',
                name: 'Git installed',
                status: CheckStatus::Passed,
                message: 'Git is available on this machine.',
            );
        }

        return new CheckResult(
            key: 'git-installed',
            name: 'Git installed',
            status: CheckStatus::Failed,
            message: 'Git is not installed or is unavailable in the current PATH.',
            recommendedFix: 'Install Git before using snapshot, tagging, and rollback features.',
        );
    }
}
