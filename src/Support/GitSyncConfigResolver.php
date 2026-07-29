<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Support;

use BackedEnum;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Viovenia\GitSync\DataTransferObjects\Config\CommitConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\ContentConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\DebounceConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\EventConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\LoggingConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\PullConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\PushConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\QueueConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\RepositoryConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Config\SafetyNetConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\PathCollectionDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;
use Viovenia\GitSync\Enums\ConflictBehaviour;
use Viovenia\GitSync\Enums\ContentEvent;
use Viovenia\GitSync\Enums\PullStrategy;
use Viovenia\GitSync\Exceptions\ConfigurationException;

/**
 * The one place in the package that reads raw configuration.
 *
 * Everything downstream receives GitSyncConfigDTO, which means a wrong value
 * is caught here, once, with a message naming the key — instead of surfacing
 * three layers down as a TypeError nobody can place.
 */
final class GitSyncConfigResolver
{
    public const CONFIG_KEY = 'statamic-git-sync';

    public function __construct(private readonly ConfigRepository $config) {}

    public function resolve(): GitSyncConfigDTO
    {
        /** @var array<string, mixed> $raw */
        $raw = $this->config->get(self::CONFIG_KEY, []);

        return $this->fromArray($raw);
    }

    /** @param array<string, mixed> $raw */
    public function fromArray(array $raw): GitSyncConfigDTO
    {
        return new GitSyncConfigDTO(
            enabled: $this->boolean($raw, 'enabled', true),
            dryRun: $this->boolean($raw, 'dry_run', false),
            strategy: $this->string($raw, 'strategy', 'commit'),
            repository: $this->repository($this->section($raw, 'repository')),
            content: $this->content($this->section($raw, 'content')),
            commit: $this->commit($this->section($raw, 'commit')),
            pull: $this->pull($this->section($raw, 'pull')),
            push: $this->push($this->section($raw, 'push')),
            debounce: $this->debounce($this->section($raw, 'debounce')),
            queue: $this->queue($this->section($raw, 'queue')),
            safetyNet: $this->safetyNet($this->section($raw, 'safety_net')),
            logging: $this->logging($this->section($raw, 'logging')),
            events: $this->events($this->section($raw, 'events')),
        );
    }

    /** @param array<string, mixed> $raw */
    private function repository(array $raw): RepositoryConfigDTO
    {
        $timeout = $this->integer($raw, 'repository.timeout', 60);

        if ($timeout < 1) {
            throw ConfigurationException::invalidValue('repository.timeout', $timeout, 'a positive number of seconds');
        }

        return new RepositoryConfigDTO(
            path: $this->nullableString($raw, 'repository.path'),
            gitBinary: $this->string($raw, 'repository.git_binary', 'git'),
            timeout: $timeout,
            environment: $this->stringMap($raw, 'repository.env'),
        );
    }

    /** @param array<string, mixed> $raw */
    private function content(array $raw): ContentConfigDTO
    {
        $paths = $this->stringList($raw, 'content.paths');

        if ($paths === []) {
            throw ConfigurationException::invalidValue(
                'content.paths',
                $paths,
                'at least one path — an empty allow list would never commit anything',
            );
        }

        return new ContentConfigDTO(
            paths: PathCollectionDTO::fromArray($paths),
            exclude: $this->stringList($raw, 'content.exclude'),
        );
    }

    /** @param array<string, mixed> $raw */
    private function commit(array $raw): CommitConfigDTO
    {
        $template = $this->string($raw, 'commit.message_template', 'content: {summary}');

        if (trim($template) === '') {
            throw ConfigurationException::invalidValue('commit.message_template', $template, 'a non-empty template');
        }

        /** @var array<string, mixed> $fallback */
        $fallback = is_array($raw['fallback_author'] ?? null) ? $raw['fallback_author'] : [];

        return new CommitConfigDTO(
            messageTemplate: $template,
            summaryMaxFiles: max(1, $this->integer($raw, 'commit.summary_max_files', 3)),
            trailer: $this->nullableString($raw, 'commit.trailer'),
            authorFromUser: $this->boolean($raw, 'commit.author_from_user', true),
            fallbackAuthor: new CommitAuthorDTO(
                name: $this->string($fallback, 'commit.fallback_author.name', 'Statamic Git Sync'),
                email: $this->string($fallback, 'commit.fallback_author.email', 'git-sync@localhost'),
            ),
            allowEmpty: $this->boolean($raw, 'commit.allow_empty', false),
            sign: $this->boolean($raw, 'commit.sign', false),
        );
    }

    /** @param array<string, mixed> $raw */
    private function pull(array $raw): PullConfigDTO
    {
        return new PullConfigDTO(
            enabled: $this->boolean($raw, 'pull.enabled', false),
            strategy: $this->enum(PullStrategy::class, $raw, 'pull.strategy', PullStrategy::Rebase),
            onConflict: $this->enum(ConflictBehaviour::class, $raw, 'pull.on_conflict', ConflictBehaviour::Abort),
        );
    }

    /** @param array<string, mixed> $raw */
    private function push(array $raw): PushConfigDTO
    {
        $branch = $this->nullableString($raw, 'push.branch');

        return new PushConfigDTO(
            enabled: $this->boolean($raw, 'push.enabled', false),
            remote: $this->string($raw, 'push.remote', 'origin'),
            branch: $branch !== null ? BranchNameDTO::fromString($branch) : null,
            forceWithLease: $this->boolean($raw, 'push.force_with_lease', false),
        );
    }

    /** @param array<string, mixed> $raw */
    private function debounce(array $raw): DebounceConfigDTO
    {
        $seconds = $this->integer($raw, 'debounce.seconds', 60);

        if ($seconds < 0) {
            throw ConfigurationException::invalidValue('debounce.seconds', $seconds, 'zero or more seconds');
        }

        return new DebounceConfigDTO(
            enabled: $this->boolean($raw, 'debounce.enabled', true),
            seconds: $seconds,
        );
    }

    /** @param array<string, mixed> $raw */
    private function queue(array $raw): QueueConfigDTO
    {
        $backoff = array_values(array_map(
            static fn (mixed $value): int => (int) $value,
            $this->list($raw, 'queue.backoff'),
        ));

        return new QueueConfigDTO(
            connection: $this->nullableString($raw, 'queue.connection'),
            name: $this->nullableString($raw, 'queue.name'),
            tries: max(1, $this->integer($raw, 'queue.tries', 3)),
            backoff: $backoff === [] ? [30, 120, 300] : $backoff,
        );
    }

    /** @param array<string, mixed> $raw */
    private function safetyNet(array $raw): SafetyNetConfigDTO
    {
        $cron = $this->string($raw, 'safety_net.cron', '*/15 * * * *');

        if (count(preg_split('/\s+/', trim($cron)) ?: []) !== 5) {
            throw ConfigurationException::invalidValue('safety_net.cron', $cron, 'a five field cron expression');
        }

        return new SafetyNetConfigDTO(
            enabled: $this->boolean($raw, 'safety_net.enabled', true),
            cron: $cron,
        );
    }

    /** @param array<string, mixed> $raw */
    private function logging(array $raw): LoggingConfigDTO
    {
        return new LoggingConfigDTO(
            channel: $this->nullableString($raw, 'logging.channel'),
            level: $this->string($raw, 'logging.level', 'info'),
        );
    }

    /** @param array<string, mixed> $raw */
    private function events(array $raw): EventConfigDTO
    {
        $flags = [];

        foreach (ContentEvent::cases() as $event) {
            $flags[$event->value] = (bool) ($raw[$event->value] ?? false);
        }

        return EventConfigDTO::fromFlags($flags);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function section(array $raw, string $key): array
    {
        $value = $raw[$key] ?? [];

        if (! is_array($value)) {
            throw ConfigurationException::invalidValue($key, $value, 'an array');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @param array<string, mixed> $raw */
    private function boolean(array $raw, string $key, bool $default): bool
    {
        $value = $raw[$this->leaf($key)] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? throw ConfigurationException::invalidValue($key, $value, 'a boolean');
        }

        throw ConfigurationException::invalidValue($key, $value, 'a boolean');
    }

    /** @param array<string, mixed> $raw */
    private function string(array $raw, string $key, string $default): string
    {
        $value = $raw[$this->leaf($key)] ?? $default;

        if (is_string($value)) {
            return $value;
        }

        throw ConfigurationException::invalidValue($key, $value, 'a string');
    }

    /** @param array<string, mixed> $raw */
    private function nullableString(array $raw, string $key): ?string
    {
        $value = $raw[$this->leaf($key)] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        throw ConfigurationException::invalidValue($key, $value, 'a string or null');
    }

    /** @param array<string, mixed> $raw */
    private function integer(array $raw, string $key, int $default): int
    {
        $value = $raw[$this->leaf($key)] ?? $default;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit(ltrim($value, '-'))) {
            return (int) $value;
        }

        throw ConfigurationException::invalidValue($key, $value, 'an integer');
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<mixed>
     */
    private function list(array $raw, string $key): array
    {
        $value = $raw[$this->leaf($key)] ?? [];

        if (! is_array($value)) {
            throw ConfigurationException::invalidValue($key, $value, 'an array');
        }

        return array_values($value);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<string>
     */
    private function stringList(array $raw, string $key): array
    {
        $values = [];

        foreach ($this->list($raw, $key) as $value) {
            if (! is_string($value)) {
                throw ConfigurationException::invalidValue($key, $value, 'an array of strings');
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function stringMap(array $raw, string $key): array
    {
        $value = $raw[$this->leaf($key)] ?? [];

        if (! is_array($value)) {
            throw ConfigurationException::invalidValue($key, $value, 'an array');
        }

        $map = [];

        foreach ($value as $name => $entry) {
            if (! is_string($name) || ! is_string($entry)) {
                throw ConfigurationException::invalidValue($key, $entry, 'a map of string to string');
            }

            $map[$name] = $entry;
        }

        return $map;
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  array<string, mixed>  $raw
     * @param  TEnum  $default
     * @return TEnum
     */
    private function enum(string $enum, array $raw, string $key, mixed $default): mixed
    {
        $value = $raw[$this->leaf($key)] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (! is_string($value)) {
            throw ConfigurationException::invalidValue($key, $value, 'a string');
        }

        $case = $enum::tryFrom($value);

        if ($case === null) {
            throw ConfigurationException::unknownOption($key, $value, implode(', ', array_map(
                static fn (BackedEnum $candidate): string => (string) $candidate->value,
                $enum::cases(),
            )));
        }

        return $case;
    }

    /** The last segment of a dotted key — sections are already unwrapped. */
    private function leaf(string $key): string
    {
        $segments = explode('.', $key);

        return end($segments) ?: $key;
    }
}
