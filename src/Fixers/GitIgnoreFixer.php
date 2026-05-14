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
use M7md5ttab\LaravelShared\Support\PathHelper;

final class GitIgnoreFixer implements FixerInterface
{
    private const START_MARKER = '# laravel-shared:start';

    private const END_MARKER = '# laravel-shared:end';

    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
    ) {
    }

    public function supports(CheckResult $result): bool
    {
        return $result->key === 'gitignore';
    }

    public function automation(): FixerAutomation
    {
        return FixerAutomation::Automatic;
    }

    public function description(CheckResult $result, EnvironmentContext $context): string
    {
        return 'Add any missing Laravel shared-hosting .gitignore rules?';
    }

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult
    {
        $path = PathHelper::join($context->basePath, '.gitignore');
        $existing = $this->files->exists($path) ? trim($this->files->get($path)) : '';
        $rules = (array) $this->config->get('hosting-shared.gitignore_rules', []);
        $missing = [];

        foreach ($rules as $rule) {
            if (! str_contains($existing, (string) $rule)) {
                $missing[] = (string) $rule;
            }
        }

        if ($missing === []) {
            return new FixResult(
                name: '.gitignore rules',
                status: ActionStatus::Success,
                message: 'No .gitignore changes were needed.',
            );
        }

        $content = $existing === '' ? '' : $existing . PHP_EOL . PHP_EOL;
        $content .= self::START_MARKER . PHP_EOL;
        $content .= implode(PHP_EOL, $missing) . PHP_EOL;
        $content .= self::END_MARKER . PHP_EOL;
        $this->files->put($path, $content);

        return new FixResult(
            name: '.gitignore rules',
            status: ActionStatus::Success,
            message: 'Added missing Laravel shared-hosting .gitignore rules.',
            performed: true,
        );
    }
}
