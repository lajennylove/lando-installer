<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CreateSite extends Component
{
    public function render()
    {
        return view('livewire.create-site');
    }
}
