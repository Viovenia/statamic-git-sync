# Contributing

Bug reports, ideas and pull requests are all welcome.

## Getting set up

```bash
git clone git@github.com:Viovenia/statamic-git-sync.git
cd statamic-git-sync
composer install
composer run check
```

`check` is exactly what CI runs: style, static analysis, unit and feature tests. The integration tests are separate because they need a real git binary:

```bash
composer run test:integration
```

## The rules this code follows

These are not preferences — the test suite enforces them.

**Every class says what it is.** `*Contract` for interfaces, `*DTO` for data transfer objects, `*Repository`, `*Builder`, `*Resolver`, `*Strategy`, `*Dispatcher`, `*Runner`, `*Check`, `*Job`, `*Command`, `*Exception`. `*Factory`, `*Provider`, `*Helper`, `*Util` and `*Handler` are forbidden: the first two mean something else in Laravel, the rest mean nothing.

**No loose arrays between classes.** Every signature takes and returns a DTO or an enum. `array` appears only at the system boundary — reading the config file, parsing process output — and is converted immediately.

**One gate to the outside.** No `Process`, `exec`, `shell_exec` or `proc_open` outside `Services\Process\ProcessRunner`. No git verb outside `Repositories\GitRepository`.

**One place reads configuration.** Only `ServiceProvider` and `GitSyncConfigResolver` may touch `config()`. Everything else is handed a typed DTO.

**No inheritance except where the framework demands it.** `ServiceProvider extends AddonServiceProvider` and the exception hierarchy. Everything else implements a contract and composes.

**Nothing throws into a save request.** The subscriber queues a job and returns. A broken git setup must never stop somebody saving their work.

Read `CLAUDE.md` for the full picture — it is written for AI agents but it is the shortest accurate description of the architecture.

## The clean-room rule

Statamic is source-available, not MIT, and its licence forbids reusing its code in other projects.

- **Never open `vendor/statamic/cms/src/Git/`**, and never use it as a reference.
- Reimplementing *behaviour* is fine. Copying code, class structure or patterns is not.
- `vendor/statamic/cms/src/Events/` is public API and may be read to verify event class names.
- Never touch licence or edition checks. This addon works on Solo because it uses public extension points, not because it circumvents anything.

## Tests

Every change lands with its test.

- **Unit** — fast, everything faked. `Process::fake()` covers the whole package because there is only one process gate.
- **Feature** — boots the app through `AddonTestCase`. Queue and event assertions live here.
- **Integration** — group `integration`, real `git init` in a temporary directory. Reserved for things only real git can prove.
- **Architecture** — the rules above, enforced by reflection and token scanning.

`Process::preventStrayProcesses()` is on for the whole suite. If a test starts a real process outside the integration group, that is a hole in the abstraction and the test will say so.

## Pull requests

1. Branch from `main`.
2. Add the changelog entry **in the same pull request** as the change. Written at release time it is a `git log` summary with extra steps.
3. `composer run check` and `composer run test:integration` both green.
4. Say what breaks. Below 1.0 every minor is breaking for consumers, so a behaviour change needs to be visible in the changelog.

Merges are squash-only, so a tidy branch history is not required — a clear pull request title is.

## Releasing

Maintainers only. **Deciding to release stays a human act** — the workflow only turns a tag you pushed into a GitHub release, it never decides that something is ready.

1. Move the `Unreleased` entries into a `## [0.2.0] - YYYY-MM-DD` heading.
2. Update the link definitions at the bottom of `CHANGELOG.md`.
3. Open a pull request with those two changes and merge it. `main` is protected, so this is not optional.
4. Tag the merge commit and push it:

```bash
git checkout main && git pull
git tag -a v0.2.0 -m "v0.2.0"
git push origin v0.2.0
```

The `Release` workflow then:

- re-runs `composer validate --strict`, `check` and `test:integration` on the tagged commit, because a tag on a red build is a release nobody can trust;
- refuses if `CHANGELOG.md` has no section matching the tag, or if that section is empty;
- creates the GitHub release with that section as its notes, marking `-alpha`, `-beta` and `-rc` tags as pre-releases.

Composer derives versions from tags — there is deliberately no `version` key in `composer.json`.

**Tagging is still not publishing.** The workflow creates a *GitHub* release. Until the repository is public and submitted to Packagist, `composer require viovenia/statamic-git-sync` finds nothing. See the table in the release-manager agent notes.

### If the workflow fails

The tag exists but no release was created. Fix whatever it complained about, then delete and re-push the tag:

```bash
git tag -d v0.2.0 && git push origin :refs/tags/v0.2.0
```

Only ever do that for a tag whose release was never published.

## Before the repository goes public

- [ ] Ask Statamic (support@statamic.com or Discord) whether a free addon that commits on content events via public events is fine by them. It costs nothing and removes the only open question.
- [ ] Re-read the clean-room rule above and confirm no Statamic code was used as a template.
- [ ] Decide on Packagist and the Statamic marketplace listing (the marketplace lists free addons too).
