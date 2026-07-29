# Troubleshooting

Start here:

```bash
php artisan statamic:git-sync:doctor
```

It checks the git binary, the repository, the git identity, the branch, the remote, the content paths, cache lock support, the queue driver and the scheduler — and every finding comes with what to do about it. Most of the page below is what `doctor` would have told you.

## Nothing is being committed

**Is anything pending at all?**

```bash
php artisan statamic:git-sync:status
```

If it says nothing is pending while `git status` shows changes, the changes are outside `content.paths` or caught by `content.exclude`.

**Is the queue running?**

```bash
php artisan queue:work
```

Then save something in the control panel. With the worker stopped, jobs pile up and only the safety net commits — within its cron window.

**Is the addon on?** `enabled=false` and `dry_run=true` both produce a silent do-nothing. `doctor` reports both as warnings.

**Are the events on?** If every entry in `events` is `false`, nothing ever triggers a run.

## It commits on every single save instead of bundling

Debouncing needs a cache store with atomic locks. On a store without them the addon switches debouncing off rather than debounce badly:

```
Cache locks ... WARN  Illuminate\Cache\NullStore cannot hold atomic locks …
```

Set `CACHE_STORE` to `redis`, `memcached`, `database` or `file`.

## Saving an entry is slow

You are on the `sync` queue driver, so the job runs inside the request:

```
Queue driver ... WARN  The sync driver runs jobs inline …
```

Use a real queue connection and run a worker.

## `fatal: not a git repository`

The path is wrong or there is no repository there. Either run `git init`, or point `repository.path` at the actual repository root — the default is `base_path()`, which is not always where the repository lives.

## `Author identity unknown`

git will not commit without one:

```bash
git config user.name "Statamic"
git config user.email "statamic@example.com"
```

Setting `commit.fallback_author` is not enough on its own: that becomes the commit *author*, while git still needs a *committer* identity.

## Pushing fails with a permission error

Run the exact thing the addon runs, as the user PHP runs as:

```bash
sudo -u www-data git -C /var/www/site push --dry-run origin main
```

Common causes: the web user cannot read the deploy key, the key is not on the remote, or the host is not in `known_hosts`. `GIT_SSH_COMMAND` covers all three:

```env
STATAMIC_GIT_SYNC_SSH_COMMAND="ssh -i /home/deploy/.ssh/id_deploy -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
```

## A push hangs until the timeout

git is waiting for credentials nobody will type. The addon sets `GIT_TERMINAL_PROMPT=0` so this fails fast instead — if you overrode `repository.env`, put it back.

## Rebase conflicts keep appearing

The server and the remote both change the same files. The addon aborts and restores the working tree every time, so nothing is lost, but it will keep happening until the divergence is resolved by hand:

```bash
git -C /var/www/site status
git -C /var/www/site pull --rebase
```

If the server should never integrate remote work, set `pull.enabled` to `false`.

## Pull request mode does nothing, or refuses to start

`doctor` answers all four of the usual causes:

- **No token.** `STATAMIC_GIT_SYNC_PR_TOKEN` needs pull request write access on the repository.
- **Pushing is off.** The branch has to reach the forge first; `STATAMIC_GIT_SYNC_PUSH=true`.
- **Head equals base.** The instance is on the branch it is supposed to propose into. Put the authoring instance on its own branch — this mode never checks out another one, on purpose.
- **The slug could not be derived.** A remote that is a local path has no forge behind it; set `pull_request.repository` to `owner/name`.

## A pull request was not opened, but the commit exists

That is the designed outcome of a forge failure: the commit and the push already happened, so nothing is lost and the run is marked retryable. The log line carries the HTTP status and the response body, with credentials redacted.

If it says nothing was opened but there is no error either, `reuse_open` found an existing pull request for the branch and pushed the commits onto it — which is the normal case from the second sync onwards.

## A change was made on disk and never committed

The event path only sees changes made through Statamic. Files edited over SSH or SFTP produce no event. That is what the safety net is for — within its cron window it commits them anyway. If it is not running:

```
Safety net ... WARN  … it has never been seen to run.
```

Add `php artisan schedule:run` to the crontab.

## Commits appear twice, or from a deploy

Check the trailer:

```bash
git log --grep="Committed-by: statamic-git-sync" --oneline
```

Commits without it did not come from this addon. A deploy that pulls does not fire Statamic events, so it cannot trigger a sync — if you see duplicates, something else is committing too.

## Turning up the detail

```env
STATAMIC_GIT_SYNC_LOG_LEVEL=debug
STATAMIC_GIT_SYNC_LOG_CHANNEL=stack
```

Debug level includes the runs that found nothing to commit and the ones that stood aside for a newer change, which is usually the piece that explains the timing.

## Reporting a bug

Please include the output of:

```bash
php artisan statamic:git-sync:doctor
php artisan about --only=environment
```

plus the log lines around the failure. Credentials are already redacted in both.
