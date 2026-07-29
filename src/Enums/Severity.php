<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum Severity: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Error = 'error';

    public function weight(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Warning => 1,
            self::Error => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Warning => 'WARNING',
            self::Error => 'ERROR',
        };
    }
}
