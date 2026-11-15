<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdministrator
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->administrator) {
            return $next($request);
        }

        // Entrenadores y clientes vuelven a su propio dashboard
        if ($user && $user->trainer) {
            return redirect()->route('trainer.dashboard')
                ->with('error', 'No tienes acceso al panel de administración.');
        }

        return redirect()->route('dashboard')
            ->with('error', 'No tienes acceso al panel de administración.');
    }
}
