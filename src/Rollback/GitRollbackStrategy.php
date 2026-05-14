<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Rollback;

use M7md5ttab\LaravelShared\Contracts\RollbackStrategyInterface;
use M7md5ttab\LaravelShared\DTOs\RollbackContext;
use M7md5ttab\LaravelShared\DTOs\RollbackResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Git\GitService;

final class GitRollbackStrategy implements RollbackStrategyInterface
{
    public function __construct(private readonly GitService $git)
    {
    }

    public function name(): string
    {
        return 'git';
    }

    public function supports(RollbackContext $context): bool
    {
        $tag = $context->tagName ?? $context->manifest?->tagName;

        return $tag !== null
            && $this->git->isInstalled()
            && $this->git->isRepository($context->environment->basePath);
    }

    public function rollback(RollbackContext $context): RollbackResult
    {
        $tag = $context->tagName ?? $context->manifest?->tagName;

        if ($tag === null) {
            return new RollbackResult(
                status: ActionStatus::Failure,
                strategy: $this->name(),
                message: 'No git tag was provided for rollback.',
            );
        }

        if ($this->git->isDirty($context->environment->basePath) && ! $context->force) {
            return new RollbackResult(
                status: ActionStatus::Failure,
                strategy: $this->name(),
                message: 'The git working tree has uncommitted changes.',
                reference: $tag,
                messages: ['Re-run with --force after reviewing local changes if you want to proceed.'],
            );
        }

        $this->git->rollbackToTag($context->environment->basePath, $tag);

        return new RollbackResult(
            status: ActionStatus::Success,
            strategy: $this->name(),
            message: "Rolled the repository back to git tag [{$tag}].",
            reference: $tag,
        );
    }
}
