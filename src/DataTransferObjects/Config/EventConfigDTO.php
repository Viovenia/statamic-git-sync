<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

use Viovenia\GitSync\Enums\ContentEvent;

final readonly class EventConfigDTO
{
    /** @param array<string, bool> $flags keyed by ContentEvent value */
    private function __construct(public array $flags) {}

    /** @param array<string, bool> $flags */
    public static function fromFlags(array $flags): self
    {
        return new self($flags);
    }

    public function isEnabled(ContentEvent $event): bool
    {
        return $this->flags[$event->value] ?? false;
    }

    /**
     * Every event that is both switched on and present in this Statamic version.
     *
     * @return list<ContentEvent>
     */
    public function subscribable(): array
    {
        return array_values(array_filter(
            ContentEvent::availableCases(),
            fn (ContentEvent $event): bool => $this->isEnabled($event),
        ));
    }
}
