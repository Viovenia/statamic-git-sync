# Statamic Git Sync

Automatic `git commit` — and optionally `git push` — when content changes in the Statamic control panel.

A free, event-driven alternative to Statamic Pro's built-in Git Automation. Runs on **Statamic Solo**.

[![Tests](https://github.com/Viovenia/statamic-git-sync/actions/workflows/tests.yml/badge.svg)](https://github.com/Viovenia/statamic-git-sync/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Why this exists

Statamic keeps content in flat files, which makes git the natural place for it: review, revert, deploy-by-push, CI. Statamic can commit those changes for you — but only on **Pro**.

The free options had all run out:

| Project | State | Why it does not solve this |
|---|---|---|
| Statamic Git Automation (core) | active | Pro only |
| `statamic/spock` | archived since 2020, Statamic 2.10 | dead; the feature was absorbed into Pro |
| Gitamic | active, paid | a manual git UI in the control panel, no automation |
| `diffrentdigital/git-auto-push` | available | a blunt 24 hour cron commit, not event driven |

This addon fills the gap: MIT, event driven, and built to be left alone on a production server.

## What it does

```
Entry saved in the control panel
  → the change is queued, never committed inside the save request
  → a debounce window collects the rest of the editing session
  → one commit, with the editor as author and a loop-guard trailer
  → optionally pull --rebase, then push
```

And when something goes wrong it does the boring, safe thing: a conflicting rebase is aborted and the working tree is left exactly as it was.

## Requirements

- **A persistent filesystem holding a real git checkout.** Laravel Cloud and immutable containers have ephemeral, per-replica filesystems: content saved in the control panel is discarded on the next deploy there whether this addon is installed or not. On those platforms the answer is Statamic's Eloquent driver, and there is nothing for this addon to version — [the full table is in the docs](docs/installation.md#where-this-works)
- PHP 8.2+
- Statamic 5 or 6
- A queue worker (`php artisan queue:work`) — with the `sync` driver every commit would happen inside the save request
- A cache store that supports atomic locks (`redis`, `memcached`, `database`, `file`) for debouncing
- `php artisan schedule:run` in the crontab, for the safety net

`php artisan statamic:git-sync:doctor` checks all of this and tells you what to fix.

## Installation

```bash
composer require viovenia/statamic-git-sync
```

Publish the config when you want to change anything:

```bash
php artisan vendor:publish --tag=statamic-git-sync-config
```

Then confirm the setup:

```bash
php artisan statamic:git-sync:doctor
```

## Quickstart

Commit locally on every content change — the default, nothing to configure:

```env
STATAMIC_GIT_SYNC_ENABLED=true
```

Commit and push to `origin`:

```env
STATAMIC_GIT_SYNC_PUSH=true
STATAMIC_GIT_SYNC_REMOTE=origin
```

Push over SSH with a deploy key the web user can read:

```env
STATAMIC_GIT_SYNC_SSH_COMMAND="ssh -i /home/deploy/.ssh/id_deploy -o IdentitiesOnly=yes"
```

See what would happen without touching anything:

```bash
php artisan statamic:git-sync:status
php artisan statamic:git-sync --dry-run
```

## How it works

**Nothing happens in the save request.** The subscriber writes a timestamp and queues a job. A broken git setup can never stop somebody from saving their work.

**Debouncing collects an editing session.** Every change pushes the run out by `debounce.seconds` (60 by default). A job that wakes up to find a newer change waiting steps aside, so ten saves in a minute become one commit.

**Two runs never overlap.** The queue job carries Laravel's `WithoutOverlapping` middleware, and the console and scheduled paths take the same cache lock.

**The cache is timing, not truth.** Whatever is committed is read from `git status` at the moment of the run. If the cache is flushed the addon loses the *bundling*, never the *changes* — and a scheduled safety net (`*/15 * * * *`) commits anything left behind. The worst case of a cache outage is a delay of a few minutes.

**Only your content is staged.** Git is asked about the allow list only, and the result is filtered again, so an unrelated change in `app/` can never ride along with a content commit.

**Loop guard.** Every commit carries `Committed-by: statamic-git-sync`. A deploy that pulls does not fire Statamic events, so pulls cannot trigger commits.

**Failures are classified.** A lost network on push retries with backoff. A missing git binary does not, because it will still be missing in thirty seconds. A conflict is rolled back and reported, because it needs a person.

## Configuration

Every option lives in `config/statamic-git-sync.php` and has an `env()` override. The full reference — default, effect and the pitfall of each — is in [docs/configuration.md](docs/configuration.md). The short version:

| Group | What it controls |
|---|---|
| `enabled`, `dry_run`, `strategy` | master switch, rehearsal mode, how changes reach the remote |
| `repository` | path, git binary, timeout, environment (SSH command) |
| `content` | the path allow list and the exclude globs |
| `commit` | message template, trailer, author, signing |
| `pull` / `push` | integrating remote work and publishing |
| `debounce` / `queue` | how long to collect, and where the job runs |
| `safety_net` | the scheduled fallback that makes cache loss harmless |
| `events` | each Statamic event individually |
| `logging` | channel and level |

## Commands

| Command | Purpose |
|---|---|
| `statamic:git-sync` | Commit what is pending now. `--dry-run` reports without writing. |
| `statamic:git-sync:status` | What is pending, and the configuration in effect. |
| `statamic:git-sync:doctor` | Check the whole setup. Exits non-zero when something is broken, so it works as a deploy gate. |

## Events

Listen to these to send notifications, collect metrics, or trigger a deploy:

`SyncStarted` · `SyncCompleted` · `SyncSkipped` · `SyncFailed` · `ConflictDetected` · `PullRequestOpened`

Each carries typed DTOs rather than loose scalars. See [docs/advanced.md](docs/advanced.md).

## How it compares

| | Pro Git Automation | Gitamic | Statamic Git Sync |
|---|---|---|---|
| Needs Statamic Pro | yes | no | no |
| Price | part of Pro | paid | free, MIT |
| Automatic on content change | yes | no | yes |
| Manual git UI in the CP | no | yes | no |
| Debounced commits | no | — | yes |
| Pull request instead of push | no | no | yes |
| Self-check command | no | no | yes |
| Scheduled safety net | no | — | yes |

## Pull request mode

Instead of pushing straight to the branch everybody deploys from, the addon can push the branch the instance is on and open a pull request against another one — so content changes get reviewed like code. The built-in Pro automation cannot do this.

```env
STATAMIC_GIT_SYNC_STRATEGY=pull-request
STATAMIC_GIT_SYNC_PUSH=true
STATAMIC_GIT_SYNC_PR_TOKEN=github_pat_…
STATAMIC_GIT_SYNC_PR_BASE=main
```

It **never checks out another branch** — that would swap the content files on disk underneath the editor who just saved them. So the authoring instance lives on its own branch (`content/staging`), and `base` is where it should be merged. `doctor` refuses the setup when the two are the same.

Within a debounce window the first run opens the pull request and later runs push commits onto it, rather than opening one per save. Details in [docs/configuration.md](docs/configuration.md#pull_request).

## Notifications

Mail out of the box, Slack with one extra package:

```env
STATAMIC_GIT_SYNC_NOTIFICATIONS=true
STATAMIC_GIT_SYNC_NOTIFY_MAIL=ops@example.com
```

Failures, conflicts and opened pull requests by default; successful commits are available but off, because on a busy site that is a channel nobody reads. Details in [docs/configuration.md](docs/configuration.md#notifications).

## Control panel widget

A dashboard widget showing the last few syncs — what was committed, what failed, which pull request it went into. Add it in `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'recent_syncs', 'width' => 50, 'limit' => 5],
],
```

It reads a JSONL file under `storage/`, which survives the `cache:clear` in your deploy. Losing it costs the widget its contents and nothing else — git remains the record of what was committed.

GitLab works too — merge requests instead of pull requests, nested groups included:

```env
STATAMIC_GIT_SYNC_PR_DRIVER=gitlab
STATAMIC_GIT_SYNC_PR_TOKEN=glpat-…
```

## Roadmap

Nothing planned. Gitea and Forgejo would each be one class behind `PullRequestClientContract` — see [docs/advanced.md](docs/advanced.md) if you want one.

## Contributing

Bug reports and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Credits

Built by [Viovenia](https://viovenia.com). Not affiliated with, or endorsed by, Statamic.

## License

MIT — see [LICENSE](LICENSE).
