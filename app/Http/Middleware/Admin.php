<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class Admin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $usertype = strtolower((string) Auth::user()->usertype);

        if ($usertype === 'admin') {
            return $next($request);
        }

        if ($usertype === 'user') {
            return redirect()->route('institute.dashboard');
        }

        if ($usertype === 'teacher') {
            return redirect()->route('teacher.dashboard');
        }

        abort(403);
    }
}