<?php

use App\Http\Controllers\Site\AccountController;
use App\Http\Controllers\Site\DryHireController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\NewsController;
use App\Http\Controllers\Site\SalesController;
use App\Http\Controllers\Site\TrackController;
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
    // News: Facebook and Instagram posts (pictures and videos served from our server).
    Route::get('/news', [NewsController::class, 'index'])->name('news');
    Route::get('/news/image/{post}', [NewsController::class, 'image'])->whereNumber('post')->name('social.image');
    Route::get('/news/video/{post}', [NewsController::class, 'video'])->whereNumber('post')->name('social.video');

    // Visitor behaviour (only with consent).
    Route::post('/t', [TrackController::class, 'store'])->middleware('throttle:240,1')->name('track');

    // Customer accounts (My CLUK) and the baskets kept on the server.
    Route::get('/account/login', [AccountController::class, 'login'])->name('account.login');
    Route::post('/account/login', [AccountController::class, 'doLogin'])->middleware('throttle:10,1')->name('account.login.post');
    Route::get('/account/register', [AccountController::class, 'register'])->name('account.register');
    Route::post('/account/register', [AccountController::class, 'doRegister'])->middleware('throttle:5,1')->name('account.register.post');
    Route::get('/account/forgot', [AccountController::class, 'forgot'])->name('account.forgot');
    Route::post('/account/forgot', [AccountController::class, 'doForgot'])->middleware('throttle:5,1')->name('account.forgot.post');
    Route::get('/account/reset', [AccountController::class, 'reset'])->name('account.reset');
    Route::post('/account/reset', [AccountController::class, 'doReset'])->middleware('throttle:10,1')->name('account.reset.post');
    Route::post('/account/logout', [AccountController::class, 'logout'])->name('account.logout');
    Route::middleware('customer.auth')->group(function () {
        Route::get('/account', [AccountController::class, 'show'])->name('account');
        Route::post('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
        Route::post('/account/password', [AccountController::class, 'password'])->middleware('throttle:10,1')->name('account.password');
        Route::get('/account/basket/{kind}', [AccountController::class, 'basket'])->whereIn('kind', ['dryhire', 'sales'])->name('account.basket');
        Route::post('/account/basket/{kind}/set', [AccountController::class, 'basketSet'])->whereIn('kind', ['dryhire', 'sales'])->middleware('throttle:240,1')->name('account.basket.set');
        Route::post('/account/basket/{kind}/merge', [AccountController::class, 'basketMerge'])->whereIn('kind', ['dryhire', 'sales'])->name('account.basket.merge');
        Route::post('/account/basket/dryhire/dates', [AccountController::class, 'basketDates'])->name('account.basket.dates');
        Route::post('/account/basket/dryhire/send', [AccountController::class, 'sendDryHire'])->middleware('throttle:6,1')->name('account.basket.send');
        Route::post('/account/basket/sales/order', [AccountController::class, 'orderSales'])->middleware('throttle:6,1')->name('account.basket.order');
        Route::get('/account/invoices/{invoice}', [AccountController::class, 'invoice'])->whereNumber('invoice')->name('account.invoice');
        Route::get('/account/invoices/{invoice}/e-invoice', [AccountController::class, 'invoiceXml'])->whereNumber('invoice')->name('account.invoice.xml');
        Route::post('/account/colleagues', [AccountController::class, 'invite'])->middleware('throttle:10,1')->name('account.colleagues.invite');
        Route::post('/account/colleagues/{colleague}', [AccountController::class, 'colleague'])->whereNumber('colleague')->name('account.colleagues.update');
    });

    Route::get('/sales/{cat}/{sub?}', [SalesController::class, 'category'])->where(['cat' => '[a-z0-9-]+', 'sub' => '[a-z0-9-]+'])->name('sales.category');
};

if ($host = config('website.host')) {
    Route::domain('www.'.$host)->get('/{any?}', fn (string $any = '') => redirect()->away('https://'.$host.'/'.$any, 301))->where('any', '.*');
    Route::domain($host)->name('site.')->group($site);
} else {
    Route::prefix('site')->name('site.')->group($site);
}
