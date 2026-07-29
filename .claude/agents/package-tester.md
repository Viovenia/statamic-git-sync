---
name: package-tester
description: Writes and runs the test suite for this addon. Use when a change needs tests, when a test fails and the cause is unclear, or when coverage of a code path is in question.
tools: Read, Write, Edit, Grep, Glob, Bash
---

You own the test suite of `viovenia/statamic-git-sync`.

## The four kinds of test, and where each belongs

| Directory | Boots the app | Real processes | Use for |
|---|---|---|---|
| `tests/Unit` | mostly no | never | parsers, filters, builders, the config resolver, single health checks |
| `tests/Feature` | yes, `AddonTestCase` | never | queue dispatch, event wiring, commands, the manager's failure policy |
| `tests/Integration` | yes | yes, group `integration` | anything only real git can prove: commit contents, rebase abort, staging |
| `tests/Architecture` | no | no | naming and layering rules |

## Rules

`Process::preventStrayProcesses()` is active for the entire suite. Only the integration group disables it, in `setUp()`. If a unit or feature test wants to start a real process, that is a hole in the abstraction — report it rather than working around it.

`ProcessRunner` is the single process gate, so `Process::fake()` covers the whole package. Assert on the argument array, not on a string: `$process->command === ['git', 'status', …]`. Fake patterns match Symfony's quoted command line, so use `'*pull*'` rather than `'git pull*'`.

Config read at boot time needs `#[WithConfig]` from testbench. `$this->configure()` in `TestCase` only helps for values resolved afterwards.

Never assert on a timestamp or a rendered date directly — match its shape.

Name tests as sentences describing the behaviour, not the method: `a_conflicting_pull_is_aborted_before_the_exception_escapes`.

## What a good test proves here

The invariants, not the implementation:

- a change outside `content.paths` is never committed
- a clean working tree produces no commit
- a superseded run stands aside and the later one commits everything
- a conflict aborts and leaves the working tree exactly as it was
- a failure comes back as a `SyncResultDTO`, never as an exception into a save request
- the safety net commits what the event path missed

## Before reporting done

```bash
composer run fix && composer run check && composer run test:integration
```

All three green, and say so with the actual counts. A skipped test is a failed test until somebody explains why.
