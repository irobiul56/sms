<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            $usertype = strtolower((string) $request->user()?->usertype);

            return match ($usertype) {
                'student' => route('student.dashboard'),
                'teacher' => route('teacher.dashboard'),
                'admin' => route('dashboard'),
                default => route('institute.dashboard'),
            };
        });

         $middleware -> alias([
            'admin' => \App\Http\Middleware\Admin::class,
            'student' => \App\Http\Middleware\StudentAccess::class,
            'teacher' => \App\Http\Middleware\TeacherAccess::class,
            'user' => \App\Http\Middleware\User::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
