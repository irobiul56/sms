<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\TeacherPermissionService;

class User
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $usertype = strtolower((string) Auth::user()->usertype);

        if (in_array($usertype, ['user', 'admin'], true)) {
            return $next($request);
        }

        if ($usertype === 'teacher' && app(TeacherPermissionService::class)->allowsRequest($request)) {
            return $next($request);
        }

        abort(403);
    }
}