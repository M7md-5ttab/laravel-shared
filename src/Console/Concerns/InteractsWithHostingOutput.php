<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Console\Concerns;

use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\DTOs\RollbackResult;
use M7md5ttab\LaravelShared\DTOs\RunResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use function Laravel\Prompts\confirm;

trait InteractsWithHostingOutput
{
    protected function confirmWithChoice(string $question, bool $default = true): bool
    {
        return confirm(
            label: $question,
            default: $default,
        );
    }

    protected function renderReadinessReport(ReadinessReport $report, string $title = 'Deployment Readiness Report'): void
    {
        $this->newLine();
        $this->components->info($title);

        foreach ($report->results as $result) {
            $this->line(sprintf(
                '<fg=%s>%s</> <options=bold>%s</> - %s',
                $result->status->color(),
                $result->status->icon(),
                $result->name,
                $result->message,
            ));

            foreach ($result->details as $detail) {
                $this->line("   <fg={$result->status->color()}>•</> {$detail}");
            }

            if ($result->recommendedFix !== null && $result->recommendedFix !== '') {
                $label = $result->status->guidanceLabel();
                $this->line("   <fg=yellow>{$label}:</> {$result->recommendedFix}");
            }
        }

        $this->newLine();
        $this->line('<options=bold>Readiness Score:</> ' . $report->score() . '%');
    }

    /**
     * @param  array<int, FixResult>  $results
     */
    protected function renderFixResults(array $results, string $title = 'Applied Fixes'): void
    {
        $this->newLine();
        $this->components->info($title);

        foreach ($results as $result) {
            $this->renderActionLine($result->status, $result->name, $result->message, $result->nextStep);
        }
    }

    protected function renderDeploymentResult(PublishResult $result): void
    {
        $this->newLine();
        $this->renderActionLine($result->status, 'Deployment', $result->message);
        $this->line("Mode: <options=bold>{$result->mode->label()}</>");
        $this->line("Target: <options=bold>{$result->targetPath}</>");

        if ($result->snapshot !== null) {
            $this->line("Snapshot: <options=bold>{$result->snapshot->name}</>");
        }

        if ($result->manifestPath !== null) {
            $this->line("Manifest: <options=bold>{$result->manifestPath}</>");
        }

        foreach ($result->messages as $message) {
            $this->line(" - {$message}");
        }
    }

    protected function renderRunSummary(RunResult $result): void
    {
        $this->newLine();
        $this->components->info($result->dryRun ? 'Run Dry-Run Summary' : 'Deployment Summary');

        if ($result->targetPath !== null) {
            $this->line("Target: <options=bold>{$result->targetPath}</>");
        }

        if ($result->mode !== null) {
            $this->line("Mode: <options=bold>{$result->mode->label()}</>");
        }

        $this->line('Automatic fixes: <options=bold>' . count($result->automaticFixes) . '</>');
        $this->line('Warnings remaining: <options=bold>' . count($result->finalReport->warnings()) . '</>');
        $this->line("Git snapshot: <options=bold>{$result->gitSnapshotStatus}</>");

        if ($result->tagStatus !== null) {
            $this->line("Deployment tag: <options=bold>{$result->tagStatus}</>");
        }
    }

    protected function renderRollbackResult(RollbackResult $result): void
    {
        $this->newLine();
        $this->renderActionLine($result->status, 'Rollback', $result->message);

        foreach ($result->messages as $message) {
            $this->line(" - {$message}");
        }
    }

    /**
     * @param  array<int, \M7md5ttab\LaravelShared\DTOs\DeploymentManifest>  $manifests
     * @param  array<int, string>  $tags
     */
    protected function renderRollbackHistory(array $manifests, array $tags): void
    {
        $this->components->info('Rollback History');

        if ($manifests === [] && $tags === []) {
            $this->line('No deployment snapshots or git tags were found yet.');

            return;
        }

        foreach ($manifests as $manifest) {
            $this->line(" - Snapshot {$manifest->snapshotName} ({$manifest->mode->value}) deployed at {$manifest->deployedAt}");
        }

        foreach ($tags as $tag) {
            $this->line(" - Git tag {$tag}");
        }
    }

    protected function renderActionLine(ActionStatus $status, string $title, string $message, ?string $nextStep = null): void
    {
        $this->line(sprintf(
            '<fg=%s>%s</> <options=bold>%s</> - %s',
            $status->color(),
            $status->icon(),
            $title,
            $message,
        ));

        if ($nextStep !== null && $nextStep !== '') {
            $this->line("   <fg=yellow>Next:</> {$nextStep}");
        }
    }
}
