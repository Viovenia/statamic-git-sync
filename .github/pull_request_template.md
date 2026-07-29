## What changed

<!-- One or two sentences. What does this do that the code did not do before? -->

## Why

<!-- The problem, not the solution. What breaks or annoys without this? -->

## Checklist

- [ ] `composer run check` is green
- [ ] `composer run test:integration` is green (or the change cannot affect it)
- [ ] Tests cover the new behaviour, including the failure case
- [ ] `CHANGELOG.md` has an entry under `Unreleased` — in *this* pull request, not at release time
- [ ] Documentation updated: `docs/configuration.md` for a new config key, `docs/usage.md` for a command, `docs/advanced.md` for a contract or event

## Backward compatibility

<!--
Tick one. Remember that below 1.0 Composer treats every minor as breaking:
`^0.1` does NOT allow `0.2`.
-->

- [ ] No observable change for consumers (patch)
- [ ] Adds something, breaks nothing (minor)
- [ ] Changes or removes something a consumer can depend on — config key, contract, event payload, command exit code, commit message default (minor, and breaking for them; described below)

<!-- If breaking: what breaks, and what should people do about it? -->

## Clean room

- [ ] No code, structure or pattern was taken from `vendor/statamic/cms/src/Git/` — see `CONTRIBUTING.md`
