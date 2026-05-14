<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Fixers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class WritablePermissionsFixer implements FixerInterface
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
    ) {
    }

    public function supports(CheckResult $result): bool
    {
        return in_array($result->key, ['storage-writable', 'bootstrap-cache-writable'], true);
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Automatic;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Apply safe writable permissions to the Laravel runtime directories?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        $path = $result->key === 'storage-writable'
            ? $context->storagePath
            : $context->bootstrapCachePath;

        if (! $this->files->exists($path)) {
            return new FixResult(
                name: $result->name,
                status: ActionStatus::Warning,
                message: "The target path [{$path}] does not exist, so permissions were not changed.",
            );
        }

        $directoryMode = (int) $this->config->get('hosting-shared.permissions.directory_mode', 0775);
        $fileMode = 0664;

        $this->files->chmod($path, $directoryMode);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $this->files->chmod($item->getPathname(), $item->isDir() ? $directoryMode : $fileMode);
        }

        return new FixResult(
            name: $result->name,
            status: ActionStatus::Success,
            message: "Updated permissions for [{$path}].",
            performed: true,
        );
    }
}
