<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Fixers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use M7md5ttab\LaravelShared\Contracts\FixerInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;
use M7md5ttab\LaravelShared\Support\HostingProviderDetector;
use M7md5ttab\LaravelShared\Support\PathHelper;
use M7md5ttab\LaravelShared\Support\SymlinkSupportDetector;

final class StorageLinkFixer implements FixerInterface
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
        private readonly SymlinkSupportDetector $symlinkSupport,
        private readonly HostingProviderDetector $providerDetector,
    ) {
    }

    public function supports(CheckResult $result): bool
    {
        return $result->key === 'storage-link';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Automatic;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Create the Laravel public/storage symbolic link if the host allows it?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        $probe = $this->symlinkSupport->detect($context);

        if (! $probe->supported) {
            return new FixResult(
                name: 'Storage link',
                status: ActionStatus::Warning,
                message: 'A storage symlink was not created because the host does not currently allow symlinks.',
                nextStep: $this->providerDetector->guidance($context->hostingProvider),
            );
        }

        $linkPath = PathHelper::join($context->basePath, (string) $this->config->get('hosting-shared.public_storage_link'));
        $sourcePath = PathHelper::join($context->basePath, (string) $this->config->get('hosting-shared.storage_public_directory'));

        if (! $this->files->isDirectory($sourcePath)) {
            $this->files->ensureDirectoryExists($sourcePath);
        }

        $this->files->ensureDirectoryExists(dirname($linkPath));

        if ($this->files->isDirectory($linkPath) && ! is_link($linkPath)) {
            $backupPath = $linkPath . '.backup-' . CarbonImmutable::now()->format('YmdHis');
            $this->files->move($linkPath, $backupPath);
        }

        if (is_link($linkPath)) {
            if (! $this->linkMatches($linkPath, $sourcePath)) {
                $this->files->delete($linkPath);
            } else {
                return new FixResult(
                    name: 'Storage link',
                    status: ActionStatus::Success,
                    message: 'The storage link already exists.',
                );
            }
        }

        if ($this->files->isFile($linkPath)) {
            $this->files->delete($linkPath);
        }

        if (is_link($linkPath)) {
            return new FixResult(
                name: 'Storage link',
                status: ActionStatus::Success,
                message: 'The storage link already exists.',
            );
        }

        $this->files->link($sourcePath, $linkPath);

        return new FixResult(
            name: 'Storage link',
            status: ActionStatus::Success,
            message: 'Created public/storage as a symbolic link to storage/app/public.',
            performed: true,
        );
    }

    private function linkMatches(string $linkPath, string $sourcePath): bool
    {
        $currentTarget = readlink($linkPath);

        if ($currentTarget === false) {
            return false;
        }

        $resolvedCurrent = realpath(dirname($linkPath) . DIRECTORY_SEPARATOR . $currentTarget) ?: realpath($currentTarget);
        $resolvedExpected = realpath($sourcePath);

        return $resolvedCurrent !== false && $resolvedExpected !== false && $resolvedCurrent === $resolvedExpected;
    }
}
