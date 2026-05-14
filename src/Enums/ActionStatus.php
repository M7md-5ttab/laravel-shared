<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Enums;

enum ActionStatus: string
{
    case Success = 'success';
    case Warning = 'warning';
    case Failure = 'failure';
    case Skipped = 'skipped';

    public function icon(): string
    {
        return match ($this) {
            self::Success => '✔',
            self::Warning => '⚠',
            self::Failure => '✘',
            self::Skipped => '•',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Success => 'green',
            self::Warning => 'yellow',
            self::Failure => 'red',
            self::Skipped => 'gray',
        };
    }
}
