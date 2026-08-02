<?php

declare(strict_types=1);

/*
 * Labels for the enums the control panel shows. The enum values themselves
 * stay as they are — they end up in log files and in the history file, where
 * a stable English key is worth more than a translated one.
 */
return [

    'status' => [
        'added' => 'added',
        'modified' => 'modified',
        'deleted' => 'deleted',
        'renamed' => 'renamed',
        'copied' => 'copied',
        'untracked' => 'new',
        'unmerged' => 'conflicted',
        'type_changed' => 'type changed',
    ],

    'trigger' => [
        'content_event' => 'content change',
        'console' => 'command',
        'schedule' => 'schedule',
        'safety_net' => 'safety net',
    ],

    'outcome' => [
        'committed' => 'committed',
        'nothing_to_commit' => 'nothing to commit',
        'skipped' => 'skipped',
        'conflicted' => 'conflict',
        'failed' => 'failed',
    ],

];
