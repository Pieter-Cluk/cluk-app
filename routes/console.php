<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Services\Backup\Backups;
use App\Services\Crm\Birthdays;
use App\Services\Media\Messages;
use App\Services\Media\Translator;
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

// Newsletters and promotions: start scheduled ones and send the next batch of mails.
Artisan::command('cluk:messages', function () {
    $this->info(Messages::work(50).' mail(s) sent.');
})->purpose('Send scheduled newsletters and promotions');
Schedule::command('cluk:messages')->everyMinute()->withoutOverlapping();

// Check that translating works (Media › Messages). Prints the Dutch, French and Spanish of a test sentence.
Artisan::command('cluk:translate-test', function () {
    if (! Translator::available()) {
        $this->error('No ANTHROPIC_API_KEY on the server.');

        return 1;
    }
    $out = app(Translator::class)->translate(['body' => 'Happy birthday, {name}! The whole CLUK team wishes you a great day.']);
    if ($out === null) {
        $this->error('Translation failed: see the log (storage/logs) for the reason.');

        return 1;
    }
    foreach ($out as $l => $f) {
        $this->line($l.': '.$f['body']);
    }
})->purpose('Test the translation service');

// Backups: the database and the uploaded files, compressed. Started twice a day by GitHub (workflow "Backup").
Artisan::command('cluk:backup {--no-files : Only the database}', function () {
    $r = Backups::run(! $this->option('no-files'));
    $size = fn ($f) => $f && is_file($f) ? round(filesize($f) / 1048576, 2).' MB' : '—';
    $this->info('Database: '.basename($r['db']).' ('.$size($r['db']).', '.$r['tables'].' tables, '.$r['rows'].' rows)');
    if ($r['files']) {
        $this->info('Files: '.basename($r['files']).' ('.$size($r['files']).')');
    }
    $this->line('Kept: '.implode(', ', array_map('basename', array_merge(Backups::list('db'), Backups::list('files')))));
    AuditLog::record('backup.made', null, ['db' => basename($r['db']), 'rows' => $r['rows']]);
})->purpose('Back up the database and the uploaded files');

Artisan::command('cluk:backups', function () {
    foreach (['db', 'files'] as $k) {
        foreach (Backups::list($k) as $f) {
            $this->line(basename($f).'  '.round(filesize($f) / 1048576, 2).' MB');
        }
    }
})->purpose('List the backups');

Artisan::command('cluk:restore {backup : File name of a cluk-db-… or cluk-files-… backup} {--force : Really do it}', function (string $backup) {
    $path = Backups::dir().'/'.basename($backup);
    if (! is_file($path)) {
        $this->error('No such backup: '.basename($backup).'. See php artisan cluk:backups');

        return 1;
    }
    if (! $this->option('force')) {
        $this->warn('This replaces the current '.(str_contains($path, '-db-') ? 'database' : 'uploaded files').' with '.basename($path).'. Run again with --force to do it.');

        return 1;
    }
    // First a fresh backup of how things are now, so a restore can be undone.
    $now = Backups::run(false);
    $this->info('Safety backup of the current database: '.basename($now['db']));
    if (str_contains($path, '-db-')) {
        $this->info(Backups::restoreDatabase($path).' statements restored from '.basename($path));
    } else {
        $this->info(Backups::restoreFiles($path).' files restored from '.basename($path));
    }
})->purpose('Restore a backup');
