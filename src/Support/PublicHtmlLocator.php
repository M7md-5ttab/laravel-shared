<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

final class PublicHtmlLocator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
    ) {
    }

    public function detect(string $basePath): ?string
    {
        foreach ($this->candidates() as $candidate) {
            $path = $this->resolvePath($basePath, (string) $candidate);

            if ($this->files->isDirectory($path)) {
                return $path;
            }
        }

        return null;
    }

    public function resolvePath(string $basePath, string $target): string
    {
        if (PathHelper::isAbsolute($target)) {
            return PathHelper::normalize($target);
        }

        return PathHelper::join($basePath, $target);
    }

    public function defaultPath(string $basePath): string
    {
        return $this->resolvePath($basePath, $this->candidates()[0]);
    }

    /**
     * @return array<int, string>
     */
    private function candidates(): array
    {
        $candidates = array_values(array_filter(
            array_map(
                static fn (mixed $candidate): string => trim((string) $candidate),
                (array) $this->config->get('hosting-shared.public_html_candidates', []),
            ),
            static fn (string $candidate): bool => $candidate !== '',
        ));

        return $candidates !== [] ? $candidates : ['../public_html'];
    }
}
