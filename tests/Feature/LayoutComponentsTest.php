<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\Support\TestLayoutPage;

beforeEach(function () {
    View::addLocation(base_path('tests/Support/views'));
    $this->withoutVite();
    Route::get('/__layout-test', TestLayoutPage::class);
});

it('renders the navbar, sidebar, footer and page content in the layout', function () {
    $this->get('/__layout-test')
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('Search...', false)
        ->assertSee('Dashboard')
        ->assertSee('Settings')
        ->assertSee('Logout')
        ->assertSee('&copy; '.now()->year, false)
        ->assertSee('Page body here');
});

it('shows the success and danger flash messages inside a Livewire page', function () {
    $this->get('/__layout-test')
        ->assertSee('Saved fine.')
        ->assertSee('Something broke.');
});
