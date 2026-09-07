<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class NavLink extends Component
{
    public function __construct(
        public string $href,
        public ?string $active = null,
    ) {}

    public function isActive(): bool
    {
        return $this->active !== null && request()->routeIs($this->active);
    }

    public function render(): View|Closure|string
    {
        return view('components.nav-link');
    }
}