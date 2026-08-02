<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\History;

use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;
use Viovenia\GitSync\Enums\FileStatus;

/**
 * One file inside a recorded run.
 *
 * The path and what happened to it — never the contents. A history file that
 * held content would be a second copy of the site sitting in storage, and
 * git already has the real one: the widget links to the commit for that.
 */
final readonly class HistoryFileDTO
{
    public function __construct(
        public string $path,
        public FileStatus $status,
        public ?string $originalPath = null,
    ) {}

    public static function fromChangedFile(ChangedFileDTO $file): self
    {
        return new self($file->path, $file->status, $file->originalPath);
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'status' => $this->status->value,
            'original_path' => $this->originalPath,
        ];
    }

    /**
     * Tolerates anything an older or newer release wrote; one unreadable
     * entry costs that entry, not the run it belongs to.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $path = $data['path'] ?? null;
        $status = FileStatus::tryFrom(is_string($data['status'] ?? null) ? $data['status'] : '');

        if (! is_string($path) || $path === '' || $status === null) {
            return null;
        }

        $original = $data['original_path'] ?? null;

        return new self($path, $status, is_string($original) && $original !== '' ? $original : null);
    }

    public function describe(): string
    {
        return $this->originalPath !== null
            ? sprintf('%s → %s', $this->originalPath, $this->path)
            : $this->path;
    }
}
