<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Local testing only: FAKE_TODAY=YYYY-MM-DD in .env makes the whole app think it is that day.
        if (config('app.fake_today') && ! $this->app->isProduction()) {
            Carbon::setTestNow(Carbon::parse(config('app.fake_today'))->setTimeFrom(Carbon::now()));

            // Livewire deletes temporary uploads older than a day by comparing file times with now(). With a
            // fake date ahead of the real one, a file uploaded a second ago would look old and be deleted.
            config(['livewire.temporary_file_upload.cleanup' => false]);
        }
    }
}
