<?php

use App\Http\Controllers\Afrit6\MemberAdminController;
use App\Http\Controllers\Afrit6\MemberAuthController;
use App\Http\Controllers\Afrit6\PortalController;
use App\Http\Controllers\Afrit6\StaffWorkspaceController;
use App\Http\Controllers\Afrit6\TaskController;
use App\Http\Controllers\Afrit6\WorkspaceController;
use App\Http\Controllers\Auth\EmailCodeLoginController;
use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Http\Controllers\CompanyContactController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Equipment\AssetController;
use App\Http\Controllers\Equipment\ItemController;
use App\Http\Controllers\Equipment\PhotoSuggestionController;
use App\Http\Controllers\Factory\FactoryController;
use App\Http\Controllers\Factory\FactoryPublicController;
use App\Http\Controllers\Factory\FactoryTaskController;
use App\Http\Controllers\Finance\PreInvoiceController;
use App\Http\Controllers\Help\HelpController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\Me\MeController;
use App\Http\Controllers\Media\MessageController;
use App\Http\Controllers\Media\MessagePublicController;
use App\Http\Controllers\Media\WebsiteController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PictureController;
use App\Http\Controllers\ProductSales\CustomerDiscountController;
use App\Http\Controllers\ProductSales\ItemController as SalesItemController;
use App\Http\Controllers\ProductSales\SalesPublicController;
use App\Http\Controllers\ProductSales\SalesQuoteController;
use App\Http\Controllers\Purchasing\PurchasingController;
use App\Http\Controllers\QuotePublicController;
use App\Http\Controllers\Rental\OrderController;
use App\Http\Controllers\Rental\OrderDocumentController;
use App\Http\Controllers\Rental\SubhireController;
use App\Http\Controllers\Sales\DealController;
use App\Http\Controllers\Sales\IncomingController;
use App\Http\Controllers\Sales\QuoteController;
use App\Http\Controllers\Sales\SalesTaskController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\BrandController;
use App\Http\Controllers\Settings\CompanyDetailsController;
use App\Http\Controllers\Settings\DocumentController;
use App\Http\Controllers\Settings\ImportController;
use App\Http\Controllers\Settings\ProductListController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\VatRateController;
use App\Http\Controllers\Social\PlanController as SocialPlanController;
use App\Http\Controllers\Social\SocialController;
use App\Http\Controllers\WallController;
use App\Http\Controllers\Warehouse\LabelController as WarehouseLabelController;
use App\Http\Controllers\Warehouse\LocationController;
use App\Http\Controllers\Warehouse\PhoneAfrit6Controller;
use App\Http\Controllers\Warehouse\PhoneController;
use App\Http\Controllers\Warehouse\PhoneSubhireController;
use App\Http\Controllers\Warehouse\WarehouseController;
use App\Http\Controllers\Web\CustomerVerifyController;
use App\Http\Controllers\WebAccountController;
use App\Http\Controllers\WebStatsController;
use App\Http\Controllers\Work\MeetingController;
use App\Http\Controllers\Work\TodoController;
use App\Services\Finance\PreInvoices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The public website (cluk.live, or /site as preview) comes first.
require __DIR__.'/website.php';

Route::get('/', fn (Request $request) => redirect($request->user('web')?->homeUrl() ?? '/dashboard'));

// Website form → Incoming (token in the X-Intake-Token header).
Route::post('/intake', [IncomingController::class, 'intake'])->middleware('throttle:30,1')->name('intake');

// Website login: the "confirm my email" link (signed, 48 hours). The sign-in and basket pages are on the website.
Route::get('/account/verify/{account}/{hash}', CustomerVerifyController::class)->whereNumber('account')->middleware('signed')->name('customer.verify');

// Pictures and videos of a planned social post, for Facebook, Instagram and LinkedIn to fetch (signed link, 3 days).
Route::get('/social/media/{plan}/{i}/{f}', [SocialController::class, 'media'])->whereNumber(['plan', 'i'])->middleware('signed')->name('social.media');

// Brand logos (public: the website shows them).
Route::get('/brand-logo/{brand}', [BrandController::class, 'logo'])->whereNumber('brand')->name('brand.logo');

// CLUK's general terms and conditions (linked from quotes, emails and documents).
Route::get('/terms/{lang?}', function (?string $lang = null) {
    $files = config('rental.terms_files');
    $file = $files[$lang ?? app()->getLocale()] ?? $files['en'];

    return response()->file(public_path($file), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.basename($file).'"']);
})->where('lang', '[a-z]{2}')->name('terms');

// The public Cluk Factory booking page (also for the website).
Route::get('/book', [FactoryPublicController::class, 'show'])->name('factory.book');
Route::post('/book', [FactoryPublicController::class, 'store'])->middleware('throttle:10,1')->name('factory.book.store');

// Office wall screens: a secret link per screen, no login on the screen PC.
Route::get('/wall/{view}', [WallController::class, 'show'])->whereIn('view', ['planning', 'today'])->middleware('throttle:120,1')->name('wall.show');

// The customer's quote page (link in the quote email).
// Product sales: the customer's link to a sales quote.
Route::prefix('sq/{token}')->where(['token' => '[A-Za-z0-9]{48}'])->middleware('throttle:60,1')->group(function () {
    Route::get('/', [SalesPublicController::class, 'show'])->name('psales.public');
    Route::post('/accept', [SalesPublicController::class, 'accept'])->name('psales.public.accept');
    Route::post('/decline', [SalesPublicController::class, 'decline'])->name('psales.public.decline');
});
// A sales item PDF from a link in an email (signed, 30 days).
Route::get('/d/{doc}', [SalesItemController::class, 'publicDoc'])->whereNumber('doc')->middleware('signed')->name('psales.doc.public');

// Media › Messages: what people who got a mail can open (signed links).
Route::middleware(['signed', 'throttle:120,1'])->group(function () {
    Route::get('/m/{message}/image', [MessagePublicController::class, 'image'])->whereNumber('message')->name('messages.public.image');
    Route::get('/m/{message}/pdf', [MessagePublicController::class, 'pdf'])->whereNumber('message')->name('messages.public.pdf');
    Route::match(['get', 'post'], '/unsubscribe/{list}/{send}', [MessagePublicController::class, 'unsubscribe'])->whereIn('list', ['newsletter', 'promo'])->whereNumber('send')->name('messages.unsubscribe');
});

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
    // Without Microsoft 365 (freelancers): a one-time code by email to their @cluk.live address.
    Route::get('/login/code', [EmailCodeLoginController::class, 'form'])->name('login.code');
    Route::post('/login/code', [EmailCodeLoginController::class, 'send'])->middleware('throttle:6,1')->name('login.code.send');
    Route::post('/login/code/verify', [EmailCodeLoginController::class, 'verify'])->middleware('throttle:10,1')->name('login.code.verify');
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
            Route::post('/door-code', [PortalController::class, 'requestDoorCode'])->middleware('throttle:5,1')->name('door');
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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // Media › Messages: birthday card, newsletters, promotions, banner on quotes.
    Route::prefix('media/messages')->middleware('nav:messages')->group(function () {
        Route::get('/', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/{message}', [MessageController::class, 'edit'])->whereNumber('message')->name('messages.edit');
        Route::middleware('level:sales,2')->group(function () {
            Route::get('/new', [MessageController::class, 'create'])->name('messages.create');
            Route::post('/', [MessageController::class, 'store'])->name('messages.store');
            Route::put('/{message}', [MessageController::class, 'update'])->whereNumber('message')->name('messages.update');
            Route::post('/{message}/translate', [MessageController::class, 'translate'])->whereNumber('message')->name('messages.translate');
            Route::post('/{message}/test', [MessageController::class, 'test'])->whereNumber('message')->middleware('throttle:10,1')->name('messages.test');
            Route::post('/{message}/send', [MessageController::class, 'send'])->whereNumber('message')->name('messages.send');
            Route::post('/{message}/schedule', [MessageController::class, 'schedule'])->whereNumber('message')->name('messages.schedule');
            Route::post('/{message}/unschedule', [MessageController::class, 'unschedule'])->whereNumber('message')->name('messages.unschedule');
            Route::post('/{message}/toggle', [MessageController::class, 'toggle'])->whereNumber('message')->name('messages.toggle');
            Route::post('/{message}/move/{dir}', [MessageController::class, 'move'])->whereNumber('message')->whereIn('dir', ['up', 'down'])->name('messages.move');
            Route::delete('/{message}', [MessageController::class, 'destroy'])->whereNumber('message')->name('messages.destroy');
        });
    });
    // Media › Social media: dashboard of the connected accounts, and posts planned in CLUK.
    Route::prefix('media/social')->middleware('nav:social')->name('social.')->group(function () {
        Route::get('/', [SocialController::class, 'index'])->name('index');
        Route::middleware('right:social.connect')->group(function () {
            Route::get('/connect/{provider}', [SocialController::class, 'connect'])->whereIn('provider', ['meta', 'linkedin', 'google'])->name('connect');
            Route::post('/accounts/{account}/disconnect', [SocialController::class, 'disconnect'])->whereNumber('account')->name('disconnect');
            Route::post('/sync', [SocialController::class, 'sync'])->middleware('throttle:6,1')->name('sync');
        });
        Route::get('/posts', [SocialPlanController::class, 'index'])->name('plans');
        Route::post('/website/{post}', [SocialPlanController::class, 'website'])->whereNumber('post')->name('posts.website');
        Route::get('/posts/{plan}', [SocialPlanController::class, 'show'])->whereNumber('plan')->name('plans.show');
        Route::get('/posts/new', [SocialPlanController::class, 'create'])->name('plans.create');
        Route::post('/posts', [SocialPlanController::class, 'store'])->name('plans.store');
        Route::get('/posts/{plan}/edit', [SocialPlanController::class, 'edit'])->whereNumber('plan')->name('plans.edit');
        Route::put('/posts/{plan}', [SocialPlanController::class, 'update'])->whereNumber('plan')->name('plans.update');
        Route::delete('/posts/{plan}', [SocialPlanController::class, 'destroy'])->whereNumber('plan')->name('plans.destroy');
        Route::middleware('right:social.publish')->group(function () {
            Route::post('/posts/{plan}/unschedule', [SocialPlanController::class, 'unschedule'])->whereNumber('plan')->name('plans.unschedule');
            Route::post('/posts/{plan}/retry', [SocialPlanController::class, 'retry'])->whereNumber('plan')->name('plans.retry');
        });
    });
    // Back from the platform's sign-in (an address the platforms know; must stay the same).
    Route::get('/social/connect/{provider}/callback', [SocialController::class, 'callback'])->whereIn('provider', ['meta', 'linkedin', 'google'])->middleware('right:social.connect')->name('social.callback');
    // Media › Website: pictures, videos and texts of the homepage, dry-hire and sales pages.
    Route::prefix('media/website')->middleware('nav:website')->name('website.')->group(function () {
        Route::get('/', [WebsiteController::class, 'index'])->name('index');
        Route::get('/template/{format}', [WebsiteController::class, 'template'])->whereIn('format', ['home_hero', 'dryhire_hero', 'sales_slider', 'tile'])->name('template');
        Route::post('/texts/{tab}', [WebsiteController::class, 'texts'])->whereIn('tab', ['home', 'dryhire', 'sales'])->name('texts');
        Route::post('/tiles/{tile}', [WebsiteController::class, 'tile'])->whereIn('tile', ['dryhire', 'sales', 'factory', 'afrit6'])->name('tile');
        Route::post('/settings', [WebsiteController::class, 'general'])->name('general');
        Route::post('/slides', [WebsiteController::class, 'store'])->name('slides.store');
        Route::post('/slides/{slide}', [WebsiteController::class, 'update'])->whereNumber('slide')->name('slides.update');
        Route::post('/slides/{slide}/move/{dir}', [WebsiteController::class, 'move'])->whereNumber('slide')->whereIn('dir', ['up', 'down'])->name('slides.move');
        Route::delete('/slides/{slide}', [WebsiteController::class, 'destroy'])->whereNumber('slide')->name('slides.destroy');
    });
    // Meeting notes: paste the text, check the proposed tasks, hand them out. Tasks: one list per person.
    Route::prefix('meetings')->middleware('nav:meetings')->name('meetings.')->group(function () {
        Route::get('/', [MeetingController::class, 'index'])->name('index');
        Route::get('/new', [MeetingController::class, 'create'])->name('create');
        Route::post('/', [MeetingController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{meeting}', [MeetingController::class, 'show'])->whereNumber('meeting')->name('show');
        Route::put('/{meeting}', [MeetingController::class, 'update'])->whereNumber('meeting')->middleware('throttle:20,1')->name('update');
        Route::post('/{meeting}/tasks', [MeetingController::class, 'makeTasks'])->whereNumber('meeting')->name('tasks');
        Route::post('/{meeting}/dismiss', [MeetingController::class, 'dismiss'])->whereNumber('meeting')->name('dismiss');
        Route::delete('/{meeting}', [MeetingController::class, 'destroy'])->whereNumber('meeting')->name('destroy');
    });
    Route::prefix('tasks')->middleware('nav:todos')->name('todos.')->group(function () {
        Route::get('/', [TodoController::class, 'index'])->name('index');
        Route::post('/', [TodoController::class, 'store'])->name('store');
        Route::post('/{todo}/done', [TodoController::class, 'done'])->whereNumber('todo')->name('done');
        Route::put('/{todo}', [TodoController::class, 'update'])->whereNumber('todo')->name('update');
    });
    // Commercial tasks (made by the app): customer cards, open quotes, website activity.
    Route::prefix('commercial-tasks')->middleware('nav:todos')->name('salestasks.')->group(function () {
        Route::get('/', [SalesTaskController::class, 'index'])->name('index');
        Route::post('/{task}/done', [SalesTaskController::class, 'done'])->whereNumber('task')->name('done');
        Route::post('/{task}/snooze', [SalesTaskController::class, 'snooze'])->whereNumber('task')->name('snooze');
    });
    Route::get('/management', ManagementController::class)->middleware('nav:management')->name('management');
    Route::get('/management/website', WebStatsController::class)->middleware('nav:management')->name('management.website');

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
            Route::post('/companies/assign-owner', [CompanyController::class, 'assignOwner'])->name('companies.assign_owner');
            Route::post('/companies/{company}/vies', [CompanyController::class, 'vies'])->whereNumber('company')->name('companies.vies');
            Route::post('/companies/{company}/contacts', [CompanyContactController::class, 'store'])->whereNumber('company')->name('companies.contacts.store');
            Route::get('/companies/{company}/contacts/{contact}/edit', [CompanyContactController::class, 'edit'])->whereNumber(['company', 'contact'])->name('companies.contacts.edit');
            Route::put('/companies/{company}/contacts/{contact}', [CompanyContactController::class, 'update'])->whereNumber(['company', 'contact'])->name('companies.contacts.update');
            Route::delete('/companies/{company}/contacts/{contact}', [CompanyContactController::class, 'destroy'])->whereNumber(['company', 'contact'])->name('companies.contacts.destroy');
            Route::post('/companies/{company}/logo', [PictureController::class, 'storeLogo'])->whereNumber('company')->name('companies.logo');
            Route::post('/companies/{company}/notes', [CompanyController::class, 'notes'])->whereNumber('company')->name('companies.notes');
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
        Route::middleware('level:sales,2')->group(function () {
            Route::put('/companies/{company}/rental-discount', [CustomerDiscountController::class, 'rental'])->whereNumber('company')->name('companies.rental_discount');
            Route::post('/companies/{company}/discounts', [CustomerDiscountController::class, 'store'])->whereNumber('company')->name('companies.discounts.store');
            Route::post('/companies/{company}/net-prices', [CustomerDiscountController::class, 'net'])->whereNumber('company')->name('companies.discounts.net');
            Route::delete('/companies/{company}/discounts/{discount}', [CustomerDiscountController::class, 'destroy'])->whereNumber(['company', 'discount'])->name('companies.discounts.destroy');
        });
        Route::put('/companies/{company}/vat', [CompanyController::class, 'updateVat'])->whereNumber('company')->middleware('right:finance.vat_treatment')->name('companies.vat');
        Route::get('/companies/website-logins', [WebAccountController::class, 'index'])->name('web-accounts.index');
        Route::middleware('level:companies,2')->group(function () {
            Route::post('/companies/website-logins/{account}/approve', [WebAccountController::class, 'approve'])->whereNumber('account')->name('web-accounts.approve');
            Route::post('/companies/website-logins/{account}/block', [WebAccountController::class, 'block'])->whereNumber('account')->name('web-accounts.block');
            Route::post('/companies/website-logins/{account}/unblock', [WebAccountController::class, 'unblock'])->whereNumber('account')->name('web-accounts.unblock');
            Route::post('/companies/website-logins/{account}/reset', [WebAccountController::class, 'sendReset'])->whereNumber('account')->name('web-accounts.reset');
            Route::post('/companies/website-logins/{account}/role', [WebAccountController::class, 'role'])->whereNumber('account')->name('web-accounts.role');
        });
        Route::get('/companies/{company}', [CompanyController::class, 'show'])->whereNumber('company')->name('companies.show');
        Route::post('/companies/{company}/credit/refresh', [CreditController::class, 'refresh'])->whereNumber('company')->middleware('throttle:20,1')->name('companies.credit.refresh');
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
        Route::post('/items/{item}/website', [ItemController::class, 'website'])->whereNumber('item')->middleware('right:equipment.website')->name('equipment.website');
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('equipment.assets.show');
        Route::middleware('level:dryhire,2')->group(function () {
            Route::get('/items/new', [ItemController::class, 'create'])->name('equipment.create');
            Route::post('/items', [ItemController::class, 'store'])->name('equipment.store');
            Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->whereNumber('item')->name('equipment.edit');
            Route::put('/items/{item}', [ItemController::class, 'update'])->whereNumber('item')->name('equipment.update');
            Route::post('/items/{item}/components', [ItemController::class, 'addComponent'])->whereNumber('item')->name('equipment.components.store');
            Route::delete('/items/{item}/components/{child}', [ItemController::class, 'removeComponent'])->whereNumber(['item', 'child'])->name('equipment.components.destroy');
            Route::post('/items/{item}/assets', [ItemController::class, 'addAssets'])->whereNumber('item')->name('equipment.assets.store');
            Route::post('/items/{item}/labels', [ItemController::class, 'reserveLabels'])->whereNumber('item')->name('equipment.assets.reserve');
            Route::post('/items/{item}/place', [ItemController::class, 'placePieces'])->whereNumber('item')->name('equipment.assets.place');
            Route::post('/items/{item}/place-counted', [ItemController::class, 'placeCounted'])->whereNumber('item')->name('equipment.counted.place');
            Route::post('/items/{item}/photos', [PictureController::class, 'storeItemPhotos'])->whereNumber('item')->name('equipment.photos.store');
            Route::post('/items/{item}/photos/{photo}/main', [PictureController::class, 'mainItemPhoto'])->whereNumber(['item', 'photo'])->name('equipment.photos.main');
            Route::delete('/items/{item}/photos/{photo}', [PictureController::class, 'destroyItemPhoto'])->whereNumber(['item', 'photo'])->name('equipment.photos.destroy');
            Route::put('/assets/{asset}', [AssetController::class, 'update'])->whereNumber('asset')->name('equipment.assets.update');
            Route::post('/assets/{asset}/place', [AssetController::class, 'place'])->whereNumber('asset')->name('equipment.assets.place_one');
            Route::get('/labels', [AssetController::class, 'labels'])->name('equipment.labels');
            Route::get('/photos-found', [PhotoSuggestionController::class, 'index'])->name('equipment.photos.found');
            Route::get('/photos-found/{suggestion}/{i}', [PhotoSuggestionController::class, 'image'])->whereNumber(['suggestion', 'i'])->name('equipment.photos.found.image');
            Route::post('/photos-found/{suggestion}', [PhotoSuggestionController::class, 'decide'])->whereNumber('suggestion')->name('equipment.photos.found.decide');
            Route::post('/photos-found/bulk', [PhotoSuggestionController::class, 'bulk'])->name('equipment.photos.found.bulk');
        });
        Route::post('/assets/{asset}/retire', [AssetController::class, 'retire'])->whereNumber('asset')->name('equipment.assets.retire');
    });
    // A scanned QR label opens the asset card.
    Route::get('/a/{code}', [AssetController::class, 'byCode'])->where('code', '[A-Za-z0-9\-]+')->middleware('nav:dryhire')->name('equipment.scan');
    Route::get('/i/{number}', [AssetController::class, 'byNumber'])->where('number', '[A-Za-z0-9\-_.&]+')->middleware('nav:dryhire')->name('equipment.item.scan');
    // Labels write the link in capitals (a smaller QR code with bigger squares): /A/…, /I/… work the same.
    Route::get('/A/{code}', [AssetController::class, 'byCode'])->where('code', '[A-Za-z0-9\-]+')->middleware('nav:dryhire');
    Route::get('/I/{number}', [AssetController::class, 'byNumber'])->where('number', '[A-Za-z0-9\-_.&]+')->middleware('nav:dryhire');

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
            Route::post('/members/{member}/vies', [MemberAdminController::class, 'vies'])->whereNumber('member')->name('vies');
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
        Route::put('/users/{user}/door', [UserController::class, 'doorCode'])->whereNumber('user')->middleware('right:afrit6.door_codes')->name('users.door');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->whereNumber('user')->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->whereNumber('user')->name('users.enable');
        Route::post('/users/{user}/instructions', [UserController::class, 'sendInstructions'])->whereNumber('user')->middleware('throttle:10,1')->name('users.instructions');
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
        Route::middleware('nav:documents')->group(function () {
            Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::put('/documents/note/{type}', [DocumentController::class, 'updateNote'])->where('type', '[A-Z0-9\-]+')->name('documents.note');
            Route::put('/documents/season', [DocumentController::class, 'updateSeason'])->name('documents.season');
        });
        Route::middleware('nav:import')->group(function () {
            Route::get('/import', [ImportController::class, 'index'])->name('import.index');
            Route::get('/import/template', [ImportController::class, 'template'])->name('import.template');
            Route::post('/import/export', [ImportController::class, 'export'])->middleware('throttle:10,1')->name('import.export');
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
        Route::post('/{order}/times', [OrderController::class, 'times'])->whereNumber('order')->name('orders.times');
        Route::post('/{order}/transport', [OrderController::class, 'transport'])->whereNumber('order')->name('orders.transport');
        Route::post('/{order}/reopen', [OrderController::class, 'reopen'])->whereNumber('order')->name('orders.reopen');
        Route::get('/{order}/documents/{type}', [OrderDocumentController::class, 'show'])->whereNumber('order')->whereIn('type', array_keys(OrderDocumentController::TYPES))->name('orders.doc');
        Route::post('/{order}/proforma', [OrderDocumentController::class, 'sendProforma'])->whereNumber('order')->name('orders.proforma');
    });

    // Cluk Factory: requests, the hall calendar and bookings. View from level 1, work from level 2.
    Route::prefix('factory')->middleware('nav:factory')->group(function () {
        Route::get('/', [FactoryController::class, 'index'])->name('factory.index');
        Route::get('/bookings/{booking}', [FactoryController::class, 'show'])->whereNumber('booking')->name('factory.show');
        Route::get('/bookings/{booking}/print', [FactoryController::class, 'print'])->whereNumber('booking')->name('factory.print');
        // The person who has a task works on it, whatever their Factory level.
        Route::post('/tasks/{task}/reassign', [FactoryTaskController::class, 'reassign'])->whereNumber('task')->name('factory.tasks.reassign');
        Route::post('/tasks/{task}/done', [FactoryTaskController::class, 'done'])->whereNumber('task')->name('factory.tasks.done');
        Route::post('/tasks/{task}/reopen', [FactoryTaskController::class, 'reopen'])->whereNumber('task')->name('factory.tasks.reopen');
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
            Route::post('/bookings/{booking}/gear', [FactoryController::class, 'gear'])->whereNumber('booking')->name('factory.gear');
            Route::post('/bookings/{booking}/gear/included', [FactoryController::class, 'gearIncluded'])->whereNumber('booking')->name('factory.gear.included');
            Route::post('/bookings/{booking}/tasks', [FactoryTaskController::class, 'store'])->whereNumber('booking')->name('factory.tasks.store');
            Route::post('/tasks/{task}/take', [FactoryTaskController::class, 'take'])->whereNumber('task')->name('factory.tasks.take');
            Route::delete('/tasks/{task}', [FactoryTaskController::class, 'destroy'])->whereNumber('task')->name('factory.tasks.destroy');
        });
    });

    // Warehouse: the app on the crew's phone (scan the piece, then where it goes).
    Route::get('/l/{code}', fn (string $code) => redirect()->route('w.scan', ['scanned' => strtoupper($code)]))->where('code', '[A-Za-z0-9\-]+')->middleware('nav:warehouse')->name('w.loc');
    Route::get('/L/{code}', fn (string $code) => redirect()->route('w.scan', ['scanned' => strtoupper($code)]))->where('code', '[A-Za-z0-9\-]+')->middleware('nav:warehouse');
    Route::prefix('w')->name('w.')->middleware('nav:warehouse')->group(function () {
        Route::get('/', [PhoneController::class, 'home'])->name('home');
        Route::get('/scan', [PhoneController::class, 'scan'])->name('scan');
        Route::get('/pick/{order}', [PhoneController::class, 'pick'])->whereNumber('order')->name('pick');
        Route::get('/return/{order}', [PhoneController::class, 'returns'])->whereNumber('order')->name('return');
        Route::get('/clean/{asset}', [PhoneController::class, 'clean'])->whereNumber('asset')->name('clean');
        Route::get('/check/{asset}', [PhoneController::class, 'check'])->whereNumber('asset')->name('check');
        Route::get('/photos/{check}/{i}', [PhoneController::class, 'photo'])->whereNumber(['check', 'i'])->name('photo');
        Route::get('/media/{type}/{id}/{i}', [PhoneAfrit6Controller::class, 'media'])->whereIn('type', ['a6', 'delivery'])->whereNumber(['id', 'i'])->name('media');
        Route::get('/afrit6', [PhoneAfrit6Controller::class, 'index'])->middleware('nav:afrit6')->name('a6');
        Route::get('/afrit6/{task}', [PhoneAfrit6Controller::class, 'show'])->whereNumber('task')->middleware('nav:afrit6')->name('a6.show');
        Route::get('/delivery', [PhoneAfrit6Controller::class, 'delivery'])->name('delivery');
        Route::get('/subhire/{subhire}/{dir}', [PhoneSubhireController::class, 'show'])->whereNumber('subhire')->whereIn('dir', PhoneSubhireController::DIRS)->name('sub');
        Route::get('/subhire/{subhire}/{dir}/photos/{i}', [PhoneSubhireController::class, 'media'])->whereNumber(['subhire', 'i'])->whereIn('dir', PhoneSubhireController::DIRS)->name('sub.media');
        Route::middleware('level:warehouse,2')->group(function () {
            Route::post('/scan', [PhoneController::class, 'scanPost'])->middleware('throttle:120,1')->name('scan.post');
            Route::post('/scan/cancel', [PhoneController::class, 'cancel'])->name('scan.cancel');
            Route::post('/carrier/out/{asset}', [PhoneController::class, 'carrierOut'])->whereNumber('asset')->name('carrier.out');
            Route::post('/carrier/mode', [PhoneController::class, 'carrierMode'])->name('carrier.mode');
            Route::post('/pick/{order}/scan', [PhoneController::class, 'pickScan'])->whereNumber('order')->name('pick.scan');
            Route::post('/pick/{order}/lines/{line}', [PhoneController::class, 'pickCount'])->whereNumber(['order', 'line'])->name('count');
            Route::post('/pick/{order}/load', [PhoneController::class, 'load'])->whereNumber('order')->name('load');
            Route::post('/return/{order}/scan', [PhoneController::class, 'returnScan'])->whereNumber('order')->name('return.scan');
            Route::post('/return/{order}/close', [PhoneController::class, 'returnClose'])->whereNumber('order')->name('return.close');
            Route::post('/return/{order}/carrier-done', [PhoneController::class, 'returnCarrierDone'])->whereNumber('order')->name('return.carrier_done');
            Route::post('/clean/{asset}', [PhoneController::class, 'cleanSave'])->whereNumber('asset')->name('clean.save');
            Route::post('/check/{asset}', [PhoneController::class, 'checkSave'])->whereNumber('asset')->name('check.save');
            Route::post('/delivery', [PhoneAfrit6Controller::class, 'deliveryStore'])->name('delivery.store');
            Route::post('/subhire/{subhire}/{dir}/lines/{line}', [PhoneSubhireController::class, 'count'])->whereNumber(['subhire', 'line'])->whereIn('dir', PhoneSubhireController::DIRS)->name('sub.count');
            Route::post('/subhire/{subhire}/{dir}/photo', [PhoneSubhireController::class, 'photo'])->whereNumber('subhire')->whereIn('dir', PhoneSubhireController::DIRS)->name('sub.photo');
            Route::post('/subhire/{subhire}/{dir}/done', [PhoneSubhireController::class, 'done'])->whereNumber('subhire')->whereIn('dir', PhoneSubhireController::DIRS)->name('sub.done');
            Route::middleware('nav:afrit6')->group(function () {
                Route::post('/afrit6/{task}/photo', [PhoneAfrit6Controller::class, 'photo'])->whereNumber('task')->name('a6.photo');
                Route::post('/afrit6/{task}/scan', [PhoneAfrit6Controller::class, 'scan'])->whereNumber('task')->name('a6.scan');
                Route::post('/afrit6/{task}/done', [PhoneAfrit6Controller::class, 'done'])->whereNumber('task')->name('a6.done');
            });
        });
    });

    // Warehouse for the office: where everything is, repairs, charges to approve.
    Route::prefix('warehouse')->middleware('nav:warehouse')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('warehouse.index');
        Route::get('/labels', [WarehouseLabelController::class, 'index'])->name('warehouse.labels');
        Route::post('/labels/locations', [WarehouseLabelController::class, 'locations'])->middleware('level:warehouse,2')->name('warehouse.labels.locations');
        Route::post('/labels/items', [WarehouseLabelController::class, 'items'])->middleware('level:warehouse,2')->name('warehouse.labels.items');
        Route::post('/assets/{asset}/release', [WarehouseController::class, 'release'])->whereNumber('asset')->middleware('right:warehouse.release_repair')->name('warehouse.release');
        Route::post('/deliveries/{delivery}', [PhoneAfrit6Controller::class, 'assign'])->whereNumber('delivery')->middleware('level:warehouse,2')->name('warehouse.delivery.assign');
        Route::get('/charges/{order}', [WarehouseController::class, 'charges'])->whereNumber('order')->name('warehouse.charges');
        Route::post('/charges/line/{charge}', [WarehouseController::class, 'updateCharge'])->whereNumber('charge')->name('warehouse.charge.update');
        Route::post('/charges/{order}/approve', [WarehouseController::class, 'approveCharges'])->whereNumber('order')->name('warehouse.charges.approve');
        Route::get('/charges/{order}/proforma', [WarehouseController::class, 'proforma'])->whereNumber('order')->name('warehouse.charges.proforma');
    });

    // Settings › Product categories: the fixed lists behind the catalogue and the website.
    Route::prefix('settings/product-categories')->middleware('right:products.classify')->name('settings.products.')->group(function () {
        Route::get('/', [ProductListController::class, 'index'])->name('index');
        Route::post('/categories', [ProductListController::class, 'storeCategory'])->name('category.store');
        Route::put('/categories/{category}', [ProductListController::class, 'updateCategory'])->whereNumber('category')->name('category.update');
        Route::delete('/categories/{category}', [ProductListController::class, 'destroyCategory'])->whereNumber('category')->name('category.destroy');
        Route::post('/categories/{category}/subcategories', [ProductListController::class, 'storeSubcategory'])->whereNumber('category')->name('subcategory.store');
        Route::put('/subcategories/{subcategory}', [ProductListController::class, 'updateSubcategory'])->whereNumber('subcategory')->name('subcategory.update');
        Route::delete('/subcategories/{subcategory}', [ProductListController::class, 'destroySubcategory'])->whereNumber('subcategory')->name('subcategory.destroy');
        Route::post('/attributes', [ProductListController::class, 'storeAttribute'])->name('attribute.store');
        Route::put('/attributes/{attribute}', [ProductListController::class, 'updateAttribute'])->whereNumber('attribute')->name('attribute.update');
        Route::post('/attributes/{attribute}/options', [ProductListController::class, 'storeOption'])->whereNumber('attribute')->name('option.store');
        Route::put('/options/{option}', [ProductListController::class, 'updateOption'])->whereNumber('option')->name('option.update');
        Route::delete('/options/{option}', [ProductListController::class, 'destroyOption'])->whereNumber('option')->name('option.destroy');
        Route::post('/tags', [ProductListController::class, 'storeTag'])->name('tag.store');
        Route::put('/tags/{tag}', [ProductListController::class, 'updateTag'])->whereNumber('tag')->name('tag.update');
        Route::delete('/tags/{tag}', [ProductListController::class, 'destroyTag'])->whereNumber('tag')->name('tag.destroy');
        Route::get('/brands', [BrandController::class, 'index'])->name('brands');
        Route::post('/brands', [BrandController::class, 'store'])->name('brand.store');
        Route::put('/brands/{brand}', [BrandController::class, 'update'])->whereNumber('brand')->name('brand.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->whereNumber('brand')->name('brand.destroy');
        Route::post('/brands/{brand}/merge', [BrandController::class, 'merge'])->whereNumber('brand')->name('brand.merge');
    });

    // Settings › Warehouse locations: zones, route, storage positions, labels.
    Route::prefix('settings/warehouse-locations')->middleware('nav:locations')->group(function () {
        Route::get('/', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/', [LocationController::class, 'store'])->name('locations.store');
        Route::put('/zones/{zone}', [LocationController::class, 'update'])->whereNumber('zone')->name('locations.update');
        Route::get('/positions', [LocationController::class, 'positions'])->name('locations.positions');
        Route::put('/positions/{position}', [LocationController::class, 'updatePosition'])->whereNumber('position')->name('locations.position.update');
        Route::post('/positions/{position}/switch', [LocationController::class, 'switchLayer'])->whereNumber('position')->name('locations.position.switch');
        Route::get('/labels', [LocationController::class, 'labels'])->name('locations.labels');
    });

    // Sales products: the catalogue of what we sell, with suppliers and PDFs (Operations).
    Route::prefix('sales-products')->middleware('nav:productsales')->group(function () {
        Route::get('/', [SalesItemController::class, 'index'])->name('psales.items');
        Route::get('/{item}/photo', [SalesItemController::class, 'photoShow'])->whereNumber('item')->name('psales.items.photo.show');
        Route::get('/{item}/documents/{doc}', [SalesItemController::class, 'download'])->whereNumber(['item', 'doc'])->name('psales.items.doc');
        Route::middleware('right:sales.edit_items')->group(function () {
            Route::post('/', [SalesItemController::class, 'store'])->name('psales.items.store');
            Route::put('/{item}', [SalesItemController::class, 'update'])->whereNumber('item')->name('psales.items.update');
            Route::post('/{item}/photo', [SalesItemController::class, 'photo'])->whereNumber('item')->name('psales.items.photo');
            Route::post('/{item}/suppliers', [SalesItemController::class, 'supplier'])->whereNumber('item')->name('psales.items.supplier');
            Route::post('/{item}/documents', [SalesItemController::class, 'upload'])->whereNumber('item')->name('psales.items.upload');
            Route::delete('/{item}/documents/{doc}', [SalesItemController::class, 'removeDoc'])->whereNumber(['item', 'doc'])->name('psales.items.doc.remove');
        });
        Route::post('/{item}/send', [SalesItemController::class, 'send'])->whereNumber('item')->middleware('level:sales,2')->name('psales.items.send');
    });
    // Sales quotes (S-QUO): part of Quotes & sales, made from a deal of the kind "Sales".
    Route::prefix('sales/sales-quotes')->middleware('nav:sales')->group(function () {
        Route::get('/', [SalesQuoteController::class, 'index'])->name('psales.index');
        Route::get('/{quote}', [SalesQuoteController::class, 'show'])->whereNumber('quote')->name('psales.show');
        Route::get('/{quote}/print/{type}', [SalesQuoteController::class, 'print'])->whereNumber('quote')->whereIn('type', ['quo', 'oc', 'pick', 'dn', 'pf'])->name('psales.print');
        Route::middleware('level:sales,2')->group(function () {
            Route::get('/new', [SalesQuoteController::class, 'create'])->name('psales.create');
            Route::post('/', [SalesQuoteController::class, 'store'])->name('psales.store');
            Route::put('/{quote}', [SalesQuoteController::class, 'update'])->whereNumber('quote')->name('psales.update');
            Route::post('/{quote}/lines', [SalesQuoteController::class, 'addLine'])->whereNumber('quote')->name('psales.lines.add');
            Route::delete('/{quote}/lines/{line}', [SalesQuoteController::class, 'removeLine'])->whereNumber(['quote', 'line'])->name('psales.lines.remove');
            Route::post('/{quote}/approve', [SalesQuoteController::class, 'approve'])->whereNumber('quote')->name('psales.approve');
            Route::post('/{quote}/send', [SalesQuoteController::class, 'send'])->whereNumber('quote')->name('psales.send');
            Route::post('/{quote}/accept', [SalesQuoteController::class, 'accept'])->whereNumber('quote')->name('psales.accept');
            Route::post('/{quote}/confirm', [SalesQuoteController::class, 'confirm'])->whereNumber('quote')->name('psales.confirm');
            Route::post('/{quote}/deliver', [SalesQuoteController::class, 'deliver'])->whereNumber('quote')->name('psales.deliver');
            Route::post('/{quote}/cancel', [SalesQuoteController::class, 'cancel'])->whereNumber('quote')->name('psales.cancel');
        });
    });
    // Old addresses (bookmarks, emails) keep working.
    Route::get('/product-sales/{rest?}', function (?string $rest = null) {
        $rest = (string) $rest;
        $to = match (true) {
            str_starts_with($rest, 'quotes') => '/sales/sales-quotes'.substr($rest, 6),
            str_starts_with($rest, 'items') => '/sales-products'.substr($rest, 5),
            default => '/sales/sales-quotes',
        };

        return redirect($to.(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301);
    })->where('rest', '.*');

    // Purchasing: price requests (PQ) and purchase orders (PO).
    Route::prefix('purchasing')->middleware('nav:purchasing')->group(function () {
        Route::get('/', [PurchasingController::class, 'index'])->name('purchasing.index');
        Route::get('/requests/{pq}', [PurchasingController::class, 'pq'])->whereNumber('pq')->name('purchasing.pq');
        Route::get('/requests/{pq}/print', [PurchasingController::class, 'printPq'])->whereNumber('pq')->name('purchasing.pq.print');
        Route::get('/orders/{po}', [PurchasingController::class, 'po'])->whereNumber('po')->name('purchasing.po');
        Route::get('/orders/{po}/print', [PurchasingController::class, 'printPo'])->whereNumber('po')->name('purchasing.po.print');
        Route::middleware('level:purchasing,2')->group(function () {
            Route::get('/requests/new', [PurchasingController::class, 'create'])->name('purchasing.create');
            Route::post('/requests', [PurchasingController::class, 'store'])->name('purchasing.store');
            Route::post('/requests/{pq}/send', [PurchasingController::class, 'send'])->whereNumber('pq')->name('purchasing.send');
            Route::post('/requests/{pq}/replies', [PurchasingController::class, 'replies'])->whereNumber('pq')->name('purchasing.replies');
            Route::post('/requests/{pq}/cancel', [PurchasingController::class, 'cancel'])->whereNumber('pq')->name('purchasing.cancel');
            Route::post('/orders/{po}/confirm', [PurchasingController::class, 'confirm'])->whereNumber('po')->name('purchasing.confirm');
            Route::post('/orders/{po}/receive', [PurchasingController::class, 'receive'])->whereNumber('po')->name('purchasing.receive');
            Route::post('/orders/{po}/invoice', [PurchasingController::class, 'invoice'])->whereNumber('po')->name('purchasing.invoice');
        });
    });

    // Finance › Pre-invoicing: every proforma passes the accountant before OkiOki.
    Route::post('/pre-invoicing/make/{type}/{id}', [PreInvoiceController::class, 'make'])->whereIn('type', PreInvoices::TYPES)->whereNumber('id')->name('finance.make');
    Route::post('/pre-invoicing/{pf}/resend', [PreInvoiceController::class, 'resend'])->whereNumber('pf')->name('finance.resend');
    Route::prefix('pre-invoicing')->middleware('nav:preinvoice')->group(function () {
        Route::get('/', [PreInvoiceController::class, 'index'])->name('finance.index');
        Route::get('/{pf}/print', [PreInvoiceController::class, 'print'])->whereNumber('pf')->name('finance.print');
        Route::middleware('level:preinvoice,2')->group(function () {
            Route::post('/{pf}/accept', [PreInvoiceController::class, 'accept'])->whereNumber('pf')->name('finance.accept');
            Route::post('/{pf}/okioki', [PreInvoiceController::class, 'okiokiResend'])->whereNumber('pf')->name('finance.okioki.resend');
            Route::get('/{pf}/e-invoice', [PreInvoiceController::class, 'ubl'])->whereNumber('pf')->name('finance.ubl');
            Route::post('/okioki-settings', [PreInvoiceController::class, 'okiokiSettings'])->name('finance.okioki.settings');
            Route::post('/{pf}/back', [PreInvoiceController::class, 'back'])->whereNumber('pf')->name('finance.back');
            Route::post('/{pf}/paid', [PreInvoiceController::class, 'paid'])->whereNumber('pf')->name('finance.paid');
            Route::post('/{pf}/cancel', [PreInvoiceController::class, 'cancel'])->whereNumber('pf')->name('finance.cancel');
            Route::post('/{pf}/late', [PreInvoiceController::class, 'late'])->whereNumber('pf')->name('finance.late');
        });
    });

    // Wall screens: display links and settings.
    Route::middleware('nav:wallscreens')->group(function () {
        Route::get('/wall-screens', [WallController::class, 'index'])->name('wall.index');
        Route::post('/wall-screens/{screen}/renew', [WallController::class, 'renew'])->whereNumber('screen')->name('wall.renew');
        Route::put('/wall-screens/{screen}', [WallController::class, 'update'])->whereNumber('screen')->name('wall.update');
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
        Route::post('/{subhire}/adjust', [SubhireController::class, 'adjust'])->whereNumber('subhire')->name('subhire.adjust');
    });

    // Help & shortcuts: search the answers, download forms, ask the admin.
    Route::prefix('help')->group(function () {
        Route::get('/', [HelpController::class, 'index'])->name('help.index');
        Route::post('/ask', [HelpController::class, 'ask'])->middleware('throttle:10,60')->name('help.ask');
        Route::get('/files/{file}', [HelpController::class, 'file'])->whereNumber('file')->name('help.file');
        Route::middleware('right:help.manage|users.manage')->group(function () {
            Route::get('/manage', [HelpController::class, 'manage'])->name('help.manage');
            Route::get('/new', [HelpController::class, 'create'])->name('help.create');
            Route::post('/articles', [HelpController::class, 'store'])->name('help.store');
            Route::get('/articles/{article}/edit', [HelpController::class, 'edit'])->name('help.edit');
            Route::put('/articles/{article}', [HelpController::class, 'update'])->name('help.update');
            Route::delete('/articles/{article}', [HelpController::class, 'destroy'])->name('help.destroy');
            Route::post('/questions/{question}/answer', [HelpController::class, 'answer'])->whereNumber('question')->name('help.answer');
            Route::post('/questions/{question}/close', [HelpController::class, 'close'])->whereNumber('question')->name('help.close');
            Route::post('/files', [HelpController::class, 'upload'])->name('help.upload');
            Route::delete('/files/{file}', [HelpController::class, 'removeFile'])->whereNumber('file')->name('help.file.remove');
        });
        Route::get('/{article}', [HelpController::class, 'show'])->name('help.show');
    });

    // Under your name: My preferences, My profile, Notifications.
    Route::prefix('me')->group(function () {
        Route::get('/preferences', [MeController::class, 'preferences'])->name('me.preferences');
        Route::put('/preferences', [MeController::class, 'save'])->name('me.preferences.save');
        Route::post('/theme', [MeController::class, 'theme'])->name('me.theme');
        Route::post('/sign-out-others', [MeController::class, 'logoutOthers'])->name('me.logout_others');
        Route::get('/profile', [MeController::class, 'profile'])->name('me.profile');
        Route::put('/profile', [MeController::class, 'saveProfile'])->name('me.profile.save');
        Route::redirect('/notifications', '/me/preferences#notifications')->name('me.notifications');
    });

    // Every menu item gets its address now; screens not built yet show
    // "coming soon". Access is checked against the same rules as the menu.
    $items = array_merge(config('navigation.top'), config('navigation.account'), ...array_values(config('navigation.groups')));
    foreach ($items as $item) {
        if (in_array($item['key'], ['dashboard', 'management', 'messages', 'wallscreens', 'warehouse', 'locations', 'documents', 'productsales', 'purchasing', 'preinvoice', 'companies', 'sales', 'orders', 'subhire', 'factory', 'afrit6', 'dryhire', 'users', 'company', 'vat', 'import', 'preferences', 'profile', 'notifications', 'help', 'productlists', 'social', 'salestasks', 'meetings', 'todos', 'website'], true)) {
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
