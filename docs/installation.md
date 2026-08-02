# Installation

## Where this works

This addon commits files on the server it runs on. That needs a **persistent filesystem holding a real git checkout** — which is what a flat-file Statamic site usually has, and what some modern hosts deliberately do not.

| Setup | Works | Why |
|---|---|---|
| VPS, managed or shared hosting with a checkout | yes | The case this is built for. |
| Docker with `content/` **and** `.git` on a mounted volume, one replica, git in the image | yes | The state survives the container. |
| Docker from an immutable image, no volume | no | Nothing to commit into, and the layer is gone on the next deploy. |
| Laravel Cloud | no | See below. |
| Laravel Cloud or containers with Statamic's Eloquent driver | not applicable | Content lives in the database. There are no files to version, so leave the addon off. |

### Why not Laravel Cloud

From Laravel Cloud's own documentation:

> Environment filesystems are ephemeral, meaning files may not persist across requests or jobs, and are reset with new deployments. Each replica has its own filesystem, so it should be treated as temporary, unshared disk space.

Worth being clear about what that means: **flat-file content editing does not survive there at all**, with or without this addon. An entry saved in the control panel lands in one replica's filesystem and is gone on the next deploy. The addon is not the limitation — it only makes the limitation visible, because `statamic:git-sync:doctor` reports the missing repository instead of failing quietly.

If you are on such a platform and want to keep editing content, the answer is Statamic's Eloquent driver — content in the database — and then this addon has nothing to do.

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer |
| Statamic | 5 or 6 |
| Queue | any driver other than `sync` — with `sync`, git runs inside the save request |
| Cache | a store with atomic locks (`redis`, `memcached`, `database`, `file`) for debouncing |
| Scheduler | `php artisan schedule:run` in the crontab, for the safety net |
| Git | on the `PATH` of the user running PHP, or configured by absolute path |

The addon degrades rather than breaks when some of these are missing: without lock support it commits on every change instead of debouncing, and says so in `doctor`.

## Install

```bash
composer require viovenia/statamic-git-sync
```

The service provider registers itself. Nothing else is required for local commits with the defaults.

## Publish the config

```bash
php artisan vendor:publish --tag=statamic-git-sync-config
```

This writes `config/statamic-git-sync.php`. Every option is documented inline and in [configuration.md](configuration.md).

## Check the setup

```bash
php artisan statamic:git-sync:doctor
```

It reports on the git binary, the repository, the git identity, the branch, the remote, the content paths, cache locks, the queue driver and the scheduler — each with what to do about it. It exits non-zero when something is broken, which makes it usable as a deploy gate:

```bash
php artisan statamic:git-sync:doctor || exit 1
```

## The repository needs an identity

git refuses to commit without one:

```bash
git config user.name "Statamic"
git config user.email "statamic@example.com"
```

The commit *author* is the control panel user who made the change; this identity is the *committer*. Both appear in `git log`.

## Pushing over SSH

The web user needs a key it can read, and git must not try to prompt:

```env
STATAMIC_GIT_SYNC_PUSH=true
STATAMIC_GIT_SYNC_SSH_COMMAND="ssh -i /home/deploy/.ssh/id_deploy -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
```

Test it as the web user before trusting it:

```bash
sudo -u www-data git -C /var/www/site push --dry-run origin main
```

Pushing over HTTPS with a token in the remote URL also works. The addon redacts credentials from logs and error messages, but a token in `.git/config` is still a token on disk — a deploy key is the better trade.

## The queue worker

```bash
php artisan queue:work --queue=default
```

Use a process manager (systemd, Supervisor, Horizon). If the worker stops, the safety net still commits within its cron window — you lose promptness, not changes.

## The scheduler

```cron
* * * * * cd /var/www/site && php artisan schedule:run >> /dev/null 2>&1
```

This is what runs the safety net. `doctor` warns if it has never been seen to run.

## Deploying

The addon commits on the server, so the server's working tree can diverge from the branch your deploy pushes. Two setups that work:

1. **Content lives on the server.** Deploys pull with `--rebase` and the server is allowed to push. Enable `push` and `pull`.
2. **Content is committed on the server, pushed by nobody.** The server commits locally; you pull those commits from it or run a periodic push by hand. Leave `push` off.

What does not work is a deploy that force-resets the working tree: it discards content commits. Whatever the setup, `git status` on the server should be clean after a deploy.
