# Configuration

Every option lives in `config/statamic-git-sync.php` and can be overridden with an environment variable. The file is read exactly once, at boot, into a typed object tree — a wrong value fails there, with a message naming the key, instead of surfacing later as something unrelated.

```bash
php artisan vendor:publish --tag=statamic-git-sync-config
```

## Top level

| Key | Env | Default | Effect |
|---|---|---|---|
| `enabled` | `STATAMIC_GIT_SYNC_ENABLED` | `true` | Master switch. When false nothing is subscribed and nothing is queued. |
| `dry_run` | `STATAMIC_GIT_SYNC_DRY_RUN` | `false` | Reads and reports, never writes. The fastest way to check your allow list. |
| `strategy` | `STATAMIC_GIT_SYNC_STRATEGY` | `commit` | How changes reach the remote. Only `commit` ships today; see [advanced.md](advanced.md). |

**Pitfall:** `enabled=false` is not the same as uninstalling — the commands still exist and report that the addon is switched off.

## `repository`

| Key | Env | Default | Effect |
|---|---|---|---|
| `path` | `STATAMIC_GIT_SYNC_PATH` | `null` | Repository root. Null means `base_path()`. |
| `git_binary` | `STATAMIC_GIT_SYNC_BINARY` | `git` | Set an absolute path when git is not on the PHP user's `PATH`. |
| `timeout` | `STATAMIC_GIT_SYNC_TIMEOUT` | `60` | Seconds a single git call may take. |
| `env` | — | `GIT_TERMINAL_PROMPT=0` | Environment for every git call. `GIT_SSH_COMMAND` goes here. |

**Pitfall:** `GIT_TERMINAL_PROMPT=0` is deliberate. Without it a git call that wants credentials hangs until the timeout instead of failing.

## `content`

| Key | Default | Effect |
|---|---|---|
| `paths` | `content`, `resources/blueprints`, `resources/fieldsets`, `resources/forms`, `users` | The allow list. Nothing outside it is ever staged. |
| `exclude` | `*.lock`, `content/**/.DS_Store` | Glob patterns applied after the allow list. |

Pattern rules: `*` matches within one path segment, `**` crosses directories, and a pattern **without a slash matches at any depth** — `*.lock` catches a lock file three directories down, the way `.gitignore` behaves.

**Pitfall:** a typo in `paths` fails silently — nothing matches, so nothing is ever committed. `doctor` warns about configured paths that do not exist.

## `commit`

| Key | Env | Default | Effect |
|---|---|---|---|
| `message_template` | `STATAMIC_GIT_SYNC_MESSAGE` | `content: {summary}` | Placeholders: `{summary}` `{count}` `{user}` `{date}` `{files}`. |
| `summary_max_files` | `STATAMIC_GIT_SYNC_SUMMARY_MAX_FILES` | `3` | File names listed before "and N more". |
| `trailer` | `STATAMIC_GIT_SYNC_TRAILER` | `Committed-by: statamic-git-sync` | Appended after a blank line. This is the loop guard. |
| `author_from_user` | `STATAMIC_GIT_SYNC_AUTHOR_FROM_USER` | `true` | Credit the control panel user as the commit author. |
| `fallback_author.name` / `.email` | `STATAMIC_GIT_SYNC_AUTHOR_NAME` / `_EMAIL` | `Statamic Git Sync` / `git-sync@localhost` | Used for console, scheduled and safety-net runs. |
| `allow_empty` | `STATAMIC_GIT_SYNC_ALLOW_EMPTY` | `false` | Commit even with nothing to commit. Almost never useful. |
| `sign` | `STATAMIC_GIT_SYNC_SIGN` | `false` | Pass `--gpg-sign`. Needs a key usable without a passphrase prompt. |

**Pitfall:** an unknown placeholder is left in the message verbatim rather than treated as an error. `doctor` reports it as a warning.

**Pitfall:** removing the trailer removes the only marker that says a commit was automated. Keep it unless you have a reason.

## `pull`

| Key | Env | Default | Effect |
|---|---|---|---|
| `enabled` | `STATAMIC_GIT_SYNC_PULL` | `false` | Integrate remote work before pushing. |
| `strategy` | `STATAMIC_GIT_SYNC_PULL_STRATEGY` | `rebase` | `rebase` or `merge`. |
| `on_conflict` | `STATAMIC_GIT_SYNC_ON_CONFLICT` | `abort` | `abort` restores and logs; `throw` does the same and fails the job loudly. |

Either way the rebase or merge is aborted first — the working tree is never left half-integrated.

**Pitfall:** enabling `pull` on a server whose working tree is managed by a deploy pipeline invites conflicts that no automation can resolve. Leave it off unless the server genuinely shares a branch.

## `push`

| Key | Env | Default | Effect |
|---|---|---|---|
| `enabled` | `STATAMIC_GIT_SYNC_PUSH` | `false` | Publish after committing. |
| `remote` | `STATAMIC_GIT_SYNC_REMOTE` | `origin` | |
| `branch` | `STATAMIC_GIT_SYNC_BRANCH` | `null` | Null pushes the current branch. |
| `force_with_lease` | `STATAMIC_GIT_SYNC_FORCE_WITH_LEASE` | `false` | Only if you know exactly why. |

**Pitfall:** pinning `branch` while the server is on a different one means every push targets a branch nobody is looking at. `doctor` warns when the two disagree.

## `debounce`

| Key | Env | Default | Effect |
|---|---|---|---|
| `enabled` | `STATAMIC_GIT_SYNC_DEBOUNCE` | `true` | |
| `seconds` | `STATAMIC_GIT_SYNC_DEBOUNCE_SECONDS` | `60` | Wait after the last change before committing. |

Each change pushes the run out again, so a whole editing session becomes one commit. A job that wakes to find a newer change waiting steps aside.

**Pitfall:** debouncing needs a cache store with atomic locks. Without one it is switched off automatically — you get a commit per change, and `doctor` says why.

## `queue`

| Key | Env | Default | Effect |
|---|---|---|---|
| `connection` | `STATAMIC_GIT_SYNC_QUEUE_CONNECTION` | `null` | Null uses the app default. |
| `name` | `STATAMIC_GIT_SYNC_QUEUE` | `null` | A dedicated queue keeps syncs behind or ahead of other work. |
| `tries` | `STATAMIC_GIT_SYNC_TRIES` | `3` | Attempts for retryable failures only. |
| `backoff` | — | `[30, 120, 300]` | Seconds before each retry. |

Only network-shaped failures are retried. A missing binary, a broken configuration or a conflict fails once and stays failed, because the second attempt would fail identically.

**Pitfall:** the `sync` queue driver runs the job inline. Debouncing becomes meaningless and every commit happens inside the control panel request. `doctor` warns about it.

## `safety_net`

| Key | Env | Default | Effect |
|---|---|---|---|
| `enabled` | `STATAMIC_GIT_SYNC_SAFETY_NET` | `true` | |
| `cron` | `STATAMIC_GIT_SYNC_SAFETY_NET_CRON` | `*/15 * * * *` | |

A scheduled run that commits whatever the event path missed — a flushed cache, a stopped worker, a change made on disk. It is idempotent: a clean tree means no commit and no log noise.

This is what makes a cache outage a delay of at most one cron window rather than a lost commit. **Leave it on.**

**Pitfall:** it needs `php artisan schedule:run` in the crontab. `doctor` warns when it has never been seen to run.

## `logging`

| Key | Env | Default | Effect |
|---|---|---|---|
| `channel` | `STATAMIC_GIT_SYNC_LOG_CHANNEL` | `null` | Null uses the app default channel. |
| `level` | `STATAMIC_GIT_SYNC_LOG_LEVEL` | `info` | Level for ordinary runs. Failures always log at `error`. |

Credentials in remote URLs are redacted before anything is written.

## `events`

Every supported Statamic event can be switched on or off individually. Events your Statamic version does not ship are ignored, so the list is safe across majors.

Defaults: everything on, except `submission_saved` and `submission_deleted` — on a busy site those would commit on every contact form send.

**Pitfall:** switching all events off leaves an addon that never does anything. `doctor` reports that as a warning.
