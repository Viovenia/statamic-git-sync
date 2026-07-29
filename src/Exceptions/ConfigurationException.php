<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Exceptions;

use Viovenia\GitSync\Enums\FailureType;

final class ConfigurationException extends GitSyncException
{
    public static function invalidValue(string $key, mixed $value, string $expected): self
    {
        return new self(sprintf(
            'Invalid value for [statamic-git-sync.%s]: expected %s, got %s.',
            $key,
            $expected,
            get_debug_type($value),
        ));
    }

    public static function unknownOption(string $key, string $value, string $allowed): self
    {
        return new self(sprintf(
            'Unknown value "%s" for [statamic-git-sync.%s]. Allowed values are: %s.',
            $value,
            $key,
            $allowed,
        ));
    }

    public static function missing(string $key): self
    {
        return new self(sprintf('Missing required configuration key [statamic-git-sync.%s].', $key));
    }

    public static function unknownStrategy(string $identifier, string $available): self
    {
        return new self(sprintf(
            'Unknown sync strategy "%s". Registered strategies are: %s.',
            $identifier,
            $available,
        ));
    }

    public function failureType(): FailureType
    {
        return FailureType::Configuration;
    }
}
