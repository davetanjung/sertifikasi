<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Carbon\Carbon;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_register_does_nothing_and_does_not_fail(): void
    {
        (new AppServiceProvider($this->app))->register();

        $this->assertTrue(true);
    }

    public function test_boot_uses_the_fake_today_date_and_keeps_the_time_of_day(): void
    {
        Carbon::setTestNow('2026-01-01 08:15:00');
        config(['app.fake_today' => '2026-12-05']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('2026-12-05', now()->toDateString());
        $this->assertSame('08:15', now()->format('H:i'));
    }

    public function test_boot_turns_off_the_livewire_upload_cleanup_with_a_fake_date(): void
    {
        config(['app.fake_today' => '2026-12-05', 'livewire.temporary_file_upload.cleanup' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('livewire.temporary_file_upload.cleanup'));
    }

    public function test_boot_leaves_the_clock_alone_without_a_fake_date(): void
    {
        Carbon::setTestNow('2026-01-01 08:15:00');
        config(['app.fake_today' => null]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('2026-01-01', now()->toDateString());
    }
}
