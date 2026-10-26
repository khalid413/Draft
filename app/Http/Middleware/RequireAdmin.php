<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->is_admin) {
            if ($request->expectsJson()) {
                abort(403, 'Only the administrator can manage cards.');
            }

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
