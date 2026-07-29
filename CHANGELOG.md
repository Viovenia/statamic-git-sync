# Changelog

All notable changes to this package are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **While the version is below 1.0**, Composer treats every minor as breaking: `^0.1` does **not** allow `0.2`. Read each minor entry as a major one.

## [Unreleased]

### Added

- **Pull request mode** (`strategy => 'pull-request'`). Commits the change, pushes the branch this instance is on, and opens a pull request against a configurable base branch instead of pushing straight to it — something the built-in Pro automation cannot do. Reuses an already open pull request by default, so a busy editor produces one pull request rather than one per debounce window.
- `PullRequestClientContract` with a GitHub REST implementation, so a second forge is a new class rather than a change to the strategy.
- `PullRequestOpened` event, fired only when a pull request is actually created.
- `PullRequestCheck` in `doctor`: token present, pushing enabled, repository slug derivable, and the head branch distinct from the base — the mistake everybody makes on their first afternoon with this mode.
- `SyncResultDTO::$pullRequest`, and the pull request in the log context of a run.
- `GitRepositoryContract::remoteUrl()`.

### Changed

- `FailureType` gained `PullRequestFailed`, which is retryable: when the forge call fails the commit and push have already succeeded, so a retry only re-attempts the proposal.

## [0.1.0] - 2026-07-29

First release.

### Added

- Automatic commits on Statamic content events, individually configurable per event.
- Trailing-edge debouncing: an editing session becomes one commit instead of one commit per save.
- A scheduled safety net that commits anything the event path missed, so a flushed cache or a stopped queue worker costs a delay rather than a commit.
- Optional `pull --rebase` (or merge) and `push`, with conflicts aborted and the working tree restored.
- Path allow list plus exclude globs, so an unrelated change can never ride along with a content commit.
- Commit message templates with `{summary}`, `{count}`, `{user}`, `{date}` and `{files}`, and a `Committed-by: statamic-git-sync` trailer as a loop guard.
- The control panel user is credited as the commit author, with a configurable fallback for console and scheduled runs.
- `statamic:git-sync`, `statamic:git-sync:status` and `statamic:git-sync:doctor`. The doctor exits non-zero on problems, so it works as a deploy gate.
- Ten health checks covering the git binary, repository, identity, branch, remote, content paths, cache locks, queue driver, safety net and configuration.
- `SyncStarted`, `SyncCompleted`, `SyncSkipped`, `SyncFailed` and `ConflictDetected` events, each carrying typed DTOs.
- Contracts for every layer, so commit messages, path filtering, sync strategies and health checks can all be replaced without forking.
- Credentials in remote URLs are redacted from logs and exception messages.
- Support for Statamic 5 and 6 on PHP 8.2, 8.3 and 8.4.

[Unreleased]: https://github.com/Viovenia/statamic-git-sync/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Viovenia/statamic-git-sync/releases/tag/v0.1.0
