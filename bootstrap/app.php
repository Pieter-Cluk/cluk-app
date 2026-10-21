<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\LogoutDisabledUsers::class,
            \App\Http\Middleware\LogoutBlockedMembers::class,
        ]);
        // Afrit 6 members and staff each have their own login page and home.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('afrit6', 'afrit6/*') ? route('afrit6.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('afrit6', 'afrit6/*') ? route('afrit6.portal') : route('dashboard'));
        $middleware->alias([
            'nav' => \App\Http\Middleware\EnsureNavAccess::class,
            'level' => \App\Http\Middleware\EnsureModuleLevel::class,
            'right' => \App\Http\Middleware\EnsureRight::class,
            'member.active' => \App\Http\Middleware\EnsureActiveMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
