<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Unit\Git;

use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\DTOs\ProcessResult;
use M7md5ttab\LaravelShared\Git\GitService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class GitServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_detects_when_git_is_installed(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null, array $environment = [], ?int $timeout = 60): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));

        $service = new GitService($runner);

        self::assertTrue($service->isInstalled());
    }

    public function test_it_detects_when_git_identity_is_configured(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null, array $environment = [], ?int $timeout = 60): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'config', 'user.name'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'config', 'user.name'], 0, "Laravel Shared Tool\n", ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'config', 'user.email'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'config', 'user.email'], 0, "tool@local\n", ''));

        $service = new GitService($runner);

        self::assertTrue($service->hasIdentity('/repo'));
    }

    public function test_it_configures_a_local_git_identity(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'config', 'user.name', 'Laravel Shared Tool'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'config', 'user.name', 'Laravel Shared Tool'], 0, '', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'config', 'user.email', 'tool@local'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'config', 'user.email', 'tool@local'], 0, '', ''));

        $service = new GitService($runner);
        $service->configureIdentity('/repo', 'Laravel Shared Tool', 'tool@local');

        self::assertTrue(true);
    }

    public function test_it_creates_a_snapshot_commit(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'add', '-A'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'add', '-A'], 0, '', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null, array $environment = []): bool => $command === ['git', 'commit', '--allow-empty', '-m', 'snapshot'] && $workingDirectory === '/repo' && $environment === ['GIT_EDITOR' => 'true'])
            ->andReturn(new ProcessResult(['git', 'commit'], 0, '[main abc123] snapshot', ''));

        $service = new GitService($runner);
        $service->createSnapshotCommit('/repo', 'snapshot');

        self::assertTrue(true);
    }

    public function test_it_returns_tags_filtered_by_prefix(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'rev-parse', '--is-inside-work-tree'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'rev-parse'], 0, 'true', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'tag', '--list', 'hosting-deploy-*'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'tag'], 0, "hosting-deploy-2026-05-14-001\nhosting-deploy-2026-05-14-002\n", ''));

        $service = new GitService($runner);

        self::assertSame(
            ['hosting-deploy-2026-05-14-001', 'hosting-deploy-2026-05-14-002'],
            $service->tags('/repo', 'hosting-deploy-'),
        );
    }

    public function test_it_finds_a_commit_by_exact_message(): void
    {
        $runner = Mockery::mock(ProcessRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', '--version'] && $workingDirectory === null)
            ->andReturn(new ProcessResult(['git', '--version'], 0, 'git version 2.45.0', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'rev-parse', '--is-inside-work-tree'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'rev-parse'], 0, 'true', ''));
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $command, ?string $workingDirectory = null): bool => $command === ['git', 'log', '--format=%H', '--fixed-strings', '--grep', 'chore: hosting deployment snapshot [hosting-deploy-2026-05-14-001]', '-n', '1'] && $workingDirectory === '/repo')
            ->andReturn(new ProcessResult(['git', 'log'], 0, "abc123\n", ''));

        $service = new GitService($runner);

        self::assertSame(
            'abc123',
            $service->findCommitByMessage('/repo', 'chore: hosting deployment snapshot [hosting-deploy-2026-05-14-001]'),
        );
    }
}
