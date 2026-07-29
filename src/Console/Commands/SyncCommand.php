<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Console\Isolatable;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Services\Diagnostics\Checks\SafetyNetCheck;

/**
 * Commits whatever is currently uncommitted inside the configured paths.
 *
 * Runs the same code path as the queued job, which is what makes it usable
 * both by hand and as the scheduled safety net. Isolatable so two overlapping
 * scheduler ticks cannot both start a git sequence.
 */
final class SyncCommand extends Command implements Isolatable
{
    protected $signature = 'statamic:git-sync
                            {--dry-run : Report what would be committed without touching the repository}
                            {--safety-net : Internal: marks the run as the scheduled fallback}';

    protected $description = 'Commit pending Statamic content changes to git';

    public function handle(SyncManagerContract $manager, CacheRepository $cache): int
    {
        $safetyNet = (bool) $this->option('safety-net');

        if ($safetyNet) {
            // Proof of life for the doctor command: a safety net nobody can
            // see is a safety net nobody can trust.
            $cache->put(SafetyNetCheck::HEARTBEAT_KEY, time(), 86400);
        }

        $context = $safetyNet
            ? SyncContextDTO::fromSafetyNet(time())
            : SyncContextDTO::fromConsole(time(), (bool) $this->option('dry-run'));

        $result = $manager->sync($context);

        match ($result->outcome) {
            SyncOutcome::Committed => $this->components->info(sprintf(
                'Committed %d file(s) as %s%s.',
                $result->changes->count(),
                $result->commit !== null ? $result->commit->shortSha() : '?',
                $result->push !== null ? ' and pushed to '.$result->push->describe() : '',
            )),
            SyncOutcome::NothingToCommit => $this->components->info('Nothing to commit.'),
            SyncOutcome::Skipped => $this->components->warn($result->reason ?? 'Skipped.'),
            SyncOutcome::Conflicted,
            SyncOutcome::Failed => $this->components->error(
                $result->failure !== null ? $result->failure->message : 'Sync failed.',
            ),
        };

        return $result->successful() || $result->outcome === SyncOutcome::Skipped
            ? self::SUCCESS
            : self::FAILURE;
    }
}
