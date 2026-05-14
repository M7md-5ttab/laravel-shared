<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Contracts;

use M7md5ttab\LaravelShared\DTOs\DeploymentManifest;

interface ManifestRepositoryInterface
{
    public function save(DeploymentManifest $manifest): string;

    public function findBySnapshot(string $snapshotName): ?DeploymentManifest;

    public function deleteBySnapshot(string $snapshotName): bool;

    /**
     * @return array<int, DeploymentManifest>
     */
    public function all(): array;
}
