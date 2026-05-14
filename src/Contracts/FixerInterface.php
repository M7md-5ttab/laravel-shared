<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Contracts;

use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;
use M7md5ttab\LaravelShared\DTOs\FixResult;
use M7md5ttab\LaravelShared\Enums\FixerAutomation;

interface FixerInterface
{
    public function supports(CheckResult $result): bool;

    public function automation(): FixerAutomation;

    public function description(CheckResult $result, EnvironmentContext $context): string;

    public function fix(CheckResult $result, EnvironmentContext $context): FixResult;
}
