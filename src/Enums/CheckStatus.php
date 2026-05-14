<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Enums;

enum CheckStatus: string
{
    case Passed = 'passed';
    case Info = 'info';
    case Warning = 'warning';
    case Failed = 'failed';

    public function icon(): string
    {
        return match ($this) {
            self::Passed => '✔',
            self::Info => 'i',
            self::Warning => '⚠',
            self::Failed => '✘',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Passed => 'green',
            self::Info => 'blue',
            self::Warning => 'yellow',
            self::Failed => 'red',
        };
    }

    public function scoreWeight(): float
    {
        return match ($this) {
            self::Passed => 1.0,
            self::Info => 1.0,
            self::Warning => 0.5,
            self::Failed => 0.0,
        };
    }

    public function guidanceLabel(): string
    {
        return match ($this) {
            self::Passed, self::Info => 'Note',
            self::Warning, self::Failed => 'Fix',
        };
    }
}
