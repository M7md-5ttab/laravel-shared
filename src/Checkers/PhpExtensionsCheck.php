<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use Illuminate\Contracts\Config\Repository;
use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class PhpExtensionsCheck implements CheckInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        $required = (array) $this->config->get('hosting-shared.required_php_extensions', []);
        $missing = [];

        foreach ($required as $extension) {
            if (! extension_loaded((string) $extension)) {
                $missing[] = (string) $extension;
            }
        }

        if ($missing === []) {
            return new CheckResult(
                key: 'php-extensions',
                name: 'Required PHP extensions',
                status: CheckStatus::Passed,
                message: 'All required PHP extensions are available.',
            );
        }

        return new CheckResult(
            key: 'php-extensions',
            name: 'Required PHP extensions',
            status: CheckStatus::Failed,
            message: 'One or more required PHP extensions are missing.',
            recommendedFix: 'Enable the missing extensions in your hosting control panel or PHP configuration.',
            details: $missing,
        );
    }
}
