<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\DataTransferObjects\Config\DebounceConfigDTO;
use Viovenia\GitSync\Repositories\DebounceRepository;
use Viovenia\GitSync\Repositories\NullDebounceRepository;

final class DebounceRepositoryTest extends TestCase
{
    #[Test]
    public function a_run_scheduled_before_the_latest_change_stands_aside(): void
    {
        $debounce = $this->repository();

        $debounce->touch(2_000);

        $this->assertTrue($debounce->isStale(1_000), 'An older run should step aside.');
        $this->assertFalse($debounce->isStale(2_000), 'The run matching the marker should proceed.');
        $this->assertFalse($debounce->isStale(3_000), 'A newer run should proceed.');
    }

    #[Test]
    public function without_a_marker_nothing_is_stale_so_no_change_is_ever_dropped(): void
    {
        $this->assertFalse($this->repository()->isStale(1_000));
    }

    #[Test]
    public function forgetting_the_marker_never_blocks_a_run(): void
    {
        $debounce = $this->repository();
        $debounce->touch(2_000);
        $debounce->forget();

        $this->assertNull($debounce->latest());
        $this->assertFalse($debounce->isStale(1_000));
    }

    #[Test]
    public function the_null_implementation_lets_every_run_through(): void
    {
        $debounce = new NullDebounceRepository;
        $debounce->touch(2_000);

        $this->assertFalse($debounce->enabled());
        $this->assertNull($debounce->latest());
        $this->assertFalse($debounce->isStale(1_000));
    }

    private function repository(): DebounceRepository
    {
        return new DebounceRepository(
            new CacheRepository(new ArrayStore),
            new DebounceConfigDTO(enabled: true, seconds: 60),
        );
    }
}
