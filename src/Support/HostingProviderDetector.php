<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

use Illuminate\Contracts\Config\Repository;
use M7md5ttab\LaravelShared\Enums\HostingProvider;

final class HostingProviderDetector
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function detect(string $basePath): HostingProvider
    {
        $signals = strtolower(implode(' ', array_filter([
            $basePath,
            gethostname() ?: null,
            $_SERVER['SERVER_SOFTWARE'] ?? null,
            $_SERVER['HTTP_HOST'] ?? null,
            $_SERVER['DOCUMENT_ROOT'] ?? null,
        ])));

        /** @var array<string, array<int, string>> $signatures */
        $signatures = (array) $this->config->get('hosting-shared.provider_signatures', []);

        foreach ($signatures as $provider => $tokens) {
            foreach ($tokens as $token) {
                if ($token !== '' && str_contains($signals, strtolower($token))) {
                    return HostingProvider::from($provider);
                }
            }
        }

        return HostingProvider::Generic;
    }

    public function guidance(HostingProvider $provider): string
    {
        return (string) $this->config->get(
            'hosting-shared.provider_guidance.' . $provider->value,
            $this->config->get('hosting-shared.provider_guidance.generic', 'Use copy mode if symlinks are unavailable.'),
        );
    }
}
