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

final class GitIgnoreCheck implements CheckInterface
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
    ) {
    }

    public function check(EnvironmentContext $context): CheckResult
    {
        $path = PathHelper::join($context->basePath, '.gitignore');

        if (! $this->files->exists($path)) {
            return new CheckResult(
                key: 'gitignore',
                name: 'Laravel .gitignore rules',
                status: CheckStatus::Warning,
                message: '.gitignore is missing from the project root.',
                recommendedFix: 'Create .gitignore and include Laravel-specific ignores such as /vendor and /node_modules.',
                blocking: false,
            );
        }

        $content = $this->files->get($path);
        $missing = [];

        foreach ((array) $this->config->get('hosting-shared.gitignore_rules', []) as $rule) {
            if (! str_contains($content, (string) $rule)) {
                $missing[] = (string) $rule;
            }
        }

        if ($missing === []) {
            return new CheckResult(
                key: 'gitignore',
                name: 'Laravel .gitignore rules',
                status: CheckStatus::Passed,
                message: '.gitignore contains the expected Laravel deployment ignores.',
            );
        }

        return new CheckResult(
            key: 'gitignore',
            name: 'Laravel .gitignore rules',
            status: CheckStatus::Warning,
            message: '.gitignore is missing one or more Laravel deployment ignores.',
            recommendedFix: 'Append the missing rules and keep vendor and node_modules out of source control.',
            details: $missing,
            blocking: false,
        );
    }
}
