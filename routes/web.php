<?php

use App\Http\Controllers\Afrit6\MemberAdminController;
use App\Http\Controllers\Afrit6\MemberAuthController;
use App\Http\Controllers\Afrit6\PortalController;
use App\Http\Controllers\Afrit6\StaffWorkspaceController;
use App\Http\Controllers\Afrit6\TaskController;
use App\Http\Controllers\Afrit6\WorkspaceController;
use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Http\Controllers\CompanyContactController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Equipment\AssetController;
use App\Http\Controllers\Equipment\ItemController;
use App\Http\Controllers\Factory\FactoryController;
use App\Http\Controllers\Factory\FactoryPublicController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PictureController;
use App\Http\Controllers\QuotePublicController;
use App\Http\Controllers\Rental\OrderController;
use App\Http\Controllers\Rental\OrderDocumentController;
use App\Http\Controllers\Rental\SubhireController;
use App\Http\Controllers\Sales\DealController;
use App\Http\Controllers\Sales\IncomingController;
use App\Http\Controllers\Sales\QuoteController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\CompanyDetailsController;
use App\Http\Controllers\Settings\ImportController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\VatRateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Website form → Incoming (token in the X-Intake-Token header).
Route::post('/intake', [IncomingController::class, 'intake'])->middleware('throttle:30,1')->name('intake');

// CLUK's general terms and conditions (linked from quotes, emails and documents).
Route::get('/terms/{lang?}', function (?string $lang = null) {
    $files = config('rental.terms_files');
    $file = $files[$lang ?? app()->getLocale()] ?? $files['en'];

    return response()->file(public_path($file), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.basename($file).'"']);
})->where('lang', '[a-z]{2}')->name('terms');

// The public Cluk Factory booking page (also for the website).
Route::get('/book', [FactoryPublicController::class, 'show'])->name('factory.book');
Route::post('/book', [FactoryPublicController::class, 'store'])->middleware('throttle:10,1')->name('factory.book.store');

// The customer's quote page (link in the quote email).
Route::prefix('q/{token}')->where(['token' => '[A-Za-z0-9]{48}'])->middleware('throttle:60,1')->group(function () {
    Route::get('/', [QuotePublicController::class, 'show'])->name('quote.public');
    Route::get('/print', [QuotePublicController::class, 'print'])->name('quote.public.print');
    Route::get('/order', [QuotePublicController::class, 'order'])->name('quote.public.order');
    Route::get('/proforma', [QuotePublicController::class, 'proforma'])->name('quote.public.proforma');
    Route::post('/accept', [QuotePublicController::class, 'accept'])->middleware('throttle:10,1')->name('quote.public.accept');
    Route::post('/change', [QuotePublicController::class, 'change'])->middleware('throttle:10,1')->name('quote.public.change');
    Route::post('/decline', [QuotePublicController::class, 'decline'])->middleware('throttle:10,1')->name('quote.public.decline');
});

// Staff login with Microsoft 365.
Route::middleware('guest:web')->group(function () {
    Route::view('/login', 'auth.staff-login')->name('login');
    Route::get('/auth/microsoft/redirect', [MicrosoftLoginController::class, 'redirect'])->name('login.microsoft');
    Route::get('/auth/microsoft/callback', [MicrosoftLoginController::class, 'callback'])->name('login.microsoft.callback');
});

// Afrit 6 member portal (members have their own login, separate from staff).
Route::prefix('afrit6')->name('afrit6.')->group(function () {
    Route::middleware('guest:member')->group(function () {
        Route::get('/', [MemberAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [MemberAuthController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
        Route::get('/register', [MemberAuthController::class, 'showCompany'])->name('register');
        Route::post('/register', [MemberAuthController::class, 'storeCompany'])->middleware('throttle:20,1')->name('register.company');
        Route::get('/register/details', [MemberAuthController::class, 'showDetails'])->name('register.details');
        Route::post('/register/details', [MemberAuthController::class, 'storeDetails'])->middleware('throttle:10,1')->name('register.store');
        Route::get('/check-inbox', [MemberAuthController::class, 'checkInbox'])->name('check-inbox');
        Route::post('/check-inbox/resend', [MemberAuthController::class, 'resend'])->middleware('throttle:6,1')->name('resend');
        Route::get('/forgot-password', [MemberAuthController::class, 'showForgot'])->name('password.request');
        Route::post('/forgot-password', [MemberAuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('/reset-password/{token}', [MemberAuthController::class, 'showReset'])->name('password.reset');
        Route::post('/reset-password', [MemberAuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
    });
    Route::get('/terms/{doc}', fn (string $doc) => view('afrit6.terms', ['doc' => $doc]))->whereIn('doc', ['rules', 'payment'])->name('terms');
    Route::get('/verify/{member}/{hash}', [MemberAuthController::class, 'verify'])->middleware(['signed', 'throttle:20,1'])->whereNumber('member')->name('verify');
    Route::middleware('auth:member')->group(function () {
        Route::get('/portal', [PortalController::class, 'home'])->name('portal');
        Route::get('/accept', [PortalController::class, 'showAccept'])->name('accept');
        Route::post('/accept', [PortalController::class, 'accept'])->name('accept.store');
        Route::middleware('member.active')->prefix('portal')->name('portal.')->group(function () {
            Route::get('/desks', [WorkspaceController::class, 'desks'])->name('desks');
            Route::post('/desks/swap', [WorkspaceController::class, 'swapDesk'])->middleware('throttle:20,1')->name('desks.swap');
            Route::post('/desks/add', [WorkspaceController::class, 'addDesk'])->middleware('throttle:20,1')->name('desks.add');
            Route::post('/desks/day', [WorkspaceController::class, 'bookDay'])->middleware('throttle:30,1')->name('desks.day');
            Route::post('/desks/day/{deskBooking}/cancel', [WorkspaceController::class, 'cancelDay'])->whereNumber('deskBooking')->name('desks.day.cancel');
            Route::post('/office', [WorkspaceController::class, 'requestOffice'])->middleware('throttle:10,1')->name('office');
            Route::get('/room', [WorkspaceController::class, 'room'])->name('room');
            Route::post('/room', [WorkspaceController::class, 'book'])->middleware('throttle:30,1')->name('room.book');
            Route::post('/room/{booking}/cancel', [WorkspaceController::class, 'cancelBooking'])->whereNumber('booking')->name('room.cancel');
            Route::get('/services', [WorkspaceController::class, 'services'])->name('services');
            Route::post('/services', [WorkspaceController::class, 'storeService'])->middleware('throttle:30,1')->name('services.store');
            Route::post('/services/{serviceRequest}/cancel', [WorkspaceController::class, 'cancelService'])->whereNumber('serviceRequest')->name('services.cancel');
            Route::post('/services/{serviceRequest}/stop', [WorkspaceController::class, 'stopStorage'])->whereNumber('serviceRequest')->name('services.stop');
            Route::post('/services/{serviceRequest}/reply', [WorkspaceController::class, 'reply'])->whereNumber('serviceRequest')->middleware('throttle:30,1')->name('services.reply');
            Route::get('/bill', [WorkspaceController::class, 'bill'])->name('bill');
        });
        Route::post('/logout', [MemberAuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [MicrosoftLoginController::class, 'logout'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Pictures (served only to signed-in staff).
    Route::get('/media/users/{user}', [MediaController::class, 'user'])->whereNumber('user')->name('media.user');
    Route::get('/media/companies/{company}', [MediaController::class, 'company'])->whereNumber('company')->name('media.company');
    Route::get('/media/contacts/{contact}', [MediaController::class, 'contact'])->whereNumber('contact')->name('media.contact');
    Route::get('/media/items/{photo}', [MediaController::class, 'itemPhoto'])->whereNumber('photo')->name('media.item');
    Route::post('/users/{user}/photo', [PictureController::class, 'storeUserPhoto'])->whereNumber('user')->name('users.photo');

    // Companies & contacts: view from level 1, change from level 2, archive at level 3.
    Route::middleware('nav:companies')->group(function () {
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::middleware('level:companies,2')->group(function () {
            Route::get('/companies/new', [CompanyController::class, 'create'])->name('companies.create');
            Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
            Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->whereNumber('company')->name('companies.edit');
            Route::put('/companies/{company}', [CompanyController::class, 'update'])->whereNumber('company')->name('companies.update');
            Route::post('/companies/{company}/vies', [CompanyController::class, 'vies'])->whereNumber('company')->name('companies.vies');
            Route::post('/companies/{company}/contacts', [CompanyContactController::class, 'store'])->whereNumber('company')->name('companies.contacts.store');
            Route::put('/companies/{company}/contacts/{contact}', [CompanyContactController::class, 'update'])->whereNumber(['company', 'contact'])->name('companies.contacts.update');
            Route::delete('/companies/{company}/contacts/{contact}', [CompanyContactController::class, 'destroy'])->whereNumber(['company', 'contact'])->name('companies.contacts.destroy');
            Route::post('/companies/{company}/logo', [PictureController::class, 'storeLogo'])->whereNumber('company')->name('companies.logo');
            Route::delete('/companies/{company}/logo', [PictureController::class, 'destroyLogo'])->whereNumber('company')->name('companies.logo.destroy');
            Route::post('/companies/{company}/contacts/{contact}/photo', [PictureController::class, 'storeContactPhoto'])->whereNumber(['company', 'contact'])->name('companies.contacts.photo');
            Route::delete('/companies/{company}/contacts/{contact}/photo', [PictureController::class, 'destroyContactPhoto'])->whereNumber(['company', 'contact'])->name('companies.contacts.photo.destroy');
            Route::post('/companies/{company}/addresses', [CompanyContactController::class, 'storeAddress'])->whereNumber('company')->name('companies.addresses.store');
            Route::delete('/companies/{company}/addresses/{address}', [CompanyContactController::class, 'destroyAddress'])->whereNumber(['company', 'address'])->name('companies.addresses.destroy');
        });
        Route::middleware('level:companies,3')->group(function () {
            Route::post('/companies/{company}/archive', [CompanyController::class, 'archive'])->whereNumber('company')->name('companies.archive');
            Route::post('/companies/{company}/restore', [CompanyController::class, 'restore'])->whereNumber('company')->name('companies.restore');
        });
        Route::put('/companies/{company}/vat', [CompanyController::class, 'updateVat'])->whereNumber('company')->middleware('right:finance.vat_treatment')->name('companies.vat');
        Route::get('/companies/{company}', [CompanyController::class, 'show'])->whereNumber('company')->name('companies.show');
    });

    // Sales: pipeline of deals and the Incoming inbox. View from level 1, work from level 2.
    Route::middleware('nav:sales')->prefix('sales')->group(function () {
        Route::get('/', [DealController::class, 'index'])->name('deals.index');
        Route::get('/incoming', [IncomingController::class, 'index'])->name('incoming.index');
        Route::get('/deals/{deal}', [DealController::class, 'show'])->whereNumber('deal')->name('deals.show');
        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->whereNumber('quote')->name('quotes.show');
        Route::get('/quotes/{quote}/print', [QuoteController::class, 'print'])->whereNumber('quote')->name('quotes.print');
        Route::middleware('level:sales,2')->group(function () {
            Route::get('/deals/new', [DealController::class, 'create'])->name('deals.create');
            Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
            Route::put('/deals/{deal}', [DealController::class, 'update'])->whereNumber('deal')->name('deals.update');
            Route::post('/deals/{deal}/checks', [DealController::class, 'checks'])->whereNumber('deal')->name('deals.checks');
            Route::post('/deals/{deal}/move', [DealController::class, 'move'])->whereNumber('deal')->name('deals.move');
            Route::post('/deals/{deal}/win', [DealController::class, 'win'])->whereNumber('deal')->name('deals.win');
            Route::post('/deals/{deal}/lose', [DealController::class, 'lose'])->whereNumber('deal')->name('deals.lose');
            Route::post('/deals/{deal}/disqualify', [DealController::class, 'disqualify'])->whereNumber('deal')->name('deals.disqualify');
            Route::post('/deals/{deal}/reopen', [DealController::class, 'reopen'])->whereNumber('deal')->name('deals.reopen');
            Route::post('/deals/{deal}/notes', [DealController::class, 'note'])->whereNumber('deal')->name('deals.note');
            Route::post('/deals/{deal}/contact', [DealController::class, 'addContact'])->whereNumber('deal')->name('deals.contact');
            Route::post('/deals/{deal}/quotes', [QuoteController::class, 'store'])->whereNumber('deal')->name('quotes.store');
            Route::put('/quotes/{quote}', [QuoteController::class, 'update'])->whereNumber('quote')->name('quotes.update');
            Route::post('/quotes/{quote}/lines', [QuoteController::class, 'addLine'])->whereNumber('quote')->name('quotes.lines.add');
            Route::delete('/quotes/{quote}/lines/{line}', [QuoteController::class, 'removeLine'])->whereNumber('quote')->whereNumber('line')->name('quotes.lines.remove');
            Route::get('/quotes/{quote}/search', [QuoteController::class, 'search'])->whereNumber('quote')->name('quotes.search');
            Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->whereNumber('quote')->name('quotes.send');
            Route::post('/quotes/{quote}/revise', [QuoteController::class, 'revise'])->whereNumber('quote')->name('quotes.revise');
            Route::post('/quotes/{quote}/accepted', [QuoteController::class, 'markAccepted'])->whereNumber('quote')->name('quotes.accepted');
            Route::post('/incoming', [IncomingController::class, 'store'])->name('incoming.store');
            Route::post('/incoming/{item}/convert', [IncomingController::class, 'convert'])->whereNumber('item')->name('incoming.convert');
            Route::post('/incoming/{item}/link', [IncomingController::class, 'link'])->whereNumber('item')->name('incoming.link');
            Route::post('/incoming/{item}/ask', [IncomingController::class, 'ask'])->whereNumber('item')->name('incoming.ask');
            Route::post('/incoming/{item}/dismiss', [IncomingController::class, 'dismiss'])->whereNumber('item')->name('incoming.dismiss');
        });
    });

    // Dry-hire equipment: view from level 1, change from level 2.
    Route::middleware('nav:dryhire')->prefix('dry-hire')->group(function () {
        Route::get('/', [ItemController::class, 'index'])->name('equipment.index');
        Route::get('/items/{item}', [ItemController::class, 'show'])->whereNumber('item')->name('equipment.show');
        Route::get('/items/{item}/photo', [ItemController::class, 'photo'])->whereNumber('item')->name('equipment.photo');
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('equipment.assets.show');
        Route::middleware('level:dryhire,2')->group(function () {
            Route::get('/items/new', [ItemController::class, 'create'])->name('equipment.create');
            Route::post('/items', [ItemController::class, 'store'])->name('equipment.store');
            Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->whereNumber('item')->name('equipment.edit');
            Route::put('/items/{item}', [ItemController::class, 'update'])->whereNumber('item')->name('equipment.update');
            Route::post('/items/{item}/components', [ItemController::class, 'addComponent'])->whereNumber('item')->name('equipment.components.store');
            Route::delete('/items/{item}/components/{child}', [ItemController::class, 'removeComponent'])->whereNumber(['item', 'child'])->name('equipment.components.destroy');
            Route::post('/items/{item}/assets', [ItemController::class, 'addAssets'])->whereNumber('item')->name('equipment.assets.store');
            Route::post('/items/{item}/photos', [PictureController::class, 'storeItemPhotos'])->whereNumber('item')->name('equipment.photos.store');
            Route::post('/items/{item}/photos/{photo}/main', [PictureController::class, 'mainItemPhoto'])->whereNumber(['item', 'photo'])->name('equipment.photos.main');
            Route::delete('/items/{item}/photos/{photo}', [PictureController::class, 'destroyItemPhoto'])->whereNumber(['item', 'photo'])->name('equipment.photos.destroy');
            Route::put('/assets/{asset}', [AssetController::class, 'update'])->whereNumber('asset')->name('equipment.assets.update');
            Route::get('/labels', [AssetController::class, 'labels'])->name('equipment.labels');
        });
        Route::post('/assets/{asset}/retire', [AssetController::class, 'retire'])->whereNumber('asset')->name('equipment.assets.retire');
    });
    // A scanned QR label opens the asset card.
    Route::get('/a/{code}', [AssetController::class, 'byCode'])->where('code', '[A-Za-z0-9\-]+')->middleware('nav:dryhire')->name('equipment.scan');

    // Afrit 6 for staff: registrations to approve and members.
    Route::middleware('nav:afrit6')->prefix('afrit6-staff')->name('afrit6.staff.')->group(function () {
        Route::get('/', [MemberAdminController::class, 'index'])->name('index');
        Route::get('/members/{member}', [MemberAdminController::class, 'show'])->whereNumber('member')->name('show');
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->whereNumber('task')->name('tasks.show');
        Route::get('/room', [StaffWorkspaceController::class, 'room'])->name('room');
        Route::get('/desks', [StaffWorkspaceController::class, 'desks'])->name('desks');
        Route::middleware('level:afrit6,2')->group(function () {
            Route::post('/tasks/{task}/take', [TaskController::class, 'take'])->whereNumber('task')->name('tasks.take');
            Route::post('/tasks/{task}/reassign', [TaskController::class, 'reassign'])->whereNumber('task')->name('tasks.reassign');
            Route::post('/tasks/{task}/confirm', [TaskController::class, 'confirm'])->whereNumber('task')->name('tasks.confirm');
            Route::post('/tasks/{task}/decline', [TaskController::class, 'decline'])->whereNumber('task')->name('tasks.decline');
            Route::post('/tasks/{task}/done', [TaskController::class, 'done'])->whereNumber('task')->name('tasks.done');
            Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->whereNumber('task')->name('tasks.reopen');
            Route::post('/tasks/{task}/message', [TaskController::class, 'message'])->whereNumber('task')->name('tasks.message');
            Route::post('/tasks/{task}/amount', [TaskController::class, 'amount'])->whereNumber('task')->name('tasks.amount');
            Route::post('/members/{member}/desks', [StaffWorkspaceController::class, 'storeDesk'])->whereNumber('member')->name('desks.store');
            Route::post('/members/{member}/desks/{rental}/end', [StaffWorkspaceController::class, 'endDesk'])->whereNumber(['member', 'rental'])->name('desks.end');
        });
        Route::put('/members/{member}/door-code', [StaffWorkspaceController::class, 'doorCode'])->whereNumber('member')->middleware('right:afrit6.door_codes')->name('door');
        Route::post('/members/{member}/statements', [StaffWorkspaceController::class, 'storeStatement'])->whereNumber('member')->middleware('level:afrit6,3')->name('statements.store');
        Route::middleware('right:afrit6.approve_members')->group(function () {
            Route::post('/members/{member}/approve', [MemberAdminController::class, 'approve'])->whereNumber('member')->name('approve');
            Route::post('/members/{member}/decline', [MemberAdminController::class, 'decline'])->whereNumber('member')->name('decline');
            Route::post('/members/{member}/block', [MemberAdminController::class, 'block'])->whereNumber('member')->name('block');
            Route::post('/members/{member}/unblock', [MemberAdminController::class, 'unblock'])->whereNumber('member')->name('unblock');
            Route::post('/members/{member}/reset-link', [MemberAdminController::class, 'sendReset'])->whereNumber('member')->name('reset');
        });
    });

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

    // Settings › Company details and VAT rates.
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::middleware('nav:company')->group(function () {
            Route::get('/company', [CompanyDetailsController::class, 'edit'])->name('company.edit');
            Route::put('/company', [CompanyDetailsController::class, 'update'])->name('company.update');
        });
        Route::middleware('nav:import')->group(function () {
            Route::get('/import', [ImportController::class, 'index'])->name('import.index');
            Route::get('/import/template', [ImportController::class, 'template'])->name('import.template');
            Route::post('/import/preview', [ImportController::class, 'preview'])->name('import.preview');
            Route::post('/import/run', [ImportController::class, 'run'])->name('import.run');
            Route::post('/import/vies', [ImportController::class, 'vies'])->name('import.vies');
        });
        Route::middleware('nav:vat')->group(function () {
            Route::get('/vat-rates', [VatRateController::class, 'index'])->name('vat.index');
            Route::post('/vat-rates', [VatRateController::class, 'store'])->name('vat.store');
            Route::put('/vat-rates/defaults', [VatRateController::class, 'updateDefaults'])->name('vat.defaults');
            Route::put('/vat-rates/{rate}', [VatRateController::class, 'update'])->whereNumber('rate')->name('vat.update');
            Route::post('/vat-rates/{rate}/schedule', [VatRateController::class, 'schedule'])->whereNumber('rate')->name('vat.schedule');
            Route::post('/vat-rates/{rate}/end', [VatRateController::class, 'end'])->whereNumber('rate')->name('vat.end');
            Route::delete('/vat-rates/{rate}', [VatRateController::class, 'destroy'])->whereNumber('rate')->name('vat.destroy');
        });
    });

    // Order allocation: confirmed rental orders, covered from own stock or sub-hire, then approved.
    Route::prefix('order-allocation')->middleware('nav:orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::get('/{order}/print', [OrderController::class, 'print'])->whereNumber('order')->name('orders.print');
        Route::post('/{order}/assign', [OrderController::class, 'assign'])->whereNumber('order')->name('orders.assign');
        Route::post('/{order}/auto', [OrderController::class, 'autoAllocate'])->whereNumber('order')->name('orders.auto');
        Route::post('/{order}/lines/{line}/allocate', [OrderController::class, 'allocateLine'])->whereNumber(['order', 'line'])->name('orders.allocate');
        Route::post('/{order}/lines/{line}/clear', [OrderController::class, 'clearLine'])->whereNumber(['order', 'line'])->name('orders.clear');
        Route::delete('/{order}/assets/{orderAsset}', [OrderController::class, 'releaseAsset'])->whereNumber(['order', 'orderAsset'])->name('orders.release');
        Route::post('/{order}/lines/{line}/subhire', [OrderController::class, 'addSubhire'])->whereNumber(['order', 'line'])->name('orders.subhire');
        Route::post('/{order}/send-requests', [OrderController::class, 'sendRequests'])->whereNumber('order')->name('orders.send');
        Route::post('/{order}/approve', [OrderController::class, 'approve'])->whereNumber('order')->name('orders.approve');
        Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->name('orders.cancel');
        Route::get('/{order}/documents/{type}', [OrderDocumentController::class, 'show'])->whereNumber('order')->whereIn('type', array_keys(OrderDocumentController::TYPES))->name('orders.doc');
        Route::post('/{order}/proforma', [OrderDocumentController::class, 'sendProforma'])->whereNumber('order')->name('orders.proforma');
    });

    // Cluk Factory: requests, the hall calendar and bookings. View from level 1, work from level 2.
    Route::prefix('factory')->middleware('nav:factory')->group(function () {
        Route::get('/', [FactoryController::class, 'index'])->name('factory.index');
        Route::get('/bookings/{booking}', [FactoryController::class, 'show'])->whereNumber('booking')->name('factory.show');
        Route::middleware('level:factory,2')->group(function () {
            Route::get('/bookings/new', [FactoryController::class, 'create'])->name('factory.create');
            Route::post('/bookings', [FactoryController::class, 'store'])->name('factory.store');
            Route::get('/bookings/{booking}/edit', [FactoryController::class, 'edit'])->whereNumber('booking')->name('factory.edit');
            Route::put('/bookings/{booking}', [FactoryController::class, 'update'])->whereNumber('booking')->name('factory.update');
            Route::post('/bookings/{booking}/hold', [FactoryController::class, 'hold'])->whereNumber('booking')->name('factory.hold');
            Route::post('/bookings/{booking}/decline', [FactoryController::class, 'decline'])->whereNumber('booking')->name('factory.decline');
            Route::post('/bookings/{booking}/quote', [FactoryController::class, 'quote'])->whereNumber('booking')->name('factory.quote');
            Route::post('/bookings/{booking}/confirm', [FactoryController::class, 'confirm'])->whereNumber('booking')->name('factory.confirm');
            Route::post('/bookings/{booking}/checks', [FactoryController::class, 'checks'])->whereNumber('booking')->name('factory.checks');
            Route::post('/bookings/{booking}/status', [FactoryController::class, 'status'])->whereNumber('booking')->name('factory.status');
        });
    });

    // Sub-hire: price requests (SHQ) and confirmations (SHC).
    Route::prefix('sub-hire')->middleware('nav:subhire')->group(function () {
        Route::get('/', [SubhireController::class, 'index'])->name('subhire.index');
        Route::get('/{subhire}', [SubhireController::class, 'show'])->whereNumber('subhire')->name('subhire.show');
        Route::put('/{subhire}', [SubhireController::class, 'update'])->whereNumber('subhire')->name('subhire.update');
        Route::delete('/{subhire}/lines/{line}', [SubhireController::class, 'removeLine'])->whereNumber(['subhire', 'line'])->name('subhire.line.remove');
        Route::post('/{subhire}/accept', [SubhireController::class, 'accept'])->whereNumber('subhire')->name('subhire.accept');
        Route::post('/{subhire}/not-available', [SubhireController::class, 'notAvailable'])->whereNumber('subhire')->name('subhire.na');
        Route::post('/{subhire}/resend', [SubhireController::class, 'resend'])->whereNumber('subhire')->name('subhire.resend');
        Route::post('/{subhire}/cancel', [SubhireController::class, 'cancel'])->whereNumber('subhire')->name('subhire.cancel');
    });

    // Every menu item gets its address now; screens not built yet show
    // "coming soon". Access is checked against the same rules as the menu.
    $items = array_merge(config('navigation.top'), config('navigation.account'), ...array_values(config('navigation.groups')));
    foreach ($items as $item) {
        if (in_array($item['key'], ['dashboard', 'companies', 'sales', 'orders', 'subhire', 'factory', 'afrit6', 'dryhire', 'users', 'company', 'vat', 'import'], true)) {
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

    if ($user = $request->user('web')) {
        $user->forceFill(['locale' => $locale])->save();
    }
    if ($member = $request->user('member')) {
        $member->forceFill(['locale' => $locale])->save();
    }

    return back();
})->name('locale.switch');
