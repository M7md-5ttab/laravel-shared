<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Services;

use Illuminate\Contracts\Container\Container;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\Contracts\RollbackStrategyInterface;
use M7md5ttab\LaravelShared\DTOs\RollbackContext;
use M7md5ttab\LaravelShared\DTOs\RollbackResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Support\ProjectEnvironmentResolver;

class RollbackManager
{
    /**
     * @param  array<int, class-string<RollbackStrategyInterface>>  $strategies
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $strategies,
        private readonly ProjectEnvironmentResolver $environmentResolver,
        private readonly ManifestRepositoryInterface $manifests,
        private readonly GitService $git,
    ) {
    }

    public function rollback(?string $tagName = null, ?string $snapshotName = null, bool $force = false): RollbackResult
    {
        $context = $this->resolveContext($tagName, $snapshotName, $force);

        foreach ($this->strategies as $strategyClass) {
            /** @var RollbackStrategyInterface $strategy */
            $strategy = $this->container->make($strategyClass);

            if ($context->preferredStrategy !== null && $strategy->name() !== $context->preferredStrategy) {
                continue;
            }

            if ($strategy->supports($context)) {
                return $strategy->rollback($context);
            }
        }

        return new RollbackResult(
            status: ActionStatus::Failure,
            strategy: 'none',
            message: 'No rollback strategy was able to handle the requested rollback reference.',
        );
    }

    /**
     * @return array<int, \M7md5ttab\LaravelShared\DTOs\DeploymentManifest>
     */
    public function manifests(): array
    {
        return $this->manifests->all();
    }

    /**
     * @return array<int, string>
     */
    public function gitTags(): array
    {
        $environment = $this->environmentResolver->resolve();
        $prefix = (string) $this->container['config']->get('hosting-shared.deployment.tag_prefix', 'hosting-deploy');

        return $this->git->tags($environment->basePath, $prefix);
    }

    public function hasDirtyWorkingTree(?string $tagName = null, ?string $snapshotName = null): bool
    {
        $context = $this->resolveContext($tagName, $snapshotName, false);

        if ($context->tagName === null && $context->manifest === null) {
            return false;
        }

        $environment = $this->environmentResolver->resolve();

        return $this->git->isDirty($environment->basePath);
    }

    public function usesGitRollback(?string $tagName = null, ?string $snapshotName = null): bool
    {
        return $this->resolveContext($tagName, $snapshotName, false)->preferredStrategy === 'git';
    }

    private function resolveContext(?string $tagName, ?string $snapshotName, bool $force): RollbackContext
    {
        $manifest = $this->resolveManifest($tagName, $snapshotName);

        return new RollbackContext(
            environment: $this->environmentResolver->resolve(),
            tagName: $tagName,
            manifest: $manifest,
            force: $force,
            preferredStrategy: $this->preferredStrategy($tagName, $manifest),
        );
    }

    private function resolveManifest(?string $tagName, ?string $snapshotName): ?\M7md5ttab\LaravelShared\DTOs\DeploymentManifest
    {
        if ($snapshotName !== null) {
            return $this->manifests->findBySnapshot($snapshotName);
        }

        if ($tagName !== null) {
            return null;
        }

        return $this->manifests->all()[0] ?? null;
    }

    private function preferredStrategy(?string $tagName, ?\M7md5ttab\LaravelShared\DTOs\DeploymentManifest $manifest): ?string
    {
        if ($tagName !== null) {
            return 'git';
        }

        if ($manifest !== null) {
            return 'cleanup';
        }

        return null;
    }
}
