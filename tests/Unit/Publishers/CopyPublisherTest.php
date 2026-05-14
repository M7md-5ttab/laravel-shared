<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Publishers;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\DTOs\DeploymentSnapshot;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\PublishContext;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Publishers\CopyPublisher;
use M7md5ttab\LaravelShared\Support\IndexPhpPatcher;
use PHPUnit\Framework\TestCase;

final class CopyPublisherTest extends TestCase
{
    private Filesystem $files;

    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->workspace = sys_get_temp_dir() . '/laravel-shared-copy-' . uniqid('', true);
        $this->files->ensureDirectoryExists($this->workspace . '/app/public');
        $this->files->ensureDirectoryExists($this->workspace . '/app/storage/app/public/images');
        $this->files->ensureDirectoryExists($this->workspace . '/target');

        $this->files->put($this->workspace . '/app/public/index.php', <<<'PHP'
<?php
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
PHP);
        $this->files->put($this->workspace . '/app/public/.htaccess', 'RewriteEngine On');
        $this->files->put($this->workspace . '/app/public/app.css', 'body { color: #111; }');
        $this->files->put($this->workspace . '/app/storage/app/public/images/logo.png', 'binary');
        $this->files->ensureDirectoryExists($this->workspace . '/app/vendor');
        $this->files->put($this->workspace . '/app/vendor/autoload.php', '<?php');
        $this->files->ensureDirectoryExists($this->workspace . '/app/bootstrap');
        $this->files->put($this->workspace . '/app/bootstrap/app.php', '<?php');
        $this->files->ensureDirectoryExists($this->workspace . '/app/storage/framework');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_copies_public_and_storage_assets(): void
    {
        $this->files->put($this->workspace . '/target/stale.txt', 'remove me');
        $this->files->link($this->workspace . '/app/storage/app/public', $this->workspace . '/target/storage');

        $publisher = new CopyPublisher(
            $this->files,
            new IndexPhpPatcher($this->files),
            new Repository([
                'hosting-shared.storage_public_directory' => 'storage/app/public',
                'hosting-shared.deployment.preserve_paths' => [],
            ]),
        );

        $result = $publisher->publish(new PublishContext(
            environment: new EnvironmentContext(
                basePath: $this->workspace . '/app',
                publicPath: $this->workspace . '/app/public',
                storagePath: $this->workspace . '/app/storage',
                bootstrapCachePath: $this->workspace . '/app/bootstrap/cache',
                phpVersion: '8.2.12',
                laravelVersion: '11.9.0',
                operatingSystem: 'Linux',
                hostingProvider: HostingProvider::Generic,
                publicHtmlPath: $this->workspace . '/target',
            ),
            mode: DeploymentMode::Copy,
            targetPath: $this->workspace . '/target',
            backupPath: $this->workspace . '/backup',
            relativeBasePath: '../app',
            snapshot: new DeploymentSnapshot('hosting-deploy-2026-05-14-001', null, false, '2026-05-14T12:00:00+00:00'),
            dryRun: false,
        ));

        self::assertSame(ActionStatus::Success, $result->status);
        self::assertFileExists($this->workspace . '/target/.htaccess');
        self::assertFileExists($this->workspace . '/target/app.css');
        self::assertFileExists($this->workspace . '/target/storage/images/logo.png');
        self::assertFileDoesNotExist($this->workspace . '/target/stale.txt');
        self::assertFalse(is_link($this->workspace . '/target/storage'));
        self::assertStringContainsString(
            "__DIR__.'/../app/storage/framework/maintenance.php'",
            $this->files->get($this->workspace . '/target/index.php'),
        );
        self::assertStringContainsString(
            "__DIR__.'/../app/vendor/autoload.php'",
            $this->files->get($this->workspace . '/target/index.php'),
        );
    }

    public function test_it_keeps_dry_runs_free_of_false_verification_warnings(): void
    {
        $publisher = new CopyPublisher(
            $this->files,
            new IndexPhpPatcher($this->files),
            new Repository([
                'hosting-shared.storage_public_directory' => 'storage/app/public',
                'hosting-shared.deployment.preserve_paths' => [],
            ]),
        );

        $result = $publisher->publish(new PublishContext(
            environment: new EnvironmentContext(
                basePath: $this->workspace . '/app',
                publicPath: $this->workspace . '/app/public',
                storagePath: $this->workspace . '/app/storage',
                bootstrapCachePath: $this->workspace . '/app/bootstrap/cache',
                phpVersion: '8.2.12',
                laravelVersion: '11.9.0',
                operatingSystem: 'Linux',
                hostingProvider: HostingProvider::Generic,
                publicHtmlPath: $this->workspace . '/target',
            ),
            mode: DeploymentMode::Copy,
            targetPath: $this->workspace . '/target',
            backupPath: $this->workspace . '/backup',
            relativeBasePath: '../app',
            snapshot: new DeploymentSnapshot('hosting-deploy-2026-05-14-001', null, false, '2026-05-14T12:00:00+00:00'),
            dryRun: true,
        ));

        self::assertSame(ActionStatus::Success, $result->status);
        self::assertNotContains('Target index.php was not generated.', $result->messages);
    }
}
