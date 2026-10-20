<?php

use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Staff login with Microsoft 365.
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.staff-login')->name('login');
    Route::get('/auth/microsoft/redirect', [MicrosoftLoginController::class, 'redirect'])->name('login.microsoft');
    Route::get('/auth/microsoft/callback', [MicrosoftLoginController::class, 'callback'])->name('login.microsoft.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [MicrosoftLoginController::class, 'logout'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Settings › Users (people, roles & rights, activity log).
    Route::middleware('nav:users')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/new', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
        Route::put('/users/{user}/access', [UserController::class, 'updateAccess'])->whereNumber('user')->name('users.access');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->whereNumber('user')->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->whereNumber('user')->name('users.enable');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('/roles', [RoleController::class, 'update'])->name('roles.update');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::get('/activity', [AuditLogController::class, 'index'])->name('audit.index');
    });

    // Every menu item gets its address now; screens not built yet show
    // "coming soon". Access is checked against the same rules as the menu.
    $items = array_merge(config('navigation.top'), config('navigation.account'), ...array_values(config('navigation.groups')));
    foreach ($items as $item) {
        if (in_array($item['key'], ['dashboard', 'users'], true)) {
            continue; // real screens, registered above
        }
        Route::view($item['path'], 'coming-soon', ['navKey' => $item['key']])
            ->middleware('nav:'.$item['key'])
            ->name('nav.'.$item['key']);
    }
});

// Switch the language for this session (and for the signed-in person).
Route::post('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(array_key_exists($locale, config('cluk.locales')), 404);

    $request->session()->put('locale', $locale);

    if ($user = $request->user()) {
        $user->forceFill(['locale' => $locale])->save();
    }

    return back();
})->name('locale.switch');
