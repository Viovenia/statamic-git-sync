<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Enums\FileStatus;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Enums\SyncTrigger;
use Viovenia\GitSync\Tests\TestCase;

final class TranslationTest extends TestCase
{
    /**
     * A missing key returns the key itself, which then shows up in the control
     * panel as `statamic-git-sync::enums.status.renamed`. This walks every
     * enum in both locales so that never ships.
     */
    #[Test]
    #[DataProvider('locales')]
    public function every_enum_label_is_translated_in_every_locale(string $locale): void
    {
        $this->app->setLocale($locale);

        $cases = [
            ...FileStatus::cases(),
            ...SyncTrigger::cases(),
            ...SyncOutcome::cases(),
        ];

        foreach ($cases as $case) {
            $label = $case->label();

            $this->assertStringNotContainsString(
                'statamic-git-sync::',
                $label,
                sprintf('%s::%s has no %s translation.', $case::class, $case->name, $locale),
            );
            $this->assertNotSame('', trim($label));
        }
    }

    #[Test]
    #[DataProvider('locales')]
    public function every_widget_string_is_translated_in_every_locale(string $locale): void
    {
        $this->app->setLocale($locale);

        $keys = ['title', 'empty', 'disabled', 'disabled_hint', 'show_files', 'files', 'duration'];

        foreach ($keys as $key) {
            $line = __('statamic-git-sync::widgets.recent_syncs.'.$key);

            $this->assertIsString($line);
            $this->assertStringNotContainsString('statamic-git-sync::', $line, $key.' is missing in '.$locale);
        }
    }

    #[Test]
    public function the_german_widget_actually_renders_german(): void
    {
        $this->app->setLocale('de');

        $this->assertSame('geändert', FileStatus::Modified->label());
        $this->assertSame('Content-Änderung', SyncTrigger::ContentEvent->label());
        $this->assertSame('2 Dateien', trans_choice('statamic-git-sync::widgets.recent_syncs.files', 2));
        $this->assertSame('1 Datei', trans_choice('statamic-git-sync::widgets.recent_syncs.files', 1));
    }

    #[Test]
    public function the_enum_values_themselves_stay_english_whatever_the_locale(): void
    {
        // They end up in log files and in the history file on disk, where a
        // stable key is worth more than a translated one.
        $this->app->setLocale('de');

        $this->assertSame('modified', FileStatus::Modified->value);
        $this->assertSame('content_event', SyncTrigger::ContentEvent->value);
        $this->assertSame('committed', SyncOutcome::Committed->value);
    }

    /** @return array<string, array{0: string}> */
    public static function locales(): array
    {
        return [
            'english' => ['en'],
            'german' => ['de'],
        ];
    }
}
