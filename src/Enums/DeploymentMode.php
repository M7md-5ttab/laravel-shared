<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Enums;

enum DeploymentMode: string
{
    case Copy = 'copy';
    case Symlink = 'symlink';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
