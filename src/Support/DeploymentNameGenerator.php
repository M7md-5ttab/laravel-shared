<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\Git\GitService;

final class DeploymentNameGenerator
{
    public function __construct(
        private readonly Repository $config,
        private readonly ManifestRepositoryInterface $manifests,
        private readonly GitService $git,
    ) {
    }

    public function generate(string $basePath): string
    {
        $prefix = (string) $this->config->get('hosting-shared.deployment.tag_prefix', 'hosting-deploy');
        $date = CarbonImmutable::now()->format('Y-m-d');
        $pattern = '/^' . preg_quote($prefix . '-' . $date . '-', '/') . '(\d{3})$/';
        $highest = 0;

        foreach ($this->git->tags($basePath, $prefix . '-' . $date . '-') as $tag) {
            if (preg_match($pattern, $tag, $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        foreach ($this->manifests->all() as $manifest) {
            if (preg_match($pattern, $manifest->snapshotName, $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return sprintf('%s-%03d', $prefix . '-' . $date, $highest + 1);
    }
}
