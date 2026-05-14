<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Fixers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Fixers\GitIgnoreFixer;
use Mockery;
use PHPUnit\Framework\TestCase;

final class GitIgnoreFixerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_appends_missing_gitignore_rules(): void
    {
        $files = Mockery::mock(Filesystem::class);
        $config = Mockery::mock(ConfigRepository::class);

        $files->shouldReceive('exists')->once()->with('/app/.gitignore')->andReturn(true);
        $files->shouldReceive('get')->once()->with('/app/.gitignore')->andReturn(".env\n");
        $config->shouldReceive('get')->once()->with('hosting-shared.gitignore_rules', [])->andReturn([
            '/vendor',
            '/node_modules',
            '.env',
        ]);
        $files->shouldReceive('put')->once()->with('/app/.gitignore', ".env\n\n# laravel-shared:start\n/vendor\n/node_modules\n# laravel-shared:end\n");

        $fixer = new GitIgnoreFixer($files, $config);

        $result = $fixer->fix(
            new CheckResult('gitignore', 'Laravel .gitignore rules', CheckStatus::Warning, 'Missing rules.'),
            new EnvironmentContext('/app', '/app/public', '/app/storage', '/app/bootstrap/cache', '8.2.12', '11.9.0', 'Linux', HostingProvider::Generic, null),
        );

        self::assertSame(ActionStatus::Success, $result->status);
        self::assertTrue($result->performed);
    }
}
