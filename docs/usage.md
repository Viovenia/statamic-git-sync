# Usage

## Day to day

Nothing. That is the point. Editors work in the control panel, and the commits appear.

```
* a4f2c11 content: home.md, about.md
* 91be004 content: pages.yaml
* 3c7d8e2 content: gruppen-foto.jpg, and 4 more
```

Each commit looks like this in full:

```
content: home.md, about.md

Committed-by: statamic-git-sync
```

with the control panel user as author and the repository's git identity as committer:

```
Author:    Marcel Wagner <marcel@example.com>
Commit:    Statamic <statamic@example.com>
```

## The commands

### `statamic:git-sync`

Commits whatever is pending inside the configured paths, right now.

```bash
php artisan statamic:git-sync
php artisan statamic:git-sync --dry-run
```

It takes the same lock as the queued job, so running it while an editor is saving is safe. It is also what the scheduler runs as the safety net.

Exit codes: `0` when it committed, found nothing to commit, or skipped; `1` on failure or conflict — usable in a script.

### `statamic:git-sync:status`

What is pending, and the configuration in effect.

```
Repository ................................ /var/www/site
Enabled ................................... yes
Strategy .................................. commit
Debounce .................................. 60s
Push ...................................... origin current branch
Watched paths ............................. content, resources/blueprints, users

  3 file(s) pending:

content/collections/pages/home.md .......... modified
content/collections/pages/about.md ......... added
content/globals/settings.yaml .............. modified
```

### `statamic:git-sync:doctor`

Checks the whole setup and says what to do about anything broken.

```
Configuration ............. OK    strategy=commit, events=31, debounce=60s, push=origin
Git binary ................ OK    git version 2.50.1
Repository ................ OK    /var/www/site
Git identity .............. OK    Statamic <statamic@example.com>
Branch .................... OK    main
Remote .................... OK    origin exists
Content paths ............. OK    content, resources/blueprints, users
Cache locks ............... OK    Supported by Illuminate\Cache\RedisStore
Queue driver .............. OK    redis (redis)
Safety net ................ WARN  Scheduled for */15 * * * *, but it has never been seen to run.
  ↳ Make sure `php artisan schedule:run` is in the crontab of this server.
```

Exit codes: `1` when anything is an error, `0` with warnings. `--strict` makes warnings fail too, which is what you want in a deploy pipeline:

```bash
php artisan statamic:git-sync:doctor --strict || exit 1
```

## Trying it out safely

1. `dry_run` on, or `--dry-run`. Nothing is written.
2. `php artisan statamic:git-sync:status` — check the allow list matches what you expect.
3. `dry_run` off, `push` off. Commits stay local; inspect them with `git log`.
4. `push` on once the commits look right.

## Reading the log

One line per run:

```
[info] [statamic-git-sync] sync committed {"trigger":"content_event","author":"Marcel <marcel@example.com>",
        "dry_run":false,"requested_at":1785326400,"outcome":"committed","files":2,
        "commit":"a4f2c11","pushed":"origin main","reason":null,"duration_ms":184}
```

Failures log at `error` with the failing command, its exit code and its output — credentials redacted.

Nothing-to-commit runs log at `debug`, so a quiet site does not fill the log.

## Turning it off

Temporarily:

```env
STATAMIC_GIT_SYNC_ENABLED=false
```

For one environment, in `config/statamic-git-sync.php`:

```php
'enabled' => env('STATAMIC_GIT_SYNC_ENABLED', ! app()->environment('local')),
```
