<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Services;

use Illuminate\Contracts\Container\Container;
use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;
use M7md5ttab\LaravelShared\Git\GitService;

class FixManager
{
    /**
     * @param  array<int, class-string<FixerInterface>>  $fixerClasses
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $fixerClasses,
        private readonly CheckPipeline $checkPipeline,
        private readonly GitService $git,
    ) {
    }

    /**
     * @param  null|callable(string): bool  $confirm
     * @return array<int, FixResult>
     */
    public function applyAutomaticFixes(ReadinessReport $report, bool $interactive = false, ?callable $confirm = null): array
    {
        $results = [];
        $repositoryInitialized = false;

        foreach ($report->results as $checkResult) {
            if (! $checkResult->needsAttention()) {
                continue;
            }

            $fixer = $this->findFixer($checkResult, FixerAutomation::Automatic);

            if ($fixer === null) {
                continue;
            }

            if ($interactive) {
                $shouldRun = $confirm !== null
                    ? $confirm($fixer->description($checkResult, $report->environment))
                    : false;

                if (! $shouldRun) {
                    $results[] = new FixResult(
                        name: $checkResult->name,
                        status: ActionStatus::Skipped,
                        message: 'Skipped by user.',
                    );

                    continue;
                }
            }

            $result = $fixer->fix($checkResult, $report->environment);
            $results[] = $result;

            if ($checkResult->key === 'git-repository' && $result->performed) {
                $repositoryInitialized = true;
            }
        }

        $snapshotResult = $this->createInitialCommitIfNeeded($report, $repositoryInitialized);

        if ($snapshotResult !== null) {
            $results[] = $snapshotResult;
        }

        return $results;
    }

    /**
     * @return array<int, FixResult>
     */
    public function previewAutomaticFixes(ReadinessReport $report): array
    {
        $results = [];
        $repositoryWillBeInitialized = false;

        foreach ($report->results as $checkResult) {
            if (! $checkResult->needsAttention()) {
                continue;
            }

            $fixer = $this->findFixer($checkResult, FixerAutomation::Automatic);

            if ($fixer === null) {
                continue;
            }

            $results[] = new FixResult(
                name: $checkResult->name,
                status: ActionStatus::Skipped,
                message: $this->previewMessage($fixer->description($checkResult, $report->environment)),
            );

            if ($checkResult->key === 'git-repository') {
                $repositoryWillBeInitialized = true;
            }
        }

        if ($repositoryWillBeInitialized && $this->git->isInstalled()) {
            $results[] = new FixResult(
                name: 'Initial git snapshot',
                status: ActionStatus::Skipped,
                message: '[dry-run] Would create the initial git snapshot commit after automatic fixes.',
            );
        }

        return $results;
    }

    /**
     * @return array<int, FixResult>
     */
    public function collectAdvisories(ReadinessReport $report): array
    {
        $results = [];

        foreach ($report->results as $checkResult) {
            if (! $checkResult->needsAttention()) {
                continue;
            }

            $fixer = $this->findFixer($checkResult, FixerAutomation::Advisory);

            if ($fixer === null) {
                continue;
            }

            $results[] = $fixer->fix($checkResult, $report->environment);
        }

        return $results;
    }

    public function rerunChecks(): ReadinessReport
    {
        return $this->checkPipeline->run();
    }

    private function findFixer(CheckResult $checkResult, ?FixerAutomation $automation = null): ?FixerInterface
    {
        foreach ($this->fixerClasses as $fixerClass) {
            /** @var FixerInterface $fixer */
            $fixer = $this->container->make($fixerClass);

            if (! $fixer->supports($checkResult)) {
                continue;
            }

            if ($automation !== null && $fixer->automation() !== $automation) {
                continue;
            }

            return $fixer;
        }

        return null;
    }

    private function createInitialCommitIfNeeded(ReadinessReport $report, bool $repositoryInitialized): ?FixResult
    {
        if (! $repositoryInitialized) {
            return null;
        }

        $basePath = $report->environment->basePath;

        if (! $this->git->isInstalled() || ! $this->git->isRepository($basePath) || $this->git->hasCommits($basePath)) {
            return null;
        }

        try {
            $this->git->createSnapshotCommit($basePath, 'chore: initial hosting snapshot');
            return new FixResult(
                name: 'Initial git snapshot',
                status: ActionStatus::Success,
                message: 'Created the initial git snapshot commit after applying safe fixes.',
                performed: true,
            );
        } catch (\Throwable $throwable) {
            return new FixResult(
                name: 'Initial git snapshot',
                status: ActionStatus::Warning,
                message: 'Repository was initialized, but the first commit could not be created automatically.',
                nextStep: $throwable->getMessage(),
            );
        }
    }

    private function previewMessage(string $description): string
    {
        $normalized = rtrim(trim($description), ' ?');

        return '[dry-run] Would ' . lcfirst($normalized) . '.';
    }
}
