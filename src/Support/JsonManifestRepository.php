<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\DTOs\DeploymentManifest;

final class JsonManifestRepository implements ManifestRepositoryInterface
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly Repository $config,
    ) {
    }

    public function save(DeploymentManifest $manifest): string
    {
        $directory = $this->directory();
        $this->files->ensureDirectoryExists($directory);

        $path = PathHelper::join($directory, $manifest->snapshotName . '.json');

        $this->files->put($path, json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    public function findBySnapshot(string $snapshotName): ?DeploymentManifest
    {
        $path = PathHelper::join($this->directory(), $snapshotName . '.json');

        if (! $this->files->exists($path)) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return DeploymentManifest::fromArray($payload);
    }

    public function deleteBySnapshot(string $snapshotName): bool
    {
        $path = PathHelper::join($this->directory(), $snapshotName . '.json');

        if (! $this->files->exists($path)) {
            return false;
        }

        return $this->files->delete($path);
    }

    public function all(): array
    {
        $directory = $this->directory();

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $manifests = [];

        foreach ($this->files->files($directory) as $file) {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($file->getContents(), true, flags: JSON_THROW_ON_ERROR);
            $manifests[] = DeploymentManifest::fromArray($payload);
        }

        usort(
            $manifests,
            static fn (DeploymentManifest $left, DeploymentManifest $right): int => strcmp($right->deployedAt, $left->deployedAt),
        );

        return $manifests;
    }

    private function directory(): string
    {
        $relative = (string) $this->config->get('hosting-shared.deployment.manifest_directory', 'app/hosting-shared/manifests');

        return PathHelper::join($this->app->storagePath(), $relative);
    }
}
