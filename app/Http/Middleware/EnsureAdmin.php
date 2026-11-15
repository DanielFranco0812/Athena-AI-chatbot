<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'That page is for front desk staff only.');
        }
        return $next($request);
    }
}
