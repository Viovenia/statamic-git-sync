<?php

declare(strict_types=1);

/*
 * Beschriftungen für die Enums, die im Control Panel auftauchen. Die Werte
 * der Enums selbst bleiben englisch — sie landen im Log und in der
 * Historien-Datei, wo ein stabiler Schlüssel mehr wert ist als ein
 * übersetzter.
 */
return [

    'status' => [
        'added' => 'hinzugefügt',
        'modified' => 'geändert',
        'deleted' => 'gelöscht',
        'renamed' => 'umbenannt',
        'copied' => 'kopiert',
        'untracked' => 'neu',
        'unmerged' => 'Konflikt',
        'type_changed' => 'Typ geändert',
    ],

    'trigger' => [
        'content_event' => 'Content-Änderung',
        'console' => 'Befehl',
        'schedule' => 'Zeitplan',
        'safety_net' => 'Sicherheitsnetz',
    ],

    'outcome' => [
        'committed' => 'committet',
        'nothing_to_commit' => 'nichts zu committen',
        'skipped' => 'übersprungen',
        'conflicted' => 'Konflikt',
        'failed' => 'fehlgeschlagen',
    ],

];
