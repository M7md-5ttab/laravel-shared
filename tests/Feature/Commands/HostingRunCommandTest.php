<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Feature\Commands;

use M7md5ttab\LaravelShared\DTOs\DeploymentSnapshot;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\DTOs\RunResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Services\RunManager;
use M7md5ttab\LaravelShared\Tests\TestCase;
use Mockery;

final class HostingRunCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_displays_dry_run_details_without_prompting(): void
    {
        $manager = Mockery::mock(RunManager::class);
        $manager->shouldReceive('prepare')->once()->andReturn(
            new RunResult(
                status: ActionStatus::Success,
                message: 'Dry run completed. No files were changed.',
                initialReport: $this->report(),
                finalReport: $this->report(),
                automaticFixes: [
                    new FixResult('Laravel .gitignore rules', ActionStatus::Skipped, '[dry-run] Would add any missing Laravel shared-hosting .gitignore rules.'),
                ],
                deployment: new PublishResult(
                    status: ActionStatus::Success,
                    mode: DeploymentMode::Copy,
                    targetPath: '/home/example/public_html',
                    message: 'Dry run completed. No files were changed.',
                    messages: ['[dry-run] Would synchronize public assets into the deployment target.'],
                    snapshot: new DeploymentSnapshot('hosting-deploy-2026-05-14-001', null, false, '2026-05-14T12:00:00+00:00'),
                    dryRun: true,
                ),
                targetPath: '/home/example/public_html',
                mode: DeploymentMode::Copy,
                dryRun: true,
                gitSnapshotStatus: 'A deployment snapshot commit will be created.',
            ),
        );
        $manager->shouldReceive('deploy')->never();

        $this->app->instance(RunManager::class, $manager);

        $this->artisan('hosting:run --copy --dry-run')
            ->expectsOutputToContain('Run Dry-Run Summary')
            ->expectsOutputToContain('Planned Automatic Fixes')
            ->expectsOutputToContain('/home/example/public_html')
            ->doesntExpectOutputToContain('Proceed with the deployment?')
            ->assertExitCode(0);
    }

    public function test_it_confirms_before_running_a_real_deployment(): void
    {
        $prepared = new RunResult(
            status: ActionStatus::Success,
            message: 'The project is ready for deployment.',
            initialReport: $this->report(),
            finalReport: $this->report(),
            automaticFixes: [
                new FixResult('.gitignore rules', ActionStatus::Success, 'Added missing rules.', true),
            ],
            targetPath: '/home/example/public_html',
            mode: DeploymentMode::Symlink,
            dryRun: false,
            gitSnapshotStatus: 'A deployment snapshot commit will be created.',
            tagStatus: 'A deployment tag will be created from the generated snapshot name.',
        );

        $completed = new RunResult(
            status: ActionStatus::Success,
            message: 'Application assets were prepared in symlink mode.',
            initialReport: $prepared->initialReport,
            finalReport: $prepared->finalReport,
            automaticFixes: $prepared->automaticFixes,
            deployment: new PublishResult(
                status: ActionStatus::Success,
                mode: DeploymentMode::Symlink,
                targetPath: '/home/example/public_html',
                message: 'Application assets were prepared in symlink mode.',
                messages: ['Created a storage symlink at [/home/example/public_html/storage].'],
                snapshot: new DeploymentSnapshot('hosting-deploy-2026-05-14-002', null, true, '2026-05-14T12:00:00+00:00'),
            ),
            targetPath: '/home/example/public_html',
            mode: DeploymentMode::Symlink,
            gitSnapshotStatus: $prepared->gitSnapshotStatus,
            tagStatus: $prepared->tagStatus,
        );

        $manager = Mockery::mock(RunManager::class);
        $manager->shouldReceive('prepare')->once()->andReturn($prepared);
        $manager->shouldReceive('deploy')->once()->andReturn($completed);

        $this->app->instance(RunManager::class, $manager);

        $this->artisan('hosting:run --symlink --tag')
            ->expectsConfirmation('Proceed with the deployment?', 'yes')
            ->expectsOutputToContain('Deployment Summary')
            ->expectsOutputToContain('Deployment tag')
            ->expectsOutputToContain('Application assets were prepared in symlink mode.')
            ->assertExitCode(0);
    }

    public function test_it_skips_confirmation_when_force_is_used(): void
    {
        $prepared = new RunResult(
            status: ActionStatus::Success,
            message: 'The project is ready for deployment.',
            initialReport: $this->report(),
            finalReport: $this->report(),
            targetPath: '/home/example/public_html',
            mode: DeploymentMode::Copy,
            gitSnapshotStatus: 'Git snapshot commit will be skipped because this project is not a git repository.',
        );

        $completed = new RunResult(
            status: ActionStatus::Success,
            message: 'Application assets were prepared in copy mode.',
            initialReport: $prepared->initialReport,
            finalReport: $prepared->finalReport,
            deployment: new PublishResult(
                status: ActionStatus::Success,
                mode: DeploymentMode::Copy,
                targetPath: '/home/example/public_html',
                message: 'Application assets were prepared in copy mode.',
            ),
            targetPath: '/home/example/public_html',
            mode: DeploymentMode::Copy,
            gitSnapshotStatus: $prepared->gitSnapshotStatus,
        );

        $manager = Mockery::mock(RunManager::class);
        $manager->shouldReceive('prepare')->once()->andReturn($prepared);
        $manager->shouldReceive('deploy')->once()->andReturn($completed);

        $this->app->instance(RunManager::class, $manager);

        $this->artisan('hosting:run --copy --force')
            ->doesntExpectOutputToContain('Proceed with the deployment?')
            ->expectsOutputToContain('Application assets were prepared in copy mode.')
            ->assertExitCode(0);
    }

    private function report(): ReadinessReport
    {
        return new ReadinessReport(
            new EnvironmentContext(
                basePath: '/app',
                publicPath: '/app/public',
                storagePath: '/app/storage',
                bootstrapCachePath: '/app/bootstrap/cache',
                phpVersion: '8.2.12',
                laravelVersion: '11.9.0',
                operatingSystem: 'Linux',
                hostingProvider: HostingProvider::Generic,
                publicHtmlPath: '/home/example/public_html',
            ),
            [
                new \M7md5ttab\LaravelShared\DTOs\CheckResult('git-installed', 'Git installed', CheckStatus::Passed, 'Git is available.'),
                new \M7md5ttab\LaravelShared\DTOs\CheckResult('gitignore', 'Laravel .gitignore rules', CheckStatus::Passed, 'All expected rules are present.', blocking: false),
            ],
        );
    }
}
