<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Contracts;

use M7md5ttab\LaravelShared\DTOs\CheckResult;
use M7md5ttab\LaravelShared\DTOs\EnvironmentContext;

interface CheckInterface
{
    public function check(EnvironmentContext $context): CheckResult;
}
