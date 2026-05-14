<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Git;

use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\Exceptions\HostingException;

final class GitService
{
    public function __construct(private readonly ProcessRunnerInterface $processRunner)
    {
    }

    public function isInstalled(): bool
    {
        return $this->processRunner->run(['git', '--version'])->isSuccessful();
    }

    public function isRepository(string $path): bool
    {
        if (! $this->isInstalled()) {
            return false;
        }

        return $this->processRunner
            ->run(['git', 'rev-parse', '--is-inside-work-tree'], $path)
            ->isSuccessful();
    }

    public function hasRemote(string $path): bool
    {
        if (! $this->isRepository($path)) {
            return false;
        }

        $result = $this->processRunner->run(['git', 'remote'], $path);

        return $result->isSuccessful() && trim($result->output) !== '';
    }

    public function hasIdentity(string $path): bool
    {
        if (! $this->isInstalled()) {
            return false;
        }

        return $this->configValue($path, 'user.name') !== null
            && $this->configValue($path, 'user.email') !== null;
    }

    public function initializeRepository(string $path): void
    {
        $result = $this->processRunner->run(['git', 'init'], $path);

        if (! $result->isSuccessful()) {
            throw new HostingException(trim($result->errorOutput) ?: 'Unable to initialize git repository.');
        }
    }

    public function hasCommits(string $path): bool
    {
        if (! $this->isRepository($path)) {
            return false;
        }

        return $this->processRunner
            ->run(['git', 'rev-parse', '--verify', 'HEAD'], $path)
            ->isSuccessful();
    }

    public function isDirty(string $path): bool
    {
        if (! $this->isRepository($path)) {
            return false;
        }

        $result = $this->processRunner->run(['git', 'status', '--porcelain'], $path);

        return trim($result->output) !== '';
    }

    public function createSnapshotCommit(string $path, string $message): void
    {
        $add = $this->processRunner->run(['git', 'add', '-A'], $path);

        if (! $add->isSuccessful()) {
            throw new HostingException(trim($add->errorOutput) ?: 'Unable to stage files for deployment snapshot.');
        }

        $commit = $this->processRunner->run(['git', 'commit', '--allow-empty', '-m', $message], $path, ['GIT_EDITOR' => 'true']);

        if (! $commit->isSuccessful()) {
            throw new HostingException(trim($commit->errorOutput) ?: 'Unable to create deployment snapshot commit.');
        }
    }

    public function createAnnotatedTag(string $path, string $tagName, string $message): void
    {
        $result = $this->processRunner->run(['git', 'tag', '-a', $tagName, '-m', $message], $path);

        if (! $result->isSuccessful()) {
            throw new HostingException(trim($result->errorOutput) ?: 'Unable to create deployment tag.');
        }
    }

    public function configureIdentity(string $path, string $name, string $email): void
    {
        $this->setConfigValue($path, 'user.name', $name);
        $this->setConfigValue($path, 'user.email', $email);
    }

    public function headCommitHash(string $path): ?string
    {
        return $this->commitHash($path, 'HEAD');
    }

    public function commitHash(string $path, string $reference): ?string
    {
        if (! $this->isRepository($path)) {
            return null;
        }

        $result = $this->processRunner->run(['git', 'rev-parse', '--verify', $reference], $path);

        if (! $result->isSuccessful()) {
            return null;
        }

        $hash = trim($result->output);

        return $hash === '' ? null : $hash;
    }

    public function parentCommitHash(string $path, string $reference): ?string
    {
        return $this->commitHash($path, $reference . '^');
    }

    public function findCommitByMessage(string $path, string $message): ?string
    {
        if (! $this->isRepository($path)) {
            return null;
        }

        $result = $this->processRunner->run([
            'git',
            'log',
            '--format=%H',
            '--fixed-strings',
            '--grep',
            $message,
            '-n',
            '1',
        ], $path);

        if (! $result->isSuccessful()) {
            return null;
        }

        $hash = trim($result->output);

        return $hash === '' ? null : $hash;
    }

    public function deleteTag(string $path, string $tagName): void
    {
        $result = $this->processRunner->run(['git', 'tag', '-d', $tagName], $path);

        if (! $result->isSuccessful()) {
            throw new HostingException(trim($result->errorOutput) ?: 'Unable to delete the deployment tag.');
        }
    }

    public function resetHard(string $path, string $reference): void
    {
        $result = $this->processRunner->run(['git', 'reset', '--hard', $reference], $path);

        if (! $result->isSuccessful()) {
            throw new HostingException(trim($result->errorOutput) ?: 'Unable to reset the repository to the requested reference.');
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(string $path, string $prefix = ''): array
    {
        if (! $this->isRepository($path)) {
            return [];
        }

        $pattern = $prefix === '' ? '*' : $prefix . '*';
        $result = $this->processRunner->run(['git', 'tag', '--list', $pattern], $path);

        if (! $result->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $result->output))));
    }

    /**
     * @return array<int, string>
     */
    public function history(string $path, int $limit = 10): array
    {
        if (! $this->isRepository($path)) {
            return [];
        }

        $result = $this->processRunner->run([
            'git',
            'log',
            '--pretty=format:%h | %ad | %s',
            '--date=short',
            '-n',
            (string) $limit,
        ], $path);

        if (! $result->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $result->output))));
    }

    public function rollbackToTag(string $path, string $tagName): void
    {
        $this->resetHard($path, $tagName);
    }

    private function configValue(string $path, string $key): ?string
    {
        $result = $this->processRunner->run(['git', 'config', $key], $path);

        if (! $result->isSuccessful()) {
            return null;
        }

        $value = trim($result->output);

        return $value === '' ? null : $value;
    }

    private function setConfigValue(string $path, string $key, string $value): void
    {
        $result = $this->processRunner->run(['git', 'config', $key, $value], $path);

        if (! $result->isSuccessful()) {
            throw new HostingException(trim($result->errorOutput) ?: "Unable to configure git {$key}.");
        }
    }
}
