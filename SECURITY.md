# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 0.1.x | yes |

While the package is below 1.0, only the latest minor receives fixes.

## Reporting a vulnerability

Please **do not** open a public issue.

Email **info@viovenia.com** with:

- what the issue is and what an attacker could achieve,
- the versions of this package, Statamic and PHP,
- steps to reproduce, if you have them.

You will get an acknowledgement within a few working days, and an assessment with a fix timeline after that. Credit in the release notes if you would like it.

## What this addon touches

Worth knowing when assessing a report:

- **It runs git.** Commands are built as argument arrays and executed without a shell, so a file name cannot break out into a second command. `GIT_TERMINAL_PROMPT=0` stops git from waiting on a credential prompt.
- **It reads and writes the repository** at `repository.path` as the user PHP runs as, limited to `git add` on the configured allow list.
- **It can hold credentials indirectly** — an SSH command or a remote URL with a token. Credentials in remote URLs are redacted from logs and exception messages, but a token stored in `.git/config` is still on disk; a deploy key is the safer arrangement.
- **It commits content authored by control panel users.** File names come from Statamic and end up as git arguments after the `--` separator, so they are always treated as paths.
- **It does not expose HTTP routes**, has no control panel UI, and adds no public endpoints.

## Out of scope

- Statamic core itself — report those to https://statamic.com/security.
- Misconfiguration where the documented default is safe (for example enabling `force_with_lease` and losing commits).
- A git binary or SSH key that the PHP user should not have had access to in the first place.
