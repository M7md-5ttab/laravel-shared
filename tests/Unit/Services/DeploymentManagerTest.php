<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\Contracts\PublisherInterface;
use M7md5ttab\LaravelShared\DTOs\DeploymentSnapshot;
use M7md5ttab\LaravelShared\DTOs\PublishContext;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Exceptions\HostingException;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Services\DeploymentManager;
use M7md5ttab\LaravelShared\Support\DeploymentNameGenerator;
use M7md5ttab\LaravelShared\Support\HostingProviderDetector;
use M7md5ttab\LaravelShared\Support\PublicHtmlLocator;
use M7md5ttab\LaravelShared\Support\ProjectEnvironmentResolver;
use M7md5ttab\LaravelShared\Support\SymlinkSupportDetector;
use Mockery;
use PHPUnit\Framework\TestCase;

final class DeploymentManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_falls_back_to_copy_mode_when_symlink_publishing_throws(): void
    {
        $workspace = sys_get_temp_dir() . '/laravel-shared-deployment-' . uniqid('', true);
        $files = new Filesystem();
        $files->ensureDirectoryExists($workspace . '/app/public');
        $files->ensureDirectoryExists($workspace . '/app/storage/framework');
        $files->ensureDirectoryExists($workspace . '/app/storage/app/public');
        $files->ensureDirectoryExists($workspace . '/app/vendor');
        $files->ensureDirectoryExists($workspace . '/app/bootstrap');
        $files->ensureDirectoryExists($workspace . '/target');
        $files->put($workspace . '/app/public/index.php', <<<'PHP'
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
PHP);
        $files->put($workspace . '/app/public/robots.txt', "User-agent: *\n");
        $files->put($workspace . '/app/vendor/autoload.php', '<?php');
        $files->put($workspace . '/app/bootstrap/app.php', '<?php');

        $container = new Container();
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('basePath')->andReturn($workspace . '/app');
        $app->shouldReceive('publicPath')->andReturn($workspace . '/app/public');
        $app->shouldReceive('storagePath')->andReturn($workspace . '/app/storage');
        $app->shouldReceive('version')->andReturn('Laravel Framework 11.9.0');

        $config = new \Illuminate\Config\Repository([
            'hosting-shared.provider_signatures' => [],
            'hosting-shared.provider_guidance.generic' => 'Use copy mode if symlinks are unavailable.',
            'hosting-shared.public_html_candidates' => [],
            'hosting-shared.storage_public_directory' => 'storage/app/public',
            'hosting-shared.deployment.manifest_directory' => 'app/hosting-shared/manifests',
            'hosting-shared.deployment.backup_directory' => 'app/hosting-shared/backups',
            'hosting-shared.deployment.log_file' => 'logs/hosting-shared.log',
            'hosting-shared.deployment.tag_prefix' => 'hosting-deploy',
            'hosting-shared.deployment.preserve_paths' => [],
        ]);

        $symlinkPublisher = Mockery::mock(PublisherInterface::class);
        $symlinkPublisher->shouldReceive('publish')->once()->andThrow(new \RuntimeException('Symlinks are blocked.'));

        $copyPublisher = Mockery::mock(PublisherInterface::class);
        $copyPublisher->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(static fn (PublishContext $context): bool => $context->mode === DeploymentMode::Copy))
            ->andReturn(new PublishResult(
                status: ActionStatus::Success,
                mode: DeploymentMode::Copy,
                targetPath: $workspace . '/target',
                message: 'Application assets were prepared in copy mode.',
                messages: ['Copied files into the deployment target.'],
                snapshot: new DeploymentSnapshot('hosting-deploy-2026-05-14-001', null, false, '2026-05-14T12:00:00+00:00'),
            ));

        $manifests = Mockery::mock(ManifestRepositoryInterface::class);
        $manifests->shouldReceive('all')->zeroOrMoreTimes()->andReturn([]);
        $manifests->shouldReceive('save')->once()->andReturn($workspace . '/app/storage/app/hosting-shared/manifests/hosting-deploy-2026-05-14-001.json');

        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->zeroOrMoreTimes()
            ->withArgs(static fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new \M7md5ttab\LaravelShared\DTOs\ProcessResult(['git', '--version'], 1, '', 'git missing'));

        $container->instance(Application::class, $app);
        $container->instance(Filesystem::class, $files);
        $container->instance('config', $config);
        $container->instance('symlink-publisher', $symlinkPublisher);
        $container->instance('copy-publisher', $copyPublisher);

        $manager = new DeploymentManager(
            $container,
            [
                'copy' => 'copy-publisher',
                'symlink' => 'symlink-publisher',
            ],
            new ProjectEnvironmentResolver(
                $app,
                new HostingProviderDetector($config),
                new PublicHtmlLocator($files, $config),
            ),
            new PublicHtmlLocator($files, $config),
            new SymlinkSupportDetector($files),
            new DeploymentNameGenerator($config, $manifests, new GitService($runner)),
            $manifests,
            new GitService($runner),
        );

        $result = $manager->publish(target: $workspace . '/target');

        self::assertSame(ActionStatus::Success, $result->status);
        self::assertSame(DeploymentMode::Copy, $result->mode);
        self::assertSame('Application assets were prepared in copy mode.', $result->message);
        self::assertContains('Symlink mode was not completed automatically. Falling back to copy mode.', $result->messages);
    }

    public function test_it_rejects_explicit_targets_when_the_parent_directory_is_missing(): void
    {
        $workspace = sys_get_temp_dir() . '/laravel-shared-deployment-' . uniqid('', true);
        $files = new Filesystem();
        $files->ensureDirectoryExists($workspace . '/app/public');
        $files->ensureDirectoryExists($workspace . '/app/storage/framework');
        $files->ensureDirectoryExists($workspace . '/app/storage/app/public');
        $files->ensureDirectoryExists($workspace . '/app/vendor');
        $files->ensureDirectoryExists($workspace . '/app/bootstrap');
        $files->put($workspace . '/app/public/index.php', <<<'PHP'
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
PHP);
        $files->put($workspace . '/app/vendor/autoload.php', '<?php');
        $files->put($workspace . '/app/bootstrap/app.php', '<?php');

        $container = new Container();
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('basePath')->andReturn($workspace . '/app');
        $app->shouldReceive('publicPath')->andReturn($workspace . '/app/public');
        $app->shouldReceive('storagePath')->andReturn($workspace . '/app/storage');
        $app->shouldReceive('version')->andReturn('Laravel Framework 11.9.0');

        $config = new \Illuminate\Config\Repository([
            'hosting-shared.provider_signatures' => [],
            'hosting-shared.provider_guidance.generic' => 'Use copy mode if symlinks are unavailable.',
            'hosting-shared.public_html_candidates' => [],
            'hosting-shared.storage_public_directory' => 'storage/app/public',
            'hosting-shared.deployment.manifest_directory' => 'app/hosting-shared/manifests',
            'hosting-shared.deployment.backup_directory' => 'app/hosting-shared/backups',
            'hosting-shared.deployment.log_file' => 'logs/hosting-shared.log',
            'hosting-shared.deployment.tag_prefix' => 'hosting-deploy',
            'hosting-shared.deployment.preserve_paths' => [],
        ]);

        $manifests = Mockery::mock(ManifestRepositoryInterface::class);
        $manifests->shouldReceive('all')->zeroOrMoreTimes()->andReturn([]);

        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->zeroOrMoreTimes()
            ->withArgs(static fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new \M7md5ttab\LaravelShared\DTOs\ProcessResult(['git', '--version'], 1, '', 'git missing'));

        $container->instance(Application::class, $app);
        $container->instance(Filesystem::class, $files);
        $container->instance('config', $config);

        $manager = new DeploymentManager(
            $container,
            [],
            new ProjectEnvironmentResolver(
                $app,
                new HostingProviderDetector($config),
                new PublicHtmlLocator($files, $config),
            ),
            new PublicHtmlLocator($files, $config),
            new SymlinkSupportDetector($files),
            new DeploymentNameGenerator($config, $manifests, new GitService($runner)),
            $manifests,
            new GitService($runner),
        );

        $this->expectException(HostingException::class);
        $this->expectExceptionMessage('The parent directory');

        $manager->publish(target: $workspace . '/missing-parent/public_html');
    }
}
