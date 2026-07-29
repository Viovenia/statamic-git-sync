# Statamic Git Sync

Automatic git commits when content changes in the Statamic control panel. MIT licensed, event driven, and usable on Statamic Solo.

## Who this is for

You keep Statamic content in flat files and you want the git history to reflect what editors do in the control panel — without paying for Pro, and without a cron job that commits blindly every 24 hours.

## The documentation

| Page | What is in it |
|---|---|
| [installation.md](installation.md) | Requirements, install, first run, deploy keys |
| [configuration.md](configuration.md) | Every option: default, effect, pitfall |
| [usage.md](usage.md) | Daily use, the commands, what a commit looks like |
| [advanced.md](advanced.md) | Events, contracts, writing your own strategy |
| [troubleshooting.md](troubleshooting.md) | It is not committing — start here |

## The shape of it

```
Statamic event  →  ContentEventSubscriber  →  SyncDispatcher  →  SyncContentJob
                                                                       │
                        SyncManager  ←────────────────────────────────┘
                             │  lock, events, failure policy
                             ▼
                    CommitSyncStrategy  →  GitRepository  →  ProcessRunner  →  git
```

Every arrow is an interface. Every layer can be replaced or faked on its own, which is why the test suite runs without a git binary and the integration tests can use a real one.

## Design decisions worth knowing

**Nothing happens in the request that saved an entry.** The subscriber writes a timestamp and queues a job. Whatever is wrong with git, saving content keeps working.

**The cache is a timing hint, never a source of truth.** What gets committed is read from `git status` at the moment of the run. A flushed cache costs you the bundling of an editing session into one commit — never the changes themselves. The scheduled safety net catches whatever the event path missed.

**Only the allow list is staged.** `git status` is scoped to the configured paths, and the result is filtered a second time. An unrelated change in `app/` cannot end up in a content commit.

**Failures are classified, not just logged.** A push that lost the network is retried with backoff. A missing git binary is not — it will still be missing in thirty seconds. A conflict is rolled back and reported, because it needs a person.

## Roadmap

- Notification channels for conflicts and opened pull requests, built on the existing events.
- A control panel widget showing recent syncs.
- GitLab and Gitea implementations of `PullRequestClientContract`.
