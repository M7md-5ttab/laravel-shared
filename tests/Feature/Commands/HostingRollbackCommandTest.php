<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Feature\Commands;

use M7md5ttab\LaravelShared\DTOs\DeploymentManifest;
use M7md5ttab\LaravelShared\DTOs\RollbackResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Services\RollbackManager;
use M7md5ttab\LaravelShared\Tests\TestCase;
use Mockery;

final class HostingRollbackCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_runs_a_confirmed_rollback(): void
    {
        $manager = Mockery::mock(RollbackManager::class);
        $manager->shouldReceive('manifests')->once()->andReturn([
            new DeploymentManifest(
                snapshotName: 'hosting-deploy-2026-05-14-001',
                tagName: 'hosting-deploy-2026-05-14-001',
                mode: DeploymentMode::Copy,
                targetPath: '/home/example/public_html',
                backupPath: '/app/storage/app/hosting-shared/backups/hosting-deploy-2026-05-14-001',
                deployedAt: '2026-05-14T12:00:00+00:00',
                publicSourcePath: '/app/public',
                relativeBasePath: '../app',
                storageLinked: false,
            ),
        ]);
        $manager->shouldReceive('gitTags')->once()->andReturn(['hosting-deploy-2026-05-14-001']);
        $manager->shouldReceive('hasDirtyWorkingTree')->once()->andReturn(false);
        $manager->shouldReceive('rollback')->once()->andReturn(
            new RollbackResult(
                status: ActionStatus::Success,
                strategy: 'cleanup',
                message: 'Removed the deployment artifacts for snapshot [hosting-deploy-2026-05-14-001] and cleaned package-managed changes.',
            ),
        );

        $this->app->instance(RollbackManager::class, $manager);

        $this->artisan('hosting:rollback')
            ->expectsConfirmation('Proceed with the rollback request?', 'yes')
            ->expectsOutputToContain('Rollback History')
            ->expectsOutputToContain('Removed the deployment artifacts for snapshot [hosting-deploy-2026-05-14-001] and cleaned package-managed changes.')
            ->assertExitCode(0);
    }
}
