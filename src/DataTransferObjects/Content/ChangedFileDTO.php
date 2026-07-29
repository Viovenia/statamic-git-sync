<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Content;

use Viovenia\GitSync\Enums\FileStatus;

final readonly class ChangedFileDTO
{
    public function __construct(
        public string $path,
        public FileStatus $status,
        /** Set for renames and copies: where the file came from. */
        public ?string $originalPath = null,
    ) {}

    public function basename(): string
    {
        return basename($this->path);
    }

    public function describe(): string
    {
        return $this->originalPath !== null
            ? sprintf('%s → %s', $this->originalPath, $this->path)
            : $this->path;
    }
}
