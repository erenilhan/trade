<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow password verification route
        if ($request->routeIs('dashboard.password.verify')) {
            return $next($request);
        }

        // Check if password is verified in session
        if (!session()->has('dashboard_authenticated')) {
            return redirect()->route('dashboard.password');
        }

        return $next($request);
    }
}
