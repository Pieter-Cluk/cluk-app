<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// First install on a server: create the starting roles and the first admin,
// but only when there are no roles yet (later changes made in the app are kept).
Artisan::command('cluk:first-install', function () {
    if (\App\Models\Role::query()->exists()) {
        $this->info('Roles exist already: nothing to do.');

        return;
    }
    $this->call('db:seed', ['--force' => true]);
    $this->info('Starting roles and first admin created.');
})->purpose('Seed roles and the first admin on a fresh database');
