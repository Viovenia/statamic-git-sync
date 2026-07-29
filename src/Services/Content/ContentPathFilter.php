<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Content;

use Viovenia\GitSync\Contracts\PathFilterContract;
use Viovenia\GitSync\DataTransferObjects\Config\ContentConfigDTO;

/**
 * Allow list first, exclude patterns second.
 *
 * The allow list is what keeps an unrelated change in app/ from riding along
 * with a content commit — git is asked about those paths only, and this
 * filter is the second gate behind that.
 */
final class ContentPathFilter implements PathFilterContract
{
    public function __construct(private readonly ContentConfigDTO $config) {}

    public function matches(string $path): bool
    {
        $path = $this->normalise($path);

        if (! $this->withinAllowedPaths($path)) {
            return false;
        }

        foreach ($this->config->exclude as $pattern) {
            $pattern = $this->normalise($pattern);

            // A pattern without a slash applies at any depth, the way
            // .gitignore behaves — `*.lock` should not have to be written as
            // `**/*.lock` to catch a lock file three directories down.
            $subject = str_contains($pattern, '/') ? $path : basename($path);

            if ($this->matchesPattern($subject, $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function withinAllowedPaths(string $path): bool
    {
        foreach ($this->config->paths->all() as $allowed) {
            $allowed = $this->normalise($allowed);

            if ($path === $allowed || str_starts_with($path, $allowed.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Glob matching where `**` also crosses directory separators.
     *
     * fnmatch alone treats `*` as "anything but a slash" only with
     * FNM_PATHNAME, and inconsistently across platforms without it, so the
     * pattern is compiled to a regular expression instead.
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        $regex = '';
        $length = strlen($pattern);

        for ($index = 0; $index < $length; $index++) {
            $character = $pattern[$index];

            if ($character === '*') {
                if (($pattern[$index + 1] ?? '') === '*') {
                    $index++;

                    // `**/` may also match nothing at all.
                    if (($pattern[$index + 1] ?? '') === '/') {
                        $index++;
                        $regex .= '(?:.*/)?';

                        continue;
                    }

                    $regex .= '.*';

                    continue;
                }

                $regex .= '[^/]*';

                continue;
            }

            $regex .= $character === '?' ? '[^/]' : preg_quote($character, '#');
        }

        return (bool) preg_match('#^'.$regex.'$#u', $path);
    }

    private function normalise(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }
}
