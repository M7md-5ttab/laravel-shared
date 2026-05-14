<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Contracts;

use M7md5ttab\LaravelShared\DTOs\PublishContext;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;

interface PublisherInterface
{
    public function mode(): DeploymentMode;

    public function publish(PublishContext $context): PublishResult;
}
