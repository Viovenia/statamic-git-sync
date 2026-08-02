<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Git;

use Viovenia\GitSync\Exceptions\ConfigurationException;

/**
 * The path an API needs to identify a repository.
 *
 * Holds the whole path rather than just `owner/name`, because GitLab groups
 * nest — `acme/websites/site` is one project, not a project called `site`
 * under a group called `websites`. GitHub never nests, so it simply sees two
 * segments.
 *
 * Derived from the remote URL by default: asking somebody to configure a
 * value git already knows is a way to get it wrong.
 */
final readonly class RepositorySlugDTO
{
    /** @param list<string> $segments at least two */
    private function __construct(public array $segments) {}

    public static function fromString(string $slug): self
    {
        $segments = array_values(array_filter(explode('/', trim($slug, " \t\n\r\0\x0B/"))));

        if (count($segments) < 2) {
            throw ConfigurationException::invalidValue('pull_request.repository', $slug, 'owner/name');
        }

        return new self($segments);
    }

    /**
     * Reads every form git uses:
     *
     *   git@github.com:acme/site.git
     *   https://github.com/acme/site.git
     *   https://user:token@github.com/acme/site
     *   git@gitlab.com:acme/websites/site.git
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

        // Strip the scheme and any credentials, then the host, leaving the path.
        $path = $isUrl
            ? preg_replace('#^[a-z][a-z0-9+.-]*://[^/]+/#i', '', $url)
            : substr($url, $colon + 1);

        $path = preg_replace('/\.git$/', '', (string) $path);
        $segments = array_values(array_filter(explode('/', trim((string) $path, '/'))));

        return count($segments) >= 2 ? new self($segments) : null;
    }

    /** Everything before the last segment. For GitHub, the account. */
    public function owner(): string
    {
        return implode('/', array_slice($this->segments, 0, -1));
    }

    /** The repository or project itself. */
    public function name(): string
    {
        return $this->segments[count($this->segments) - 1];
    }

    public function toString(): string
    {
        return implode('/', $this->segments);
    }

    /** The form GitLab wants in a URL path: acme%2Fwebsites%2Fsite. */
    public function encoded(): string
    {
        return rawurlencode($this->toString());
    }
}
