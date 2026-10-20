<?php

use App\Http\Controllers\Auth\MicrosoftLoginController;
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

    // Every menu item gets its address now; screens not built yet show
    // "coming soon". Access is checked against the same rules as the menu.
    $items = array_merge(config('navigation.top'), config('navigation.account'), ...array_values(config('navigation.groups')));
    foreach ($items as $item) {
        if ($item['key'] === 'dashboard') {
            continue;
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
