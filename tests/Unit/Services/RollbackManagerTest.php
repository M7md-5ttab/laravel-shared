<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\Contracts\RollbackStrategyInterface;
use M7md5ttab\LaravelShared\DTOs\DeploymentManifest;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\RollbackResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Services\RollbackManager;
use M7md5ttab\LaravelShared\Support\HostingProviderDetector;
use M7md5ttab\LaravelShared\Support\PublicHtmlLocator;
use M7md5ttab\LaravelShared\Support\ProjectEnvironmentResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class RollbackManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_prefers_cleanup_strategy_for_snapshot_rollbacks_even_when_the_manifest_has_a_tag(): void
    {
        $container = new Container();
        $resolver = $this->resolver();
        $manifests = Mockery::mock(ManifestRepositoryInterface::class);
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $git = new GitService($runner);
        $gitStrategy = Mockery::mock(RollbackStrategyInterface::class);
        $cleanupStrategy = Mockery::mock(RollbackStrategyInterface::class);

        $manifest = new DeploymentManifest(
            snapshotName: 'hosting-deploy-2026-05-14-002',
            tagName: 'hosting-deploy-2026-05-14-002',
            mode: DeploymentMode::Copy,
            targetPath: '/home/example/public_html',
            backupPath: '/app/storage/app/hosting-shared/backups/hosting-deploy-2026-05-14-002',
            deployedAt: '2026-05-14T12:00:00+00:00',
            publicSourcePath: '/app/public',
            relativeBasePath: '../app',
            storageLinked: false,
        );

        $manifests->shouldReceive('findBySnapshot')->once()->with('hosting-deploy-2026-05-14-002')->andReturn($manifest);
        $gitStrategy->shouldReceive('name')->once()->andReturn('git');
        $gitStrategy->shouldReceive('supports')->never();
        $cleanupStrategy->shouldReceive('name')->once()->andReturn('cleanup');
        $cleanupStrategy->shouldReceive('supports')->once()->andReturn(true);
        $cleanupStrategy->shouldReceive('rollback')->once()->andReturn(
            new RollbackResult(ActionStatus::Success, 'cleanup', 'Removed package artifacts.'),
        );

        $container->instance('git-strategy', $gitStrategy);
        $container->instance('cleanup-strategy', $cleanupStrategy);

        $manager = new RollbackManager(
            $container,
            ['git-strategy', 'cleanup-strategy'],
            $resolver,
            $manifests,
            $git,
        );

        $result = $manager->rollback(snapshotName: 'hosting-deploy-2026-05-14-002');

        self::assertSame(ActionStatus::Success, $result->status);
        self::assertSame('cleanup', $result->strategy);
    }

    public function test_it_reports_dirty_tree_checks_for_cleanup_rollbacks(): void
    {
        $container = new Container();
        $resolver = $this->resolver();
        $manifests = Mockery::mock(ManifestRepositoryInterface::class);
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new \M7md5ttab\LaravelShared\DTOs\ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'rev-parse', '--is-inside-work-tree'] && $workingDirectory === '/app')
            ->andReturn(new \M7md5ttab\LaravelShared\DTOs\ProcessResult(['git', 'rev-parse'], 0, 'true', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'status', '--porcelain'] && $workingDirectory === '/app')
            ->andReturn(new \M7md5ttab\LaravelShared\DTOs\ProcessResult(['git', 'status'], 0, " M README.md\n", ''));
        $git = new GitService($runner);

        $manifests->shouldReceive('all')->once()->andReturn([
            new DeploymentManifest(
                snapshotName: 'hosting-deploy-2026-05-14-002',
                tagName: 'hosting-deploy-2026-05-14-002',
                mode: DeploymentMode::Copy,
                targetPath: '/home/example/public_html',
                backupPath: '/app/storage/app/hosting-shared/backups/hosting-deploy-2026-05-14-002',
                deployedAt: '2026-05-14T12:00:00+00:00',
                publicSourcePath: '/app/public',
                relativeBasePath: '../app',
                storageLinked: false,
            ),
        ]);
        $manager = new RollbackManager($container, [], $resolver, $manifests, $git);

        self::assertTrue($manager->hasDirtyWorkingTree());
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

    private function resolver(): ProjectEnvironmentResolver
    {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('basePath')->andReturn('/app');
        $app->shouldReceive('publicPath')->andReturn('/app/public');
        $app->shouldReceive('storagePath')->andReturn('/app/storage');
        $app->shouldReceive('version')->andReturn('Laravel Framework 11.9.0');

        $config = new \Illuminate\Config\Repository([
            'hosting-shared.provider_signatures' => [],
            'hosting-shared.provider_guidance.generic' => 'Use copy mode if symlinks are unavailable.',
            'hosting-shared.public_html_candidates' => [],
        ]);

        return new ProjectEnvironmentResolver(
            $app,
            new HostingProviderDetector($config),
            new PublicHtmlLocator(new Filesystem(), $config),
        );
    }
}
