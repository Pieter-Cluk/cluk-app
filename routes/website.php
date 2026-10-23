<?php

use App\Http\Controllers\Site\DryHireController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\SalesController;
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

    Route::get('/sales', [SalesController::class, 'index'])->name('sales');
    Route::get('/sales/search', [SalesController::class, 'search'])->name('sales.search');
    Route::get('/sales/suggest', [SalesController::class, 'suggest'])->middleware('throttle:120,1')->name('sales.suggest');
    Route::get('/sales/new', [SalesController::class, 'new'])->name('sales.new');
    Route::get('/sales/brands', [SalesController::class, 'brands'])->name('sales.brands');
    Route::get('/sales/brands/{brand}', [SalesController::class, 'brand'])->where('brand', '[a-z0-9-]+')->name('sales.brand');
    Route::get('/sales/product/{item}', [SalesController::class, 'product'])->where('item', '[0-9]+(-[a-z0-9-]*)?')->name('sales.product');
    Route::get('/sales/photo/{item}/{w}', [SalesController::class, 'photo'])->whereNumber(['item', 'w'])->name('sales.photo');
    Route::get('/sales/document/{doc}', [SalesController::class, 'document'])->whereNumber('doc')->name('sales.document');
    Route::post('/sales/request', [SalesController::class, 'request'])->middleware('throttle:6,1')->name('sales.request');
    Route::get('/sales/{cat}/{sub?}', [SalesController::class, 'category'])->where(['cat' => '[a-z0-9-]+', 'sub' => '[a-z0-9-]+'])->name('sales.category');
};

if ($host = config('website.host')) {
    Route::domain('www.'.$host)->get('/{any?}', fn (string $any = '') => redirect()->away('https://'.$host.'/'.$any, 301))->where('any', '.*');
    Route::domain($host)->name('site.')->group($site);
} else {
    Route::prefix('site')->name('site.')->group($site);
}
