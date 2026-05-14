<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Contracts;

use M7md5ttab\LaravelShared\DTOs\RollbackContext;
use M7md5ttab\LaravelShared\DTOs\RollbackResult;

interface RollbackStrategyInterface
{
    public function name(): string;

    public function supports(RollbackContext $context): bool;

    public function rollback(RollbackContext $context): RollbackResult;
}
