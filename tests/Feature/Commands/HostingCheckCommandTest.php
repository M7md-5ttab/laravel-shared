<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Feature\Commands;

use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Services\CheckPipeline;
use M7md5ttab\LaravelShared\Tests\TestCase;
use Mockery;

final class HostingCheckCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_renders_a_readiness_report(): void
    {
        $report = new ReadinessReport(
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
                new CheckResult('git-installed', 'Git installed', CheckStatus::Passed, 'Git is available.'),
                new CheckResult('git-remote', 'Git remote detected', CheckStatus::Info, 'No remote repository is configured.'),
            ],
        );

        $pipeline = Mockery::mock(CheckPipeline::class);
        $pipeline->shouldReceive('count')->once()->andReturn(2);
        $pipeline->shouldReceive('run')->once()->andReturnUsing(function (?callable $progress) use ($report): ReadinessReport {
            if ($progress !== null) {
                $progress(1);
                $progress(2);
            }

            return $report;
        });

        $this->app->instance(CheckPipeline::class, $pipeline);

        $this->artisan('hosting:check')
            ->expectsOutputToContain('Deployment Readiness Report')
            ->expectsOutputToContain('Git installed')
            ->expectsOutputToContain('Readiness Score: 100%')
            ->assertExitCode(0);
    }
}
