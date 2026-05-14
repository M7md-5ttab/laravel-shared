<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\DeploymentMode;

final class DeploymentManifest
{
    public function __construct(
        public readonly string $snapshotName,
        public readonly ?string $tagName,
        public readonly DeploymentMode $mode,
        public readonly string $targetPath,
        public readonly string $backupPath,
        public readonly string $deployedAt,
        public readonly string $publicSourcePath,
        public readonly string $relativeBasePath,
        public readonly bool $storageLinked,
        public readonly ?string $gitCommitHash = null,
        public readonly ?string $previousGitCommitHash = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'snapshot_name' => $this->snapshotName,
            'tag_name' => $this->tagName,
            'mode' => $this->mode->value,
            'target_path' => $this->targetPath,
            'backup_path' => $this->backupPath,
            'deployed_at' => $this->deployedAt,
            'public_source_path' => $this->publicSourcePath,
            'relative_base_path' => $this->relativeBasePath,
            'storage_linked' => $this->storageLinked,
            'git_commit_hash' => $this->gitCommitHash,
            'previous_git_commit_hash' => $this->previousGitCommitHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            snapshotName: (string) $payload['snapshot_name'],
            tagName: isset($payload['tag_name']) ? (string) $payload['tag_name'] : null,
            mode: DeploymentMode::from((string) $payload['mode']),
            targetPath: (string) $payload['target_path'],
            backupPath: (string) $payload['backup_path'],
            deployedAt: (string) $payload['deployed_at'],
            publicSourcePath: (string) $payload['public_source_path'],
            relativeBasePath: (string) $payload['relative_base_path'],
            storageLinked: (bool) $payload['storage_linked'],
            gitCommitHash: isset($payload['git_commit_hash']) ? (string) $payload['git_commit_hash'] : null,
            previousGitCommitHash: isset($payload['previous_git_commit_hash']) ? (string) $payload['previous_git_commit_hash'] : null,
        );
    }
}
