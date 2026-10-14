<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            if ($user && $user->isUser()) {
                return redirect()
                    ->route('user.desk')
                    ->with('info', 'Accès réservé à l’administrateur.');
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
