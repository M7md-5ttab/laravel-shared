<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class BootstrapCacheWritableCheck implements CheckInterface
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        $writable = $this->files->isWritable($context->bootstrapCachePath);

        return new CheckResult(
            key: 'bootstrap-cache-writable',
            name: 'bootstrap/cache permissions',
            status: $writable ? CheckStatus::Passed : CheckStatus::Failed,
            message: $writable
                ? 'The bootstrap/cache directory is writable.'
                : 'The bootstrap/cache directory is not writable.',
            recommendedFix: $writable ? null : 'Set writable permissions on bootstrap/cache, typically 775 for directories.',
        );
    }
}
