<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

/**
 * How much a commit changed, in lines.
 *
 * Enough to tell a typo fix from a bulk import at a glance, without storing
 * any content anywhere.
 */
final readonly class DiffStatDTO
{
    public function __construct(
        public int $insertions,
        public int $deletions,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0);
    }

    /**
     * Sums the output of `git show --numstat`, whose lines are
     * "insertions<TAB>deletions<TAB>path". Binary files report "-" for both.
     *
     * @param  list<string>  $lines
     */
    public static function fromNumstat(array $lines): self
    {
        $insertions = 0;
        $deletions = 0;

        foreach ($lines as $line) {
            $parts = explode("\t", $line);

            if (count($parts) < 3) {
                continue;
            }

            $insertions += ctype_digit($parts[0]) ? (int) $parts[0] : 0;
            $deletions += ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        }

        return new self($insertions, $deletions);
    }

    public function isEmpty(): bool
    {
        return $this->insertions === 0 && $this->deletions === 0;
    }

    public function describe(): string
    {
        return sprintf('+%d −%d', $this->insertions, $this->deletions);
    }
}
