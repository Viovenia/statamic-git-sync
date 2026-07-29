<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

use Viovenia\GitSync\Exceptions\ConfigurationException;

final readonly class BranchNameDTO
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw ConfigurationException::invalidValue('push.branch', $value, 'a non-empty branch name');
        }

        return new self($value);
    }

    /** `git rev-parse --abbrev-ref HEAD` returns this when no commit exists yet. */
    public function isDetached(): bool
    {
        return $this->value === 'HEAD';
    }
}
