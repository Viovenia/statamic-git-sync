<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

use Viovenia\GitSync\Exceptions\ConfigurationException;

/**
 * The `owner/name` pair an API needs to identify a repository.
 *
 * Derived from the remote URL by default, because asking somebody to
 * configure a value git already knows is a way to get it wrong.
 */
final readonly class RepositorySlugDTO
{
    private function __construct(
        public string $owner,
        public string $name,
    ) {}

    public static function fromString(string $slug): self
    {
        $parts = explode('/', trim($slug, " \t\n\r\0\x0B/"));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ConfigurationException::invalidValue('pull_request.repository', $slug, 'owner/name');
        }

        return new self($parts[0], $parts[1]);
    }

    /**
     * Reads both forms git uses:
     *
     *   git@github.com:acme/site.git
     *   https://github.com/acme/site.git
     *   https://user:token@github.com/acme/site
     */
    public static function fromRemoteUrl(string $url): ?self
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $isUrl = preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1;
        $colon = strrpos($url, ':');

        // A remote that is neither a URL nor the scp-like form is a local
        // path. There is no forge behind it, so there is no slug to derive —
        // saying so beats guessing and failing later against an API.
        if (! $isUrl && $colon === false) {
            return null;
        }

        // Strip the scheme and any credentials, then the host, leaving owner/name.
        $path = $isUrl
            ? preg_replace('#^[a-z][a-z0-9+.-]*://[^/]+/#i', '', $url)
            : substr($url, $colon + 1);

        $path = preg_replace('/\.git$/', '', (string) $path);
        $parts = array_values(array_filter(explode('/', trim((string) $path, '/'))));

        if (count($parts) < 2) {
            return null;
        }

        // Keep the last two segments: self-hosted installations nest deeper.
        return new self($parts[count($parts) - 2], $parts[count($parts) - 1]);
    }

    public function toString(): string
    {
        return $this->owner.'/'.$this->name;
    }
}
