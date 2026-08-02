# CLAUDE.md — viovenia/statamic-git-sync

Instructions for AI agents working in this repository. Read this before writing code.

## What this package is

A Statamic addon that commits — and optionally pushes — content changes automatically when they happen in the control panel. MIT licensed, works on Statamic Solo, which is the point: the equivalent core feature is Pro only.

`Viovenia\GitSync\` → `src/`. PHP `^8.2`, `statamic/cms ^5.0 || ^6.0`.

## Commands

```bash
composer run check             # what CI runs: pint --test, phpstan, unit + feature tests
composer run fix               # pint, writing
composer run test              # unit + feature + architecture
composer run test:integration  # real git in a temp directory
composer run analyse           # phpstan level 6
```

Run `composer run fix` before `composer run check`. Always run `check` before saying you are done.

## Never do this

- **Never open `vendor/statamic/cms/src/Git/`.** Statamic is source-available, not MIT, and clause 4 of its licence forbids reusing its code. Reimplementing behaviour is fine; reading that directory as a reference is not. `vendor/statamic/cms/src/Events/` is public API and may be read.
- **Never touch licence or edition checks.** This addon works on Solo because it uses public extension points, not because it circumvents anything. Never fake `STATAMIC_PRO_ENABLED`.
- **Never start a process outside `Services/Process/ProcessRunner`.** No `Process` facade, `exec`, `shell_exec`, `proc_open`, `passthru` anywhere else. `ArchitectureTest` fails the build otherwise.
- **Never call `config()` outside `ServiceProvider` and `GitSyncConfigResolver`.** Everything takes a typed config DTO. Also enforced by `ArchitectureTest`.
- **Never pass an associative array between two classes.** Use a `*DTO` or an enum.
- **Never add a `version` key to `composer.json`.** Composer derives versions from tags.
- **Never widen a dependency constraint to resolve a conflict** without saying so explicitly.
- **Never let an exception escape into a control panel save request.** The subscriber queues and returns; `SyncManager::sync()` never throws.
- **Never edit anything in `vendor/`.**

## Naming

The suffix says what the class does. `ArchitectureTest` enforces the forbidden ones.

| Role | Suffix |
|---|---|
| Interface | `*Contract` |
| Data transfer object | `*DTO` (`final readonly`, in `DataTransferObjects/`) |
| Entry point service | `*Manager` — exactly one, `SyncManager` |
| Access to a resource | `*Repository` |
| Builds an object, chainable | `*Builder` |
| Picks or derives something | `*Resolver` |
| Interchangeable procedure | `*Strategy` |
| Queues work | `*Dispatcher` |
| Executes | `*Runner` |
| Tests a condition | `*Check` |
| Talks to an external service over HTTP | `*Client` |
| Laravel notification | `*Notification` |
| Queue job / listener / command / exception | `*Job` / `*Subscriber` / `*Command` / `*Exception` |
| Event | verb in the past: `SyncCompleted` |
| Enum | a noun: `SyncOutcome` |
| Framework mechanism wrapper | the noun itself: `SyncLock` |

**Forbidden:** `*Factory` (Laravel model factories), `*Provider` (service providers — only `ServiceProvider` may use it), `*Helper`, `*Util`, `*Handler`. If no suffix fits, the class is cut wrong.

## Layout

```
src/
├── ServiceProvider.php          the only place that wires and reads config
├── Concerns/                    FiresSyncEvents, WritesSyncLog, RedactsCredentials
├── Console/Commands/            statamic:git-sync[:status|:doctor]
├── Contracts/                   one interface per moving part
├── DataTransferObjects/         Sync/ Content/ Git/ Config/ Diagnostics/
├── Enums/                       SyncOutcome, SyncTrigger, FileStatus, ContentEvent, …
├── Events/                      the public extension points
├── Exceptions/                  GitSyncException + subclasses, each with a FailureType
├── Facades/GitSync.php          → SyncManagerContract
├── Jobs/SyncContentJob.php      thin: middleware + one call
├── Repositories/                GitRepository, DebounceRepository, NullDebounceRepository
├── Services/                    Process/ Git/ Sync/ Content/ Diagnostics/
├── Strategies/                  CommitSyncStrategy, PullRequestSyncStrategy
├── Notifications/               mail + optional Slack, sent inline on purpose
├── Subscribers/                 ContentEventSubscriber, NotificationSubscriber, HistorySubscriber
├── Widgets/RecentSyncsWidget    extends Statamic\Widgets\Widget — no interface exists
└── Support/GitSyncConfigResolver.php
```

## The chain

```
Statamic event → ContentEventSubscriber → SyncDispatcher → SyncContentJob
                                                                 │
                     SyncManager ←──────────────────────────────┘
                          │ lock, events, failure policy
                     CommitSyncStrategy → GitRepository → ProcessRunner → git
```

Each arrow is a contract, bound as a singleton in `ServiceProvider`.

## Invariants worth protecting

**Git is the state; the cache is only timing.** What gets committed is read from `git status` at the moment of the run. Losing the debounce marker costs bundling, never changes. The scheduled safety net (`statamic:git-sync --safety-net`, default `*/15 * * * *`) is what makes that guarantee hold. Do not make any code path depend on the cache being intact.

**Failure policy lives in `SyncManager::handleFailure()`, nowhere else.** Strategies throw; the manager decides what a failure means, fires the event, writes the log and returns a `SyncResultDTO`. Retryability comes from `FailureType`, not from guessing at a message.

**Concurrency uses Laravel's own tools.** `Skip`, `WithoutOverlapping`, `ThrottlesExceptions` on the job; `SyncLock` for the console and scheduler paths, which the job middleware does not see. Do not hand-roll a second mechanism.

**`middleware()` gets no dependency injection.** Laravel calls it directly. That is why `SyncJobOptionsDTO` travels with the job — do not "fix" it with a facade or a `config()` call.

**Degrade rather than fail.** No lock-capable cache store means `NullDebounceRepository` and a warning from `doctor`, not an exception.

## Tests

`tests/Unit` (faked), `tests/Feature` (`AddonTestCase`, queue and event assertions), `tests/Integration` (group `integration`, real `git init`), `tests/Architecture` (the rules above).

`Process::preventStrayProcesses()` is on for the whole suite. Only the integration group turns it off. If a unit test needs a real process, the abstraction has a hole — fix the hole, not the test.

Every change lands with its test. Use `#[WithConfig]` from testbench for anything that has to be set before boot; `$this->configure()` in `TestCase` only helps for values read after boot.

## Releases

SemVer from git tags. Currently below 1.0, where **Composer treats every minor as breaking** — `^0.1` does not allow `0.2`. Changelog entries are hand-written and land in the same pull request as the change.

`main` is protected: work on a branch and open a pull request. Merges are squash-only.

## Pull request mode

`PullRequestSyncStrategy` pushes the branch the instance is on and opens a pull request against `pull_request.base`. **It never checks out another branch** — that would swap content files on disk underneath the editor who just saved them. `PullRequestCheck` fails the setup when head and base are the same. Do not "fix" this by adding a checkout.

The forge lives behind `PullRequestClientContract`; `GithubPullRequestClient` is the only implementation. A run that committed and pushed but failed to open the pull request is **retryable** — the work is safe on the branch.

## Notifications

`NotificationSubscriber` listens to the addon's own events — nothing is baked into `SyncManager`. Only the configured events are subscribed at all.

Sent **inline, never queued**: a failure notification that depends on the queue is useless exactly when the queue is what broke. Nothing here runs in a control panel request, so blocking briefly costs nobody anything. Do not "improve" this with `ShouldQueue`.

Slack is optional. `laravel/slack-notification-channel` is a **dev** dependency so the code can reference `SlackMessage` and the tests can cover it; `class_exists()` guards keep it working for consumers who do not install it. Never move it to `require`.

A conflict fires both `ConflictDetected` and `SyncFailed`; only the conflict notification is sent.

## History and the widget

`HistorySubscriber` writes a JSONL line per run to `storage/statamic-git-sync/history.jsonl`; `RecentSyncsWidget` reads it. A file, not the cache — `cache:clear` is part of most deploys. A file, not a table — a flat-file Solo site often has no database.

**It is a display, never a source of truth.** `FileHistoryRepository` catches everything and checks writability before writing, so a sync can never fail because its history could not be recorded. `HistoryCheck` reports an unwritable path as a *warning*. Do not make any of this throw.

Runs that committed nothing are not recorded — the safety net would otherwise fill the file on a quiet site.

`RecentSyncsWidget extends Statamic\Widgets\Widget` is the third framework-demanded inheritance; Statamic discovers widgets by that base class and offers no interface.

## Roadmap

Empty. Gitea and Forgejo would each be one `PullRequestClientContract` implementation, but nobody has asked. Do not build ahead of it.
