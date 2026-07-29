# Handoff: viovenia/statamic-git-sync

Stand: 27.07.2026. Zusammengetragen in einer lead-app-Session (Marcel Wagner). Dieses Dokument ist die vollständige Übergabe — die Implementierung startet in einer eigenen Session in diesem Verzeichnis. Es wurde noch **kein Code geschrieben**; ein früherer Scaffolding-Agent wurde vor Arbeitsbeginn gestoppt, das Verzeichnis enthält nur diese Datei.

## 1. Was gebaut werden soll

Freies Open-Source-Statamic-Addon: Bei Content-Änderungen im Control Panel automatisch `git commit` und optional `git push`. Funktionale Alternative zur **Pro-only** eingebauten „Git Automation" von Statamic — lauffähig auf Statamic **Solo (Free)**.

Entscheidungen des Auftraggebers (Marcel):
- **Kostenlos und Open Source** (MIT), bewusst keine Bezahlschranke
- Package-Name: `viovenia/statamic-git-sync` (Vendor: Viovenia)
- Eigenständiges Composer-Package in diesem Verzeichnis, eigenes Git-Repo
- GitHub-Repo unter Viovenia-Org legt Marcel an, wenn er es öffentlich machen will — bis dahin **nur lokal committen, nicht pushen**
- Einsatzkontext des Auftraggebers: Single-User-Setup (Solo-Edition reicht ihm)

## 2. Rechtslage (geprüft, wichtig)

Quelle: `LICENSE.md` in `statamic/cms` (lokal gelesen in lead-app: `vendor/statamic/cms/LICENSE.md`). Statamic ist **nicht MIT**, sondern proprietär source-available. Relevante Klauseln:

- **Klausel 3**: Verbietet Umgehen/Verändern von Lizenz-/Edition-Checks. Ein Addon über öffentliche Events fasst diese nicht an → unkritisch. Niemals `STATAMIC_PRO_ENABLED` faken oder Edition-Gates patchen.
- **Klausel 4** („Not for reuse"): Verbietet Wiederverwendung von Statamic-Code (Klassen, Komponenten, Patterns) in anderen Projekten. Konsequenz — **harte Clean-Room-Regel für dieses Projekt**:
  - `vendor/statamic/cms/src/Git/` **niemals öffnen oder als Vorlage nutzen**
  - Feature-Verhalten nachbauen ist erlaubt, Code-/Strukturübernahme nicht
  - Öffentliche APIs (Events, `AddonServiceProvider`, Config-Publishing) nutzen ist der dokumentierte, erwünschte Erweiterungsweg
- Präzedenz (schwach, aber vorhanden): **Gitamic** (Simon Hamp) bietet seit Jahren Git-Funktionen ohne Pro an, verkauft über Anystack. Kein aktives Endorsement von Statamic (NICHT im offiziellen Marketplace gelistet — geprüft: statamic.com/addons-Suche liefert 0 Treffer), aber erkennbar geduldet.
- Offener Punkt: Vor Public-Release optional Bestätigung bei Statamic einholen (support@statamic.com oder Discord): „Dürfen wir ein freies Addon veröffentlichen, das bei Content-Änderungen via Events committet/pusht?"

## 3. Marktrecherche (27.07.2026)

| Projekt | Status | Warum keine Lösung |
|---|---|---|
| Statamic Git Automation (Core) | aktiv | Pro-only |
| `statamic/spock` | **archiviert**, letzter Push 07/2020, Statamic 2.10 | tot; Funktion wurde als Pro-Feature in den Core übernommen — genau deshalb existiert seitdem kein gepflegtes freies Addon |
| Gitamic (marketplace.anystack.sh/item/gitamic) | aktiv, **kostenpflichtig** | manuelle Git-UI im CP, keine Automation; läuft ohne Pro |
| `diffrentdigital/git-auto-push` (Packagist) | vorhanden | stumpfer 24h-Cron-Commit, nicht event-basiert |
| `emran-alhaddad/statamic-content-sync` (3 Stars) | letzter Push 08/2025, MIT | **kein Git-Sync** — Export/Import von Content zwischen Umgebungen mit CP-UI und Diff-Ansicht; Statamic v5 / Laravel 10–11, inkompatibel mit Statamic 6 / Laravel 13 |

**Fazit: Nische real.** Kein gepflegtes, freies, event-basiertes Git-Automation-Addon für Statamic 5/6.

Links: github.com/statamic/spock · gitamic.simonhamp.me · packagist.org/packages/diffrentdigital/git-auto-push · github.com/emran-alhaddad/statamic-content-sync · statamic.dev/git-automation (Feature-Referenz der Pro-Variante — nur Doku lesen, keinen Code)

## 4. Architektur-Skizze v1 (abgestimmt mit Marcel)

- **Subscriber** auf Content-Events: `EntrySaved`, `EntryDeleted`, `TermSaved`, `TermDeleted`, `GlobalSetSaved`, `NavSaved`, `NavDeleted`, `AssetSaved`, `AssetDeleted`, `CollectionSaved`, `CollectionDeleted`, `CollectionTreeSaved`. Klassennamen gegen installierte Statamic-Version verifizieren (`vendor/statamic/cms/src/Events/` ansehen ist ok — öffentliche API; nur `src/Git/` ist tabu).
- **Debounce**: Event → delayed Queued Job (Default 30–60 s). Mehrere Saves im Fenster = ein Commit. Job verwirft sich, wenn ein späterer Job ansteht (Zeitstempel im Cache).
- **Lock**: `Cache::lock('statamic-git-sync')` um die gesamte Git-Sequenz — zwei parallele Saves dürfen nie gleichzeitig `git` ausführen.
- **GitRunner** (Laravel `Process`-Facade): `status --porcelain` beschränkt auf Pfad-Whitelist → kein Change, kein Commit → `add` → `commit` → optional `pull --rebase` → optional `push`.
- **Konflikt-Verhalten**: Rebase schlägt fehl → `rebase --abort`, Error-Log (v1; Notification-Hook später), Working Tree unangetastet.
- **Loop-Schutz**: Commit-Trailer `Committed-by: statamic-git-sync`; Deploy-Pulls triggern keine Statamic-Events, das reicht als v1-Schutz.
- **Config** (`statamic-git-sync.php`, publishbar): `enabled`, `debounce_seconds`, `paths` (Default: `content/`, `resources/blueprints/`, `resources/fieldsets/`, `users/`), `push` (bool), `pull_rebase` (bool), `branch` (null = current), `commit_message_template` (Platzhalter `{summary}`, `{user}`), `git_binary`, `author_from_user` (CP-User als Commit-Author).

**Differenzierungs-Feature für Roadmap (v2): PR-Modus** — statt Direkt-Push Branch + PR via `gh` öffnen. Kann die eingebaute Pro-Automation nicht; Hauptgrund, warum das Addon auch für Pro-Kunden interessant ist.

## 5. Technischer Rahmen

- `composer.json`: `php ^8.2`, `statamic/cms ^5.0 || ^6.0`, Autoload `Viovenia\GitSync\`, dev: `phpunit/phpunit` + Statamic-Addon-Testharness (wie Addon-Tests in Statamic 5/6 aufgesetzt werden vorab prüfen — Stichwort `Statamic\Testing\AddonTestCase`, via statamic.dev-Doku verifizieren)
- ServiceProvider extends `Statamic\Providers\AddonServiceProvider`
- Tests (PHPUnit, kein Pest):
  - GitRunner-Unit-Tests gegen echtes Temp-Repo (`git init` in `sys_get_temp_dir()`): Commit erzeugt, Whitelist greift, kein Commit bei clean tree, Trailer vorhanden
  - Feature: Event feuern → Job dispatched (`Queue::fake`); Debounce: zwei Events → ein effektiver Lauf
  - Konflikt: Rebase-Fehler → abort, kein Crash
- CI: GitHub Actions, PHP-8.2/8.3/8.4-Matrix
- README auf Englisch: Why (Pro-only-Gap, Spock tot), Install, Config-Referenz, How it works (Debounce/Lock/Loop-Schutz), Vergleichstabelle (Pro Git Automation / Gitamic / dieses Addon), Roadmap, MIT
- LICENSE: MIT, Copyright Viovenia

## 6. Bezug zur lead-app (erster Konsument, später)

- lead-app: Statamic 6 / Laravel 13, Content flat-file, Repo `Tschucki/lead-app`, lokal `~/dev-private/lead-app`
- Motivation entstand aus Diskussion Flat-File vs. DB: Entscheidung fiel auf Flat-File behalten + Git-Automation, damit CP-Änderungen (aktuell Single-User) automatisch im Repo landen und der Git-Workflow (Review, Revert, Deploy-by-Push, CI) erhalten bleibt
- Integration in lead-app ist NICHT Teil dieses Package-Builds — erst nach v1 (Path-Repository oder Packagist)

## 7. Offene Entscheidungen für die neue Session

1. Debounce-Default (30 vs. 60 s)
2. Statamic-5-Support wirklich mitnehmen oder nur `^6.0` (weniger Testmatrix; lead-app ist v6)
3. Lizenz-Rückfrage an Statamic vor Public-Release ja/nein (Empfehlung: ja, kostet nichts)
4. Packagist-Publish + Marketplace-Listing-Zeitpunkt (Marketplace listet auch freie Addons)
