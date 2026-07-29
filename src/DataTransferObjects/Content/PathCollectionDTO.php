<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Content;

final readonly class PathCollectionDTO
{
    /** @param list<string> $paths */
    private function __construct(public array $paths) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(string ...$paths): self
    {
        return self::fromArray($paths);
    }

    /** @param array<int|string, string> $paths */
    public static function fromArray(array $paths): self
    {
        $normalised = [];

        foreach ($paths as $path) {
            $path = trim($path);

            if ($path !== '' && ! in_array($path, $normalised, true)) {
                $normalised[] = $path;
            }
        }

        return new self($normalised);
    }

    public function isEmpty(): bool
    {
        return $this->paths === [];
    }

    public function count(): int
    {
        return count($this->paths);
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->paths;
    }

    public function merge(self $other): self
    {
        return self::fromArray([...$this->paths, ...$other->paths]);
    }

    public function take(int $limit): self
    {
        return new self(array_slice($this->paths, 0, max(0, $limit)));
    }
}
