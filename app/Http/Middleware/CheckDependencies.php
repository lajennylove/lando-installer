<?php

namespace App\Http\Middleware;

use App\Services\DependencyChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDependencies
{
    public function __construct(private DependencyChecker $checker) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Never block: the setup page itself, settings (so Manage Deps button is reachable),
        // health check, Livewire internals, assets, or any Livewire AJAX update request.
        if (
            $request->is('setup') ||
            $request->is('settings') ||
            $request->is('up') ||
            $request->is('livewire/*') ||
            $request->is('vendor/*') ||
            $request->is('assets/*') ||
            $request->hasHeader('X-Livewire')
        ) {
            return $next($request);
        }

        if (! $this->checker->allRequiredInstalled()) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
