<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Services;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\DTOs\ProcessResult;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Services\CheckPipeline;
use M7md5ttab\LaravelShared\Services\DeploymentManager;
use M7md5ttab\LaravelShared\Services\FixManager;
use M7md5ttab\LaravelShared\Services\RunManager;
use M7md5ttab\LaravelShared\Support\PublicHtmlLocator;
use Mockery;
use PHPUnit\Framework\TestCase;

final class RunManagerTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $workspaces = [];

    protected function tearDown(): void
    {
        $files = new Filesystem();

        foreach ($this->workspaces as $workspace) {
            $files->deleteDirectory($workspace);
        }

        Mockery::close();

        parent::tearDown();
    }

    public function test_dry_runs_preview_automatic_fixes_without_applying_mutating_fixes(): void
    {
        ['basePath' => $basePath, 'publicHtmlPath' => $publicHtmlPath] = $this->workspace(withPublicHtml: true);

        $pipeline = Mockery::mock(CheckPipeline::class);
        $pipeline->shouldReceive('run')->once()->andReturn($this->report(publicHtmlPath: $publicHtmlPath, basePath: $basePath));

        $fixManager = Mockery::mock(FixManager::class);
        $fixManager->shouldReceive('previewAutomaticFixes')->once()->andReturn([
            new FixResult('.gitignore rules', ActionStatus::Skipped, '[dry-run] Would add missing rules.'),
        ]);
        $fixManager->shouldReceive('applyAutomaticFixes')->never();
        $fixManager->shouldReceive('rerunChecks')->never();

        $deploymentManager = Mockery::mock(DeploymentManager::class);
        $deploymentManager->shouldReceive('publish')
            ->once()
            ->withArgs(static fn (
                ?DeploymentMode $requestedMode,
                ?string $target,
                bool $dryRun,
                bool $createTag = false,
                ?string $tagName = null
            ): bool => $requestedMode === DeploymentMode::Copy
                && $target === null
                && $dryRun
                && $createTag === false
                && $tagName === null)
            ->andReturn(new PublishResult(
                status: ActionStatus::Success,
                mode: DeploymentMode::Copy,
                targetPath: $publicHtmlPath,
                message: 'Dry run completed. No files were changed.',
                dryRun: true,
            ));

        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->twice()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 1, '', 'git missing'));

        $manager = new RunManager(
            $pipeline,
            $fixManager,
            $deploymentManager,
            new PublicHtmlLocator(new Filesystem(), new Repository(['hosting-shared.public_html_candidates' => []])),
            new Filesystem(),
            new GitService($runner),
        );

        $result = $manager->prepare(requestedMode: DeploymentMode::Copy, dryRun: true);

        self::assertTrue($result->dryRun);
        self::assertSame(ActionStatus::Success, $result->status);
        self::assertCount(1, $result->automaticFixes);
        self::assertNotNull($result->deployment);
    }

    public function test_it_fails_early_when_tags_are_requested_without_git(): void
    {
        $pipeline = Mockery::mock(CheckPipeline::class);
        $pipeline->shouldReceive('run')->once()->andReturn($this->report());

        $fixManager = Mockery::mock(FixManager::class);
        $fixManager->shouldReceive('applyAutomaticFixes')->once()->andReturn([]);
        $fixManager->shouldReceive('rerunChecks')->once()->andReturn($this->report());

        $deploymentManager = Mockery::mock(DeploymentManager::class);
        $deploymentManager->shouldReceive('publish')->never();

        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->times(3)
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 1, '', 'git missing'));

        $manager = new RunManager(
            $pipeline,
            $fixManager,
            $deploymentManager,
            new PublicHtmlLocator(new Filesystem(), new Repository(['hosting-shared.public_html_candidates' => []])),
            new Filesystem(),
            new GitService($runner),
        );

        $result = $manager->prepare(createTag: true);

        self::assertSame(ActionStatus::Failure, $result->status);
        self::assertSame(
            'Git is required when --tag or --tag-name is used. Install Git or rerun without deployment tags.',
            $result->message,
        );
    }

    public function test_it_uses_the_default_public_html_target_when_none_is_detected(): void
    {
        ['basePath' => $basePath, 'publicHtmlPath' => $publicHtmlPath] = $this->workspace(withPublicHtml: false);

        $pipeline = Mockery::mock(CheckPipeline::class);
        $pipeline->shouldReceive('run')->once()->andReturn($this->report(publicHtmlPath: null, basePath: $basePath));

        $fixManager = Mockery::mock(FixManager::class);
        $fixManager->shouldReceive('previewAutomaticFixes')->once()->andReturn([]);
        $fixManager->shouldReceive('applyAutomaticFixes')->never();
        $fixManager->shouldReceive('rerunChecks')->never();

        $deploymentManager = Mockery::mock(DeploymentManager::class);
        $deploymentManager->shouldReceive('publish')
            ->once()
            ->andReturn(new PublishResult(
                status: ActionStatus::Success,
                mode: DeploymentMode::Copy,
                targetPath: $publicHtmlPath,
                message: 'Dry run completed. No files were changed.',
                dryRun: true,
            ));

        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->twice()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 1, '', 'git missing'));

        $manager = new RunManager(
            $pipeline,
            $fixManager,
            $deploymentManager,
            new PublicHtmlLocator(new Filesystem(), new Repository(['hosting-shared.public_html_candidates' => []])),
            new Filesystem(),
            new GitService($runner),
        );

        $result = $manager->prepare(requestedMode: DeploymentMode::Copy, dryRun: true);

        self::assertSame(ActionStatus::Success, $result->status);
        self::assertSame($publicHtmlPath, $result->targetPath);
    }

    /**
     * @return array{basePath: string, publicHtmlPath: string}
     */
    private function workspace(bool $withPublicHtml): array
    {
        $workspace = sys_get_temp_dir() . '/laravel-shared-run-manager-' . uniqid('', true);
        $this->workspaces[] = $workspace;

        $files = new Filesystem();
        $files->ensureDirectoryExists($workspace . '/app');

        if ($withPublicHtml) {
            $files->ensureDirectoryExists($workspace . '/public_html');
        }

        return [
            'basePath' => $workspace . '/app',
            'publicHtmlPath' => $workspace . '/public_html',
        ];
    }

    private function report(?string $publicHtmlPath = '/home/example/public_html', string $basePath = '/app'): ReadinessReport
    {
        return new ReadinessReport(
            new EnvironmentContext(
                basePath: $basePath,
                publicPath: $basePath . '/public',
                storagePath: $basePath . '/storage',
                bootstrapCachePath: $basePath . '/bootstrap/cache',
                phpVersion: '8.2.12',
                laravelVersion: '11.9.0',
                operatingSystem: 'Linux',
                hostingProvider: HostingProvider::Generic,
                publicHtmlPath: $publicHtmlPath,
            ),
            [
                new CheckResult('git-installed', 'Git installed', CheckStatus::Passed, 'Git is available.'),
                new CheckResult('symlink-support', 'Symlink support', CheckStatus::Warning, 'Symlink support is unavailable.', blocking: false),
                new CheckResult(
                    'public-html',
                    'public_html directory',
                    $publicHtmlPath === null ? CheckStatus::Warning : CheckStatus::Passed,
                    $publicHtmlPath === null ? 'No public_html directory was auto-detected near the application.' : 'Detected target directory.',
                    blocking: false,
                ),
            ],
        );
    }
}
