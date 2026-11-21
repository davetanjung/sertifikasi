<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Carbon\Carbon;
use Tests\TestCase;

class FakeTodayTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_fake_today_makes_the_app_use_that_date(): void
    {
        config(['app.fake_today' => '2026-12-05']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('2026-12-05', today()->toDateString());
        $this->assertSame('2026-12-05', now()->toDateString());
    }

    public function test_fake_today_turns_off_the_livewire_upload_cleanup(): void
    {
        config(['app.fake_today' => '2026-12-05']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('livewire.temporary_file_upload.cleanup'));
    }

    public function test_without_fake_today_the_real_date_is_used(): void
    {
        config(['app.fake_today' => null]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(Carbon::hasTestNow());
        $this->assertNotFalse(config('livewire.temporary_file_upload.cleanup'));
    }

    public function test_fake_today_is_ignored_in_production(): void
    {
        config(['app.fake_today' => '2026-12-05']);
        $this->app['env'] = 'production';

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(Carbon::hasTestNow());
    }
}
