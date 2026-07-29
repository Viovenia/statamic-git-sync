<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Concerns;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\Process;

/**
 * A real git repository in a temporary directory.
 *
 * The unit tests fake the process layer; these do not, because the thing
 * worth proving here is that the commands this addon builds are commands git
 * actually accepts.
 */
trait InteractsWithTemporaryRepository
{
    protected string $repositoryPath;

    protected function createTemporaryRepository(): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'statamic-git-sync-'.bin2hex(random_bytes(6));

        if (! mkdir($path, 0o777, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create '.$path);
        }

        $this->repositoryPath = $path;

        $this->git(['init', '--initial-branch=main']);
        $this->git(['config', 'user.name', 'Test Runner']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'commit.gpgsign', 'false']);

        return $path;
    }

    protected function deleteTemporaryRepository(): void
    {
        if (! isset($this->repositoryPath) || ! is_dir($this->repositoryPath)) {
            return;
        }

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->repositoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->repositoryPath);
    }

    protected function writeFile(string $relativePath, string $contents = "title: Test\n"): void
    {
        $absolute = $this->repositoryPath.DIRECTORY_SEPARATOR.$relativePath;
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create '.$directory);
        }

        file_put_contents($absolute, $contents);
    }

    /** @param list<string> $arguments */
    protected function git(array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->repositoryPath);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'git %s failed: %s',
                implode(' ', $arguments),
                $process->getErrorOutput(),
            ));
        }

        return $process->getOutput();
    }

    protected function commitCount(): int
    {
        $process = new Process(['git', 'rev-list', '--count', 'HEAD'], $this->repositoryPath);
        $process->run();

        return $process->isSuccessful() ? (int) trim($process->getOutput()) : 0;
    }
}
