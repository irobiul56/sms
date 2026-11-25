<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $student = $user?->student;

        abort_unless(
            $user
            && strtolower((string) $user->usertype) === 'student'
            && $student
            && $student->status === 'active'
            && (int) $student->institute_id === (int) $user->institute_id
            && strtolower($student->student_id) === $user->username,
            403
        );

        return $next($request);
    }
}
