<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Publishers;

use M7md5ttab\LaravelShared\Contracts\PublisherInterface;
use M7md5ttab\LaravelShared\DTOs\PublishContext;
use M7md5ttab\LaravelShared\DTOs\PublishResult;
use M7md5ttab\LaravelShared\Enums\ActionStatus;
use M7md5ttab\LaravelShared\Enums\DeploymentMode;

final class CopyPublisher extends AbstractPublisher implements PublisherInterface
{
    public function mode(): DeploymentMode
    {
        return DeploymentMode::Copy;
    }

    public function publish(PublishContext $context): PublishResult
    {
        $messages = [
            ...$this->copyPublicDirectory($context),
            ...$this->patchIndexFile($context),
            ...$this->copyStorageAssets($context),
            ...$this->verifyIndexPatch($context),
        ];

        $issues = array_values(array_filter(
            $messages,
            static fn (string $message): bool => str_contains($message, 'missing') || str_contains($message, 'not patched'),
        ));

        return new PublishResult(
            status: $issues === [] ? ActionStatus::Success : ActionStatus::Warning,
            mode: $this->mode(),
            targetPath: $context->targetPath,
            message: $issues === []
                ? 'Application assets were prepared in copy mode.'
                : 'Copy mode completed with verification warnings.',
            messages: $messages,
            snapshot: $context->snapshot,
            storageLinked: false,
            dryRun: $context->dryRun,
        );
    }
}
