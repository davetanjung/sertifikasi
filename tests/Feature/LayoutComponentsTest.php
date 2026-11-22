<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\Support\TestLayoutPage;
use Tests\TestCase;

class LayoutComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(base_path('tests/Support/views'));
        $this->withoutVite();
        Route::get('/__layout-test', TestLayoutPage::class);
    }

    public function test_it_renders_the_navbar_sidebar_footer_and_page_content_in_the_layout(): void
    {
        $this->get('/__layout-test')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Search...', false)
            ->assertSee('Dashboard')
            ->assertDontSee('Settings')
            ->assertSee('Logout')
            ->assertSee('&copy; '.now()->year, false)
            ->assertSee('Page body here');
    }

    public function test_it_shows_the_success_and_danger_flash_messages_inside_a_livewire_page(): void
    {
        $this->get('/__layout-test')
            ->assertSee('Saved fine.')
            ->assertSee('Something broke.');
    }
}
