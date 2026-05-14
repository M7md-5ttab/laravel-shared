<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Support\IndexPhpPatcher;
use PHPUnit\Framework\TestCase;

final class IndexPhpPatcherTest extends TestCase
{
    private Filesystem $files;

    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->workspace = sys_get_temp_dir() . '/laravel-shared-index-' . uniqid('', true);
        $this->files->ensureDirectoryExists($this->workspace);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_patches_index_php_with_the_new_relative_paths(): void
    {
        $source = $this->workspace . '/source-index.php';
        $target = $this->workspace . '/target-index.php';

        $this->files->put($source, <<<'PHP'
<?php
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
PHP);

        $patcher = new IndexPhpPatcher($this->files);
        $patcher->patch($source, $target, '../laravel-app');

        $content = $this->files->get($target);

        self::assertStringContainsString("__DIR__.'/../laravel-app/storage/framework/maintenance.php'", $content);
        self::assertStringContainsString("__DIR__.'/../laravel-app/vendor/autoload.php'", $content);
        self::assertStringContainsString("__DIR__.'/../laravel-app/bootstrap/app.php'", $content);
    }
}
