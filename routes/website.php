<?php

use App\Http\Controllers\Site\DryHireController;
use App\Http\Controllers\Site\HomeController;
use Illuminate\Support\Facades\Route;

/*
| The public website. Served on WEBSITE_HOST (cluk.live) when that is set,
| otherwise previewed under /site on the app's own address.
*/
$site = function () {
    Route::get('/', [HomeController::class, 'show'])->name('home');
    Route::get('/dry-hire', [DryHireController::class, 'index'])->name('dryhire');
    Route::post('/dry-hire/request', [DryHireController::class, 'request'])->middleware('throttle:6,1')->name('dryhire.request');
    Route::get('/dry-hire/data/{item}', [DryHireController::class, 'data'])->whereNumber('item')->name('dryhire.data');
    Route::get('/dry-hire/photo/{item}/{w}', [DryHireController::class, 'photo'])->whereNumber(['item', 'w'])->name('photo');
    Route::get('/dry-hire/{item}', [DryHireController::class, 'show'])->where('item', '[0-9]+(-[a-z0-9-]*)?')->name('dryhire.item');
};

if ($host = config('website.host')) {
    Route::domain('www.'.$host)->get('/{any?}', fn (string $any = '') => redirect()->away('https://'.$host.'/'.$any, 301))->where('any', '.*');
    Route::domain($host)->name('site.')->group($site);
} else {
    Route::prefix('site')->name('site.')->group($site);
}
