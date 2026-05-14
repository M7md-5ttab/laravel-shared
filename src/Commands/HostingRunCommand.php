<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Commands;

use Illuminate\Console\Command;
use M7md5ttab\LaravelShared\Console\Concerns\InteractsWithHostingOutput;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Services\RunManager;

final class HostingRunCommand extends Command
{
    use InteractsWithHostingOutput;

    protected $signature = 'hosting:run
        {--symlink : Force symlink deployment mode}
        {--copy : Force copy deployment mode}
        {--target= : Absolute or relative path to the target public_html directory}
        {--tag : Create a git tag after the deployment snapshot commit}
        {--tag-name= : Custom git tag name to use for the deployment}
        {--dry-run : Preview automatic fixes and deployment actions without changing files}
        {--force : Skip the final deployment confirmation prompt}';

    protected $description = 'Run the shared hosting workflow: checks, automatic fixes, and deployment.';

    public function handle(RunManager $runManager): int
    {
        if ((bool) $this->option('symlink') && (bool) $this->option('copy')) {
            $this->components->error('Choose either --symlink or --copy, not both.');

            return self::FAILURE;
        }

        $requestedMode = match (true) {
            (bool) $this->option('symlink') => DeploymentMode::Symlink,
            (bool) $this->option('copy') => DeploymentMode::Copy,
            default => null,
        };

        $this->components->info('Running the shared hosting workflow...');

        $prepared = $runManager->prepare(
            requestedMode: $requestedMode,
            target: $this->option('target') !== null ? (string) $this->option('target') : null,
            dryRun: (bool) $this->option('dry-run'),
            createTag: (bool) $this->option('tag') || $this->option('tag-name') !== null,
            tagName: $this->option('tag-name') !== null ? (string) $this->option('tag-name') : null,
        );

        $this->renderReadinessReport($prepared->initialReport, 'Initial Readiness Report');

        if ($prepared->automaticFixes !== []) {
            $this->renderFixResults(
                $prepared->automaticFixes,
                $prepared->dryRun ? 'Planned Automatic Fixes' : 'Applied Automatic Fixes',
            );
        }

        if ($prepared->advisoryItems !== []) {
            $this->renderFixResults($prepared->advisoryItems, 'Manual Follow-up');
        }

        if (! $prepared->dryRun) {
            $this->renderReadinessReport($prepared->finalReport, 'Readiness After Automatic Fixes');
        }

        if ($prepared->status === ActionStatus::Failure) {
            $this->newLine();
            $this->renderActionLine($prepared->status, 'Run', $prepared->message);

            return self::FAILURE;
        }

        $this->renderRunSummary($prepared);

        if ($prepared->dryRun) {
            if ($prepared->deployment !== null) {
                $this->renderDeploymentResult($prepared->deployment);
            }

            return $prepared->status === ActionStatus::Failure ? self::FAILURE : self::SUCCESS;
        }

        if (! (bool) $this->option('force') && ! $this->confirmWithChoice('Proceed with the deployment?', true)) {
            $this->components->warn('Deployment cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $runManager->deploy(
                $prepared,
                requestedMode: $requestedMode,
                target: $this->option('target') !== null ? (string) $this->option('target') : null,
                createTag: (bool) $this->option('tag') || $this->option('tag-name') !== null,
                tagName: $this->option('tag-name') !== null ? (string) $this->option('tag-name') : null,
            );
        } catch (\Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        if ($result->deployment !== null) {
            $this->renderDeploymentResult($result->deployment);
        }

        return $result->status === ActionStatus::Failure ? self::FAILURE : self::SUCCESS;
    }
}
