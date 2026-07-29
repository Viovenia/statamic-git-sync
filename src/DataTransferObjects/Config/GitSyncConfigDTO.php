<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

/**
 * The whole addon configuration, typed.
 *
 * Built once by GitSyncConfigResolver and bound as a singleton. No class in
 * this package calls config() — they take this object instead, which means
 * every option is discoverable through the type system and a typo in a key
 * fails at boot rather than at three in the morning.
 */
final readonly class GitSyncConfigDTO
{
    public function __construct(
        public bool $enabled,
        public bool $dryRun,
        public string $strategy,
        public RepositoryConfigDTO $repository,
        public ContentConfigDTO $content,
        public CommitConfigDTO $commit,
        public PullConfigDTO $pull,
        public PushConfigDTO $push,
        public PullRequestConfigDTO $pullRequest,
        public DebounceConfigDTO $debounce,
        public QueueConfigDTO $queue,
        public SafetyNetConfigDTO $safetyNet,
        public HistoryConfigDTO $history,
        public NotificationConfigDTO $notifications,
        public LoggingConfigDTO $logging,
        public EventConfigDTO $events,
    ) {}
}
