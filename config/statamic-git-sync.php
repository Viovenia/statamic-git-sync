<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | The master switch. When false, nothing is subscribed, nothing is queued
    | and the artisan commands report that they did nothing. Useful on local
    | machines and on any environment where the deploy owns the repository.
    |
    */

    'enabled' => env('STATAMIC_GIT_SYNC_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dry run
    |--------------------------------------------------------------------------
    |
    | Reads the working tree and logs what would happen, without staging,
    | committing or pushing anything. The fastest way to find out whether your
    | path allow list matches what you think it matches.
    |
    */

    'dry_run' => env('STATAMIC_GIT_SYNC_DRY_RUN', false),

    /*
    |--------------------------------------------------------------------------
    | Strategy
    |--------------------------------------------------------------------------
    |
    | How changes reach the remote. "commit" commits locally and optionally
    | pushes. Additional strategies can be registered from your own service
    | provider — see docs/advanced.md.
    |
    */

    'strategy' => env('STATAMIC_GIT_SYNC_STRATEGY', 'commit'),

    /*
    |--------------------------------------------------------------------------
    | Repository
    |--------------------------------------------------------------------------
    |
    | path        Absolute path to the repository. Null uses base_path().
    | git_binary  Absolute path if git is not on the PATH of the PHP user.
    | timeout     Seconds a single git invocation may take before it is killed.
    | env         Environment variables passed to every git call. GIT_SSH_COMMAND
    |             is the usual reason to touch this — a deploy key for pushing.
    |
    */

    'repository' => [
        'path' => env('STATAMIC_GIT_SYNC_PATH'),
        'git_binary' => env('STATAMIC_GIT_SYNC_BINARY', 'git'),
        'timeout' => (int) env('STATAMIC_GIT_SYNC_TIMEOUT', 60),
        'env' => array_filter([
            'GIT_SSH_COMMAND' => env('STATAMIC_GIT_SYNC_SSH_COMMAND'),
            'GIT_TERMINAL_PROMPT' => '0',
        ], static fn ($value): bool => $value !== null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    |
    | paths    The allow list. Nothing outside these paths is ever staged, so
    |          a stray change in app/ cannot ride along with a content commit.
    | exclude  Glob patterns applied after the allow list, for the handful of
    |          files inside a content directory that should stay local.
    |
    */

    'content' => [
        'paths' => [
            'content',
            'resources/blueprints',
            'resources/fieldsets',
            'resources/forms',
            'users',
        ],

        'exclude' => [
            '*.lock',
            'content/**/.DS_Store',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    |
    | message_template    Placeholders: {summary} {count} {user} {date} {files}
    | summary_max_files   How many file names the summary lists before it says
    |                     "and N more".
    | trailer             Appended as a trailer line. This is the loop guard —
    |                     it marks commits this addon made. Null removes it.
    | author_from_user    Credit the logged in control panel user as the commit
    |                     author. Falls back to fallback_author when there is
    |                     no user, which is the case for queued and scheduled runs.
    | allow_empty         Commit even when nothing changed. Almost never useful.
    | sign                Pass --gpg-sign. Requires a key the PHP user can use
    |                     without a passphrase prompt.
    |
    */

    'commit' => [
        'message_template' => env('STATAMIC_GIT_SYNC_MESSAGE', 'content: {summary}'),
        'summary_max_files' => (int) env('STATAMIC_GIT_SYNC_SUMMARY_MAX_FILES', 3),
        'trailer' => env('STATAMIC_GIT_SYNC_TRAILER', 'Committed-by: statamic-git-sync'),
        'author_from_user' => env('STATAMIC_GIT_SYNC_AUTHOR_FROM_USER', true),
        'fallback_author' => [
            'name' => env('STATAMIC_GIT_SYNC_AUTHOR_NAME', 'Statamic Git Sync'),
            'email' => env('STATAMIC_GIT_SYNC_AUTHOR_EMAIL', 'git-sync@localhost'),
        ],
        'allow_empty' => env('STATAMIC_GIT_SYNC_ALLOW_EMPTY', false),
        'sign' => env('STATAMIC_GIT_SYNC_SIGN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pull
    |--------------------------------------------------------------------------
    |
    | Integrate remote work before pushing. Off by default: pulling on a server
    | whose working tree is managed by a deploy pipeline is rarely what you want.
    |
    | on_conflict  "abort" restores the working tree and logs (recommended).
    |              "throw" does the same but lets the job fail loudly.
    |
    */

    'pull' => [
        'enabled' => env('STATAMIC_GIT_SYNC_PULL', false),
        'strategy' => env('STATAMIC_GIT_SYNC_PULL_STRATEGY', 'rebase'),
        'on_conflict' => env('STATAMIC_GIT_SYNC_ON_CONFLICT', 'abort'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push
    |--------------------------------------------------------------------------
    |
    | branch            Null pushes whatever branch the repository is on.
    | force_with_lease  Only ever set this if you know precisely why.
    |
    */

    'push' => [
        'enabled' => env('STATAMIC_GIT_SYNC_PUSH', false),
        'remote' => env('STATAMIC_GIT_SYNC_REMOTE', 'origin'),
        'branch' => env('STATAMIC_GIT_SYNC_BRANCH'),
        'force_with_lease' => env('STATAMIC_GIT_SYNC_FORCE_WITH_LEASE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pull request
    |--------------------------------------------------------------------------
    |
    | Only read when strategy is "pull-request". Instead of pushing straight to
    | the branch everybody deploys from, the addon pushes the branch this
    | instance is on and opens a pull request against `base`.
    |
    | This mode never checks out another branch — that would swap the content
    | files on disk underneath the editor who just saved them. Run the
    | authoring instance on its own branch, and point `base` at the branch it
    | should be merged into. `statamic:git-sync:doctor` refuses the setup when
    | the two are the same.
    |
    | driver       Which forge. Only "github" today.
    | api_url      Change for GitHub Enterprise.
    | token        A token with pull request write access on the repository.
    | repository   "owner/name". Null derives it from the remote URL.
    | base         The branch the pull request asks to merge into.
    | reuse_open   Append to the open pull request instead of opening one per
    |              sync. Leave this on unless you enjoy notification noise.
    |
    | Templates take {summary} {count} {user} {head} {base} {files}.
    |
    */

    'pull_request' => [
        'driver' => env('STATAMIC_GIT_SYNC_PR_DRIVER', 'github'),
        'api_url' => env('STATAMIC_GIT_SYNC_PR_API_URL'),
        'token' => env('STATAMIC_GIT_SYNC_PR_TOKEN'),
        'repository' => env('STATAMIC_GIT_SYNC_PR_REPOSITORY'),
        'base' => env('STATAMIC_GIT_SYNC_PR_BASE', 'main'),
        'title_template' => env('STATAMIC_GIT_SYNC_PR_TITLE', 'Content changes from {head}'),
        'body_template' => env(
            'STATAMIC_GIT_SYNC_PR_BODY',
            "Automated by [statamic-git-sync](https://github.com/Viovenia/statamic-git-sync).\n\n{files}",
        ),
        'draft' => env('STATAMIC_GIT_SYNC_PR_DRAFT', false),
        'labels' => [],
        'reuse_open' => env('STATAMIC_GIT_SYNC_PR_REUSE_OPEN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debounce
    |--------------------------------------------------------------------------
    |
    | Each content change pushes the run out by this many seconds, so a whole
    | editing session ends up in one commit instead of one commit per save.
    |
    | Requires a cache store that supports atomic locks (redis, memcached,
    | database, file, array, dynamodb). With any other store the addon commits
    | immediately instead and `statamic:git-sync:doctor` says so.
    |
    */

    'debounce' => [
        'enabled' => env('STATAMIC_GIT_SYNC_DEBOUNCE', true),
        'seconds' => (int) env('STATAMIC_GIT_SYNC_DEBOUNCE_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Null uses the application defaults. Note that the "sync" queue driver
    | makes debouncing meaningless and runs git inside the control panel
    | request — the doctor command warns about it.
    |
    | backoff  Delay in seconds before each retry of a retryable failure,
    |          such as a push that lost the network.
    |
    */

    'queue' => [
        'connection' => env('STATAMIC_GIT_SYNC_QUEUE_CONNECTION'),
        'name' => env('STATAMIC_GIT_SYNC_QUEUE'),
        'tries' => (int) env('STATAMIC_GIT_SYNC_TRIES', 3),
        'backoff' => [30, 120, 300],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety net
    |--------------------------------------------------------------------------
    |
    | A scheduled run that commits anything the event driven path missed —
    | a flushed cache, a queue worker that was down, a change made on disk.
    |
    | It is idempotent: a clean working tree means no commit and no log noise.
    | This is what makes the cache a timing hint rather than a place where
    | commits can go missing. Leave it on.
    |
    | Requires `php artisan schedule:run` to be in your crontab.
    |
    */

    'safety_net' => [
        'enabled' => env('STATAMIC_GIT_SYNC_SAFETY_NET', true),
        'cron' => env('STATAMIC_GIT_SYNC_SAFETY_NET_CRON', '*/15 * * * *'),
    ],

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    |
    | Feeds the "Recent content syncs" control panel widget. One JSON object
    | per line, appended under a lock, trimmed to max_entries.
    |
    | A file rather than the cache, because `cache:clear` is part of most
    | deploys and an empty widget after every deploy is worse than no widget.
    | A file rather than a database table, because a flat-file Solo site often
    | has no database.
    |
    | This is a display, never a source of truth — git is the record of what
    | was committed. Losing the file costs the widget its contents and nothing
    | else, and writing to it can never fail a sync.
    |
    | Add the widget in config/statamic/cp.php:
    |
    |     'widgets' => [
    |         ['type' => 'recent_syncs', 'width' => 50, 'limit' => 5],
    |     ],
    |
    */

    'history' => [
        'enabled' => env('STATAMIC_GIT_SYNC_HISTORY', true),
        'path' => env('STATAMIC_GIT_SYNC_HISTORY_PATH'),
        'max_entries' => (int) env('STATAMIC_GIT_SYNC_HISTORY_MAX', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Who hears about it when something happens. Built on the addon's own
    | events, so switching this off and writing your own listener is always an
    | option — see docs/advanced.md.
    |
    | on         Which events to send. sync_completed is off by default: on a
    |            site with an active editor that is one message per debounce
    |            window, and a channel that is mostly noise gets muted.
    | mail.to    Address => name. A plain list of addresses works too.
    | slack      A channel such as "#content". Requires
    |            `composer require laravel/slack-notification-channel` and a
    |            bot token in config/services.php — without the package the
    |            Slack channel is quietly skipped rather than failing the run.
    |
    | A conflict sends the conflict notification, not the generic failure one,
    | even though both events fire.
    |
    */

    'notifications' => [
        'enabled' => env('STATAMIC_GIT_SYNC_NOTIFICATIONS', false),

        'on' => [
            'sync_failed',
            'conflict_detected',
            'pull_request_opened',
        ],

        'mail' => [
            'to' => array_filter([env('STATAMIC_GIT_SYNC_NOTIFY_MAIL')]),
        ],

        'slack' => [
            'channel' => env('STATAMIC_GIT_SYNC_NOTIFY_SLACK_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | channel  Null uses the application default channel.
    | level    Level used for ordinary, successful runs. Failures always log
    |          at error regardless of this setting.
    |
    */

    'logging' => [
        'channel' => env('STATAMIC_GIT_SYNC_LOG_CHANNEL'),
        'level' => env('STATAMIC_GIT_SYNC_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Which Statamic events trigger a sync. Events your Statamic version does
    | not ship are ignored, so this list is safe across majors.
    |
    | Form submissions are off by default: on a busy site they would commit on
    | every contact form send.
    |
    */

    'events' => [
        'entry_saved' => true,
        'entry_deleted' => true,
        'term_saved' => true,
        'term_deleted' => true,
        'taxonomy_saved' => true,
        'taxonomy_deleted' => true,
        'global_set_saved' => true,
        'global_set_deleted' => true,
        'global_variables_saved' => true,
        'nav_saved' => true,
        'nav_deleted' => true,
        'nav_tree_saved' => true,
        'asset_saved' => true,
        'asset_deleted' => true,
        'asset_folder_saved' => true,
        'asset_folder_deleted' => true,
        'asset_container_saved' => true,
        'asset_container_deleted' => true,
        'collection_saved' => true,
        'collection_deleted' => true,
        'collection_tree_saved' => true,
        'blueprint_saved' => true,
        'blueprint_deleted' => true,
        'fieldset_saved' => true,
        'fieldset_deleted' => true,
        'form_saved' => true,
        'form_deleted' => true,
        'submission_saved' => false,
        'submission_deleted' => false,
        'user_saved' => true,
        'user_deleted' => true,
        'user_group_saved' => true,
        'user_group_deleted' => true,
        'role_saved' => true,
        'role_deleted' => true,
        'site_saved' => true,
        'site_deleted' => true,
    ],

];
