<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;
use M7md5ttab\LaravelShared\Support\PathHelper;

final class StorageLinkCheck implements CheckInterface
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
    ) {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        $linkPath = PathHelper::join(
            $context->basePath,
            (string) $this->config->get('hosting-shared.public_storage_link', 'public/storage'),
        );

        if (is_link($linkPath)) {
            return new CheckResult(
                key: 'storage-link',
                name: 'Storage link',
                status: CheckStatus::Passed,
                message: 'public/storage is already a symbolic link.',
                blocking: false,
            );
        }

        if ($this->files->isDirectory($linkPath)) {
            return new CheckResult(
                key: 'storage-link',
                name: 'Storage link',
                status: CheckStatus::Info,
                message: 'public/storage exists as a directory instead of a symbolic link.',
                recommendedFix: 'Replace the directory with a symbolic link or deploy using copy mode.',
                blocking: false,
            );
        }

        return new CheckResult(
            key: 'storage-link',
            name: 'Storage link',
            status: CheckStatus::Info,
            message: 'public/storage does not exist yet.',
            recommendedFix: 'Create it manually with storage:link if your app needs public storage assets, or use copy mode during deployment.',
            blocking: false,
        );
    }
}
