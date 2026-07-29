<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * The naming and layering rules from CLAUDE.md, enforced rather than trusted.
 *
 * Conventions that live only in a document erode. These are the ones that
 * would cost the most to restore later, so they fail the build instead.
 */
final class ArchitectureTest extends TestCase
{
    #[Test]
    public function only_the_process_runner_starts_a_process(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $relative = $this->relativePath($file);

            if (str_starts_with($relative, 'Services/Process/')) {
                continue;
            }

            $contents = $this->codeWithoutComments($file);

            foreach (['Facades\\Process', 'shell_exec(', 'proc_open(', 'passthru('] as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = $relative.' uses '.$needle;
                }
            }

            if (preg_match('/(?<![\w>$])exec\s*\(/', $contents) === 1) {
                $offenders[] = $relative.' uses exec()';
            }
        }

        $this->assertSame([], $offenders, 'Everything must go through Services\Process\ProcessRunner.');
    }

    #[Test]
    public function only_the_service_provider_reads_configuration(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $relative = $this->relativePath($file);

            if ($relative === 'ServiceProvider.php' || $relative === 'Support/GitSyncConfigResolver.php') {
                continue;
            }

            $contents = $this->codeWithoutComments($file);

            if (preg_match('/(?<![\w>$])config\s*\(/', $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Classes receive a typed config DTO instead of calling config().');
    }

    #[Test]
    public function every_data_transfer_object_is_final_readonly_and_named_so(): void
    {
        $offenders = [];

        foreach ($this->classesIn('DataTransferObjects') as $class) {
            $reflection = new ReflectionClass($class);

            if (! str_ends_with($reflection->getShortName(), 'DTO')) {
                $offenders[] = $reflection->getShortName().' is not named *DTO';
            }

            if (! $reflection->isFinal()) {
                $offenders[] = $reflection->getShortName().' is not final';
            }

            if (! $reflection->isReadOnly()) {
                $offenders[] = $reflection->getShortName().' is not readonly';
            }
        }

        $this->assertNotSame([], $this->classesIn('DataTransferObjects'), 'The DTO namespace should not be empty.');
        $this->assertSame([], $offenders);
    }

    #[Test]
    public function every_interface_is_named_contract(): void
    {
        $offenders = [];

        foreach ($this->classesIn('Contracts') as $class) {
            if (! str_ends_with($class, 'Contract')) {
                $offenders[] = $class;
            }
        }

        $this->assertSame([], $offenders);
    }

    #[Test]
    public function no_class_uses_a_suffix_that_means_something_else_in_laravel(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $name = $file->getBasename('.php');
            $relative = $this->relativePath($file);

            // Factory belongs to model factories, Provider to service providers.
            foreach (['Factory', 'Helper', 'Util', 'Handler'] as $forbidden) {
                if (str_ends_with($name, $forbidden)) {
                    $offenders[] = $relative;
                }
            }

            if (str_ends_with($name, 'Provider') && $relative !== 'ServiceProvider.php') {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * The file's code with comments removed.
     *
     * Without this the rules would flag their own documentation — a docblock
     * explaining why nobody calls config() would count as a call to config().
     */
    private function codeWithoutComments(SplFileInfo $file): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /** @return list<SplFileInfo> */
    private function sourceFiles(): array
    {
        $files = [];

        /** @var iterable<SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->sourcePath()));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /** @return list<class-string> */
    private function classesIn(string $namespace): array
    {
        $classes = [];

        foreach ($this->sourceFiles() as $file) {
            $relative = $this->relativePath($file);

            if (! str_starts_with($relative, $namespace.'/')) {
                continue;
            }

            /** @var class-string $class */
            $class = 'Viovenia\\GitSync\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
            $classes[] = $class;
        }

        return $classes;
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace($this->sourcePath().DIRECTORY_SEPARATOR, '', $file->getPathname());
    }

    private function sourcePath(): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'src';
    }
}
