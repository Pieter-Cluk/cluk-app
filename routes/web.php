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
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\CompanyDetailsController;
use App\Http\Controllers\Settings\ImportController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\VatRateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

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

    // Every menu item gets its address now; screens not built yet show
    // "coming soon". Access is checked against the same rules as the menu.
    $items = array_merge(config('navigation.top'), config('navigation.account'), ...array_values(config('navigation.groups')));
    foreach ($items as $item) {
        if (in_array($item['key'], ['dashboard', 'companies', 'afrit6', 'users', 'company', 'vat', 'import'], true)) {
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
