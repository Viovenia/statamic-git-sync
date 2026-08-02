# Changelog

All notable changes to this package are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **While the version is below 1.0**, Composer treats every minor as breaking: `^0.1` does **not** allow `0.2`. Read each minor entry as a major one.

## [Unreleased]

## [0.1.0] - 2026-08-02

First release.

### Syncing

- Automatic commits on Statamic content events — entries, terms, taxonomies, globals, navs, assets, collections, blueprints, fieldsets, forms, users, roles and sites, each switchable on its own.
- Trailing-edge debouncing, so an editing session becomes one commit rather than one commit per save. Falls back to committing immediately, with a warning from `doctor`, when the cache store cannot hold atomic locks.
- A scheduled safety net that commits whatever the event path missed. This is what makes a flushed cache or a stopped queue worker cost a delay rather than a commit.
- Only the configured paths are committed. Not "mostly": `git commit --only` restricts each commit to the paths that run staged, so a file somebody staged by hand stays out of it and stays staged.
- Nothing is committed on top of a half-finished merge, rebase or cherry-pick. Burying somebody's unresolved conflict under an automated commit is worse than waiting for them.
- Optional `pull --rebase` or merge before pushing, with conflicts aborted and the working tree restored exactly as it was found.
- Commit messages from a template — `{summary}`, `{count}`, `{user}`, `{date}`, `{files}` — with a `Committed-by: statamic-git-sync` trailer as the loop guard.
- The control panel user is credited as the commit author, with a configurable fallback for console and scheduled runs.

### Pull request mode

- `strategy => 'pull-request'` pushes the branch the instance is on and opens a pull request against a base branch instead of pushing straight to it — something the built-in Pro automation cannot do.
- It never checks out another branch: that would swap content files on disk underneath the editor who just saved them. `doctor` refuses a setup whose head and base are the same.
- Reuses an already open pull request, so an active editor produces one pull request rather than one per debounce window.
- GitHub and GitLab, each behind `PullRequestClientContract`. GitLab means merge requests: URL-encoded project paths including nested groups, `PRIVATE-TOKEN` authentication, `source_branch` / `target_branch`, and drafts as a title prefix.

### Telling you about it

- Notifications for failures, conflicts and opened pull requests, plus successful commits if you want them. Mail out of the box, Slack with `laravel/slack-notification-channel`. Sent inline rather than queued, because a failure notification that depends on the queue is useless exactly when the queue is what broke.
- A "Recent content syncs" control panel widget listing which files each run touched, their status, the `+/−` line counts and a link to the commit or pull request. Backed by a JSONL file under `storage/` that survives the `cache:clear` in your deploy.
- Six events — `SyncStarted`, `SyncCompleted`, `SyncSkipped`, `SyncFailed`, `ConflictDetected`, `PullRequestOpened` — each carrying typed DTOs rather than loose scalars.
- English and German translations for the control panel. Console output stays English on purpose: an error message in a deploy log is worth more when it can be searched for.

### Finding out what is wrong

- `statamic:git-sync`, `statamic:git-sync:status` and `statamic:git-sync:doctor`. The doctor exits non-zero on problems, so it works as a deploy gate.
- Thirteen health checks: configuration, git binary, repository state, git identity, branch, remote, content paths, pull request setup, notifications, history file, cache lock support, queue driver and the safety net. Each one says what to do about what it found.
- Credentials in remote URLs are redacted from logs and exception messages.
- Failures are classified rather than guessed at: a lost network on push retries with backoff, a missing git binary does not, and a conflict never does because it needs a person.

### For anyone extending it

- Sixteen contracts, one per moving part. Commit messages, path filtering, sync strategies, forge clients and health checks can all be replaced from your own service provider without forking.
- Nothing throws into a control panel save request. `SyncManager::sync()` never throws; the subscriber only queues.

### Supported

- PHP 8.2, 8.3 and 8.4. Statamic 5 and 6. Works on Statamic Solo, which is the point.

[Unreleased]: https://github.com/Viovenia/statamic-git-sync/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Viovenia/statamic-git-sync/releases/tag/v0.1.0
