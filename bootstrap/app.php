<?php

use App\Http\Middleware\EnsureActiveMember;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureModuleLevel;
use App\Http\Middleware\EnsureNavAccess;
use App\Http\Middleware\EnsureRight;
use App\Http\Middleware\LogoutBlockedCustomers;
use App\Http\Middleware\LogoutBlockedMembers;
use App\Http\Middleware\LogoutDisabledUsers;
use App\Http\Middleware\RunBackgroundChecks;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottlePerPerson;
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
            SetLocale::class,
            LogoutDisabledUsers::class,
            LogoutBlockedMembers::class,
            LogoutBlockedCustomers::class,
            RunBackgroundChecks::class,
            \App\Http\Middleware\MarkStaffBrowser::class,
        ]);
        // Afrit 6 members and staff each have their own login page and home.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('afrit6', 'afrit6/*') ? route('afrit6.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('afrit6', 'afrit6/*') ? route('afrit6.portal') : route('dashboard'));
        $middleware->validateCsrfTokens(except: ['intake']);
        // Website consent and visitor cookies are set by the page script, so they are not encrypted.
        // cluk_staff marks a team browser (set by the platform for the whole domain; read by the website statistics).
        $middleware->encryptCookies(except: ['cluk_consent', 'cluk_vid', 'cluk_staff']);
        $middleware->alias([
            'nav' => EnsureNavAccess::class,
            'level' => EnsureModuleLevel::class,
            'right' => EnsureRight::class,
            'member.active' => EnsureActiveMember::class,
            'customer.auth' => EnsureCustomer::class,
            'throttle' => ThrottlePerPerson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
