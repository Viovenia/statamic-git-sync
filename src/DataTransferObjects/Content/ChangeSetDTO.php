<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Content;

use Viovenia\GitSync\Contracts\PathFilterContract;

final readonly class ChangeSetDTO
{
    /** @param list<ChangedFileDTO> $files */
    private function __construct(public array $files) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(ChangedFileDTO ...$files): self
    {
        return new self(array_values($files));
    }

    /** @param list<ChangedFileDTO> $files */
    public static function fromArray(array $files): self
    {
        return new self(array_values($files));
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    public function count(): int
    {
        return count($this->files);
    }

    /** Every path touched, including the source path of a rename. */
    public function paths(): PathCollectionDTO
    {
        $paths = [];

        foreach ($this->files as $file) {
            $paths[] = $file->path;

            if ($file->originalPath !== null) {
                $paths[] = $file->originalPath;
            }
        }

        return PathCollectionDTO::fromArray($paths);
    }

    /** Keeps only the files the filter accepts. Returns a new set. */
    public function filteredBy(PathFilterContract $filter): self
    {
        return new self(array_values(array_filter(
            $this->files,
            static fn (ChangedFileDTO $file): bool => $filter->matches($file->path)
                || ($file->originalPath !== null && $filter->matches($file->originalPath)),
        )));
    }

    /** @return list<ChangedFileDTO> */
    public function take(int $limit): array
    {
        return array_slice($this->files, 0, max(0, $limit));
    }
}
