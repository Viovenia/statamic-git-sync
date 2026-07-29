<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Console\Commands;

use Illuminate\Console\Command;
use Throwable;
use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\PathFilterContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;

/**
 * Answers "what would happen if a sync ran right now" without running one.
 */
final class StatusCommand extends Command
{
    protected $signature = 'statamic:git-sync:status';

    protected $description = 'Show the pending content changes and the effective configuration';

    public function handle(
        GitRepositoryContract $repository,
        GitRepositoryLocatorContract $locator,
        PathFilterContract $filter,
        GitSyncConfigDTO $config,
    ): int {
        $this->components->twoColumnDetail('<fg=gray>Repository</>', $locator->path());
        $this->components->twoColumnDetail('<fg=gray>Enabled</>', $config->enabled ? 'yes' : 'no');
        $this->components->twoColumnDetail('<fg=gray>Strategy</>', $config->strategy);
        $this->components->twoColumnDetail(
            '<fg=gray>Debounce</>',
            $config->debounce->enabled ? $config->debounce->seconds.'s' : 'off',
        );
        $this->components->twoColumnDetail(
            '<fg=gray>Push</>',
            $config->push->enabled
                ? $config->push->remote.' '.($config->push->branch !== null ? $config->push->branch->value : 'current branch')
                : 'off',
        );
        $this->components->twoColumnDetail('<fg=gray>Watched paths</>', implode(', ', $config->content->paths->all()));

        if (! $locator->exists()) {
            $this->newLine();
            $this->components->error('No git repository at '.$locator->path());

            return self::FAILURE;
        }

        try {
            $changes = $repository->status($config->content->paths)->filteredBy($filter);
        } catch (Throwable $exception) {
            $this->newLine();
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        if ($changes->isEmpty()) {
            $this->components->info('Nothing pending.');

            return self::SUCCESS;
        }

        $this->components->info($changes->count().' file(s) pending:');

        foreach ($changes->files as $file) {
            $this->components->twoColumnDetail($this->describe($file), '<fg=gray>'.$file->status->value.'</>');
        }

        return self::SUCCESS;
    }

    private function describe(ChangedFileDTO $file): string
    {
        return $file->describe();
    }
}
