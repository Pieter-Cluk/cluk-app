<?php

use App\Models\Role;
use App\Services\Crm\Birthdays;
use App\Services\Vies;
use App\Services\ViesRecheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// First install on a server: create the starting roles and the first admin,
// but only when there are no roles yet (later changes made in the app are kept).
Artisan::command('cluk:first-install', function () {
    if (Role::query()->exists()) {
        $this->info('Roles exist already: nothing to do.');

        return;
    }
    $this->call('db:seed', ['--force' => true]);
    $this->info('Starting roles and first admin created.');
})->purpose('Seed roles and the first admin on a fresh database');

// Send a test email to check the mail connection (Microsoft 365).
Artisan::command('cluk:mail-test {to}', function (string $to) {
    try {
        Mail::raw(
            'This is a test email from the CLUK app ('.config('app.url').").\nIf you can read this, email from the app works.",
            fn ($m) => $m->to($to)->subject('CLUK app · test email')
        );
        $this->info('Sent to '.$to.' from '.config('mail.from.address').' via '.config('mail.default'));
    } catch (Throwable $e) {
        $this->error('Not sent: '.mb_substr($e->getMessage(), 0, 400));

        return 1;
    }
})->purpose('Send a test email');

// Birthday cards for ticked contacts, every morning at 9:00 (Brussels).
Artisan::command('cluk:birthdays', function () {
    $this->info(Birthdays::sendToday().' birthday card(s) sent.');
})->purpose('Send today\'s birthday cards');
Schedule::command('cluk:birthdays')->dailyAt('09:00')->timezone('Europe/Brussels')->withoutOverlapping();

// VIES was busy or down: check those numbers again every hour.
Artisan::command('cluk:vies-recheck', function () {
    $this->info(ViesRecheck::run(app(Vies::class)).' VAT number(s) checked again.');
})->purpose('Check VAT numbers again that VIES could not answer for');
Schedule::command('cluk:vies-recheck')->hourly()->withoutOverlapping();
