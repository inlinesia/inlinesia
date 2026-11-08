<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        if ($user->role !== 'admin' && ! $user->hasVerifiedEmail()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
