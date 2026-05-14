<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Enums;

enum FixerAutomation: string
{
    case Automatic = 'automatic';
    case Advisory = 'advisory';
    case Interactive = 'interactive';
}
