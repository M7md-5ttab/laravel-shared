<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Checkers;

use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class PublicHtmlCheck implements CheckInterface
{
    public function check(EnvironmentContext $context): CheckResult
    {
        if ($context->publicHtmlPath !== null) {
            return new CheckResult(
                key: 'public-html',
                name: 'public_html directory',
                status: CheckStatus::Passed,
                message: "Detected shared hosting target directory at [{$context->publicHtmlPath}].",
                blocking: false,
            );
        }

        return new CheckResult(
            key: 'public-html',
            name: 'public_html directory',
            status: CheckStatus::Warning,
            message: 'No public_html directory was auto-detected near the application.',
            recommendedFix: 'Run hosting:run to create the default public_html directory, or pass a target path during deployment.',
            blocking: false,
        );
    }
}
