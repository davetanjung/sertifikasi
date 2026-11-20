<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;

class TestLayoutPage extends Component
{
    public function mount(): void
    {
        session()->flash('flash_success', 'Saved fine.');
        $this->addError('flash_danger', 'Something broke.');
    }

    public function render()
    {
        return view('welcome-test-stub')->layout('components.layouts.app');
    }
}
