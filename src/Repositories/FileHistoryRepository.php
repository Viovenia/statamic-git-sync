<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Repositories;

use Psr\Log\LoggerInterface;
use Throwable;
use Viovenia\GitSync\Contracts\HistoryRepositoryContract;
use Viovenia\GitSync\DataTransferObjects\Config\HistoryConfigDTO;
use Viovenia\GitSync\DataTransferObjects\History\SyncHistoryEntryDTO;

/**
 * One JSON object per line, appended under an exclusive lock.
 *
 * A file rather than the cache, because the widget would otherwise be empty
 * after every deploy — `cache:clear` is part of most of them. A file rather
 * than a database table, because a flat-file Statamic Solo site often has no
 * database at all, and a migration in an addon is a poor thing to force on
 * somebody who only wanted their content committed.
 *
 * Nothing in here is allowed to throw. This is a display; a sync must never
 * fail because its history could not be written.
 */
final class FileHistoryRepository implements HistoryRepositoryContract
{
    public function __construct(
        private readonly HistoryConfigDTO $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function record(SyncHistoryEntryDTO $entry): void
    {
        try {
            $this->ensureDirectory();

            // Checked rather than suppressed: an unwritable path should leave
            // one debug line, not a PHP warning in somebody's error log every
            // time content is saved.
            if (! $this->writable()) {
                $this->logger->debug('[statamic-git-sync] the history file is not writable', [
                    'path' => $this->config->path,
                ]);

                return;
            }

            $line = json_encode($entry->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($line === false) {
                return;
            }

            file_put_contents($this->config->path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);

            $this->trim();
        } catch (Throwable $exception) {
            $this->logger->debug('[statamic-git-sync] could not write the history file', [
                'path' => $this->config->path,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /** @return list<SyncHistoryEntryDTO> */
    public function recent(int $limit): array
    {
        $entries = [];

        foreach (array_reverse($this->lines()) as $line) {
            if (count($entries) >= $limit) {
                break;
            }

            $entry = $this->decode($line);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function clear(): void
    {
        if (is_file($this->config->path)) {
            @unlink($this->config->path);
        }
    }

    public function enabled(): bool
    {
        return $this->config->enabled;
    }

    /**
     * Keeps the file from growing without bound.
     *
     * Rewritten wholesale rather than trimmed in place: the file is a few
     * hundred short lines at most, and a partial rewrite that failed halfway
     * would leave something unreadable behind.
     */
    private function trim(): void
    {
        $lines = $this->lines();

        if (count($lines) <= $this->config->maxEntries) {
            return;
        }

        $kept = array_slice($lines, -$this->config->maxEntries);

        file_put_contents($this->config->path, implode(PHP_EOL, $kept).PHP_EOL, LOCK_EX);
    }

    /** @return list<string> */
    private function lines(): array
    {
        if (! is_file($this->config->path)) {
            return [];
        }

        $contents = @file_get_contents($this->config->path);

        if ($contents === false || $contents === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\R/', $contents) ?: [],
            static fn (string $line): bool => trim($line) !== '',
        ));
    }

    private function decode(string $line): ?SyncHistoryEntryDTO
    {
        $data = json_decode($line, true);

        return is_array($data) ? SyncHistoryEntryDTO::fromArray($data) : null;
    }

    public function writable(): bool
    {
        return is_file($this->config->path)
            ? is_writable($this->config->path)
            : is_dir(dirname($this->config->path)) && is_writable(dirname($this->config->path));
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->config->path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0o755, true);
        }
    }
}
