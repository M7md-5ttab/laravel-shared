<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Services;

use Illuminate\Container\Container;
use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\DTOs\ProcessResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Services\CheckPipeline;
use M7md5ttab\LaravelShared\Services\FixManager;
use Mockery;
use PHPUnit\Framework\TestCase;

final class FixManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_applies_only_automatic_fixers_and_collects_advisories_separately(): void
    {
        $container = new Container();
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $git = new GitService($runner);
        $pipeline = Mockery::mock(CheckPipeline::class);

        $manager = new FixManager(
            $container,
            [
                AutomaticTestFixer::class,
                AdvisoryTestFixer::class,
            ],
            $pipeline,
            $git,
        );

        $report = new ReadinessReport($this->environment(), [
            new CheckResult('gitignore', 'Laravel .gitignore rules', CheckStatus::Warning, 'Rules are missing.', blocking: false),
            new CheckResult('git-installed', 'Git installed', CheckStatus::Failed, 'Git is missing.'),
        ]);

        $automaticResults = $manager->applyAutomaticFixes($report);
        $advisories = $manager->collectAdvisories($report);

        self::assertCount(1, $automaticResults);
        self::assertSame('Automatic fixer applied.', $automaticResults[0]->message);
        self::assertCount(1, $advisories);
        self::assertSame('Manual Git installation is required.', $advisories[0]->message);
    }

    public function test_it_previews_the_initial_snapshot_when_git_repository_setup_would_run(): void
    {
        $container = new Container();
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));

        $manager = new FixManager(
            $container,
            [RepositoryBootstrapFixer::class],
            Mockery::mock(CheckPipeline::class),
            new GitService($runner),
        );

        $report = new ReadinessReport($this->environment(), [
            new CheckResult('git-repository', 'Git repository detected', CheckStatus::Warning, 'Repository is missing.', blocking: false),
        ]);

        $results = $manager->previewAutomaticFixes($report);

        self::assertCount(2, $results);
        self::assertSame('[dry-run] Would initialize a git repository in the Laravel application root.', $results[0]->message);
        self::assertSame('[dry-run] Would create the initial git snapshot commit after automatic fixes.', $results[1]->message);
    }

    private function environment(): EnvironmentContext
    {
        return new EnvironmentContext(
            basePath: '/app',
            publicPath: '/app/public',
            storagePath: '/app/storage',
            bootstrapCachePath: '/app/bootstrap/cache',
            phpVersion: '8.2.12',
            laravelVersion: '11.9.0',
            operatingSystem: 'Linux',
            hostingProvider: HostingProvider::Generic,
            publicHtmlPath: '/home/example/public_html',
        );
    }
}

final class AutomaticTestFixer implements FixerInterface
{
    public function supports(CheckResult $result): bool
    {
        return $result->key === 'gitignore';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Automatic;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Add any missing Laravel shared-hosting .gitignore rules?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        return new FixResult('.gitignore rules', ActionStatus::Success, 'Automatic fixer applied.', true);
    }
}

final class AdvisoryTestFixer implements FixerInterface
{
    public function supports(CheckResult $result): bool
    {
        return $result->key === 'git-installed';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Advisory;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Show the safest Git installation command for this operating system?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        return new FixResult('Git installation', ActionStatus::Warning, 'Manual Git installation is required.');
    }
}

final class RepositoryBootstrapFixer implements FixerInterface
{
    public function supports(CheckResult $result): bool
    {
        return $result->key === 'git-repository';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Automatic;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Initialize a git repository in the Laravel application root?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        return new FixResult('Git repository initialization', ActionStatus::Success, 'Initialized the repository.', true);
    }
}
