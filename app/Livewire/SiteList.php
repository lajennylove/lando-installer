<?php

namespace App\Livewire;

use App\Models\Site;
use Livewire\Attributes\On;
use Livewire\Component;

class SiteList extends Component
{
    #[On('site-created')]
    #[On('site-deleted')]
    #[On('site-status-changed')]
    public function refresh(): void
    {
        // Livewire will re-render automatically
    }

    public function render()
    {
        return view('livewire.site-list', [
            'sites' => Site::orderBy('name')->get(),
        ]);
    }
}
