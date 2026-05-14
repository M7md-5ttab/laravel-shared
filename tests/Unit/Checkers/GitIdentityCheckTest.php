<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Checkers;

use M7md5ttab\LaravelShared\Checkers\GitIdentityCheck;
use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\ProcessResult;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Enums\HostingProvider;
use M7md5ttab\LaravelShared\Git\GitService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class GitIdentityCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_passes_when_git_identity_is_configured(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->times(3)
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'rev-parse', '--is-inside-work-tree'] && $workingDirectory === '/app')
            ->andReturn(new ProcessResult(['git', 'rev-parse'], 0, 'true', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'config', 'user.name'] && $workingDirectory === '/app')
            ->andReturn(new ProcessResult(['git', 'config', 'user.name'], 0, "Laravel Shared Tool\n", ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'config', 'user.email'] && $workingDirectory === '/app')
            ->andReturn(new ProcessResult(['git', 'config', 'user.email'], 0, "tool@local\n", ''));

        $check = new GitIdentityCheck(new GitService($runner));
        $result = $check->check($this->environment());

        self::assertSame(CheckStatus::Passed, $result->status);
    }

    public function test_it_fails_when_git_identity_is_missing_in_a_repository(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->times(3)
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'rev-parse', '--is-inside-work-tree'] && $workingDirectory === '/app')
            ->andReturn(new ProcessResult(['git', 'rev-parse'], 0, 'true', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'config', 'user.name'] && $workingDirectory === '/app')
            ->andReturn(new ProcessResult(['git', 'config', 'user.name'], 1, '', 'missing'));

        $check = new GitIdentityCheck(new GitService($runner));
        $result = $check->check($this->environment());

        self::assertSame(CheckStatus::Failed, $result->status);
        self::assertSame(
            'Run git config user.name "Your Name" && git config user.email "you@example.com" inside this repository.',
            $result->recommendedFix,
        );
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
