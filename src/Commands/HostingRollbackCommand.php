<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Commands;

use Illuminate\Console\Command;
use M7md5ttab\LaravelShared\Console\Concerns\InteractsWithHostingOutput;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Services\RollbackManager;

final class HostingRollbackCommand extends Command
{
    use InteractsWithHostingOutput;

    protected $signature = 'hosting:rollback
        {--tag= : Rollback to a specific git tag}
        {--snapshot= : Rollback using a saved deployment snapshot manifest}
        {--force : Allow rollback to proceed even when git has uncommitted changes}';

    protected $description = 'Rollback a shared hosting deployment using cleanup snapshots or explicit git tags.';

    public function handle(RollbackManager $rollbackManager): int
    {
        $this->renderRollbackHistory($rollbackManager->manifests(), $rollbackManager->gitTags());

        $tagName = $this->option('tag') !== null ? (string) $this->option('tag') : null;
        $snapshotName = $this->option('snapshot') !== null ? (string) $this->option('snapshot') : null;

        if ($rollbackManager->hasDirtyWorkingTree($tagName, $snapshotName) && ! (bool) $this->option('force')) {
            $this->components->warn('Uncommitted git changes were detected. Rollback will be blocked unless you pass --force.');
        }

        if (! $this->confirmWithChoice('Proceed with the rollback request?', false)) {
            $this->components->warn('Rollback cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $rollbackManager->rollback(
                tagName: $tagName,
                snapshotName: $snapshotName,
                force: (bool) $this->option('force'),
            );
        } catch (\Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->renderRollbackResult($result);

        return $result->status === ActionStatus::Failure ? self::FAILURE : self::SUCCESS;
    }
}
