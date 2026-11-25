<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeacherAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $teacher = $user?->teacher;

        abort_unless(
            $user
            && strtolower((string) $user->usertype) === 'teacher'
            && $teacher
            && $teacher->status === 'active'
            && (int) $teacher->institute_id === (int) $user->institute_id,
            403
        );

        return $next($request);
    }
}
