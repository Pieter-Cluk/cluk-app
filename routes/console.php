<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyRegistry;
use App\Models\Equipment\PhotoSuggestion;
use App\Models\Role;
use App\Services\Backup\Backups;
use App\Services\Credit\CreditCheck;
use App\Services\Credit\KboLookup;
use App\Services\Credit\RegistryImport;
use App\Services\Crm\Birthdays;
use App\Services\Equipment\PhotoSuggestions;
use App\Services\Media\Messages;
use App\Services\Media\Translator;
use App\Services\Sales\CommercialTasks;
use App\Services\Social\Social;
use App\Services\Vies;
use App\Services\ViesRecheck;
use App\Services\Website\WebActivity;
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
    // The copy outside one.com (Microsoft 365). A failure there does not fail the backup itself, but is shown.
    if (\App\Services\Backup\Offsite::on()) {
        foreach (array_filter([$r['db'], $r['files']]) as $f) {
            try {
                \App\Services\Backup\Offsite::upload($f);
                $this->info('Copied to Microsoft 365: '.basename($f));
            } catch (\Throwable $e) {
                $this->warn('Copy to Microsoft 365 failed for '.basename($f).': '.$e->getMessage());
            }
        }
        try {
            if ($gone = \App\Services\Backup\Offsite::prune()) {
                $this->line('Removed older copies in Microsoft 365: '.implode(', ', $gone));
            }
        } catch (\Throwable $e) {
            $this->warn('Cleaning the Microsoft 365 copies failed: '.$e->getMessage());
        }
    } else {
        $this->line('No copy outside one.com: BACKUP_OFFSITE_USER is not set.');
    }
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

// Product photos found on the web: read the list and fetch the photos (a batch at a time).
Artisan::command('cluk:photo-suggestions {--limit=40}', function () {
    $added = PhotoSuggestions::load();
    $left = PhotoSuggestions::fetch((int) $this->option('limit'));
    $c = PhotoSuggestion::query()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
    $this->info("added/updated {$added}; still to fetch {$left}; ".json_encode($c));
    $this->line('LEFT='.$left);
})->purpose('Fetch the product photos found on the web, for review');

// KBO/BCE register data: the workflow "KBO update" asks for our enterprise numbers,
// filters the official extract on them and hands the result back to import.
Artisan::command('cluk:kbo-numbers', function () {
    $this->line(json_encode(RegistryImport::numbers()));
})->purpose('Print the enterprise numbers of our Belgian companies (JSON)');

Artisan::command('cluk:kbo-import {file : JSON made by the KBO update workflow}', function (string $file) {
    $data = json_decode((string) file_get_contents($file), true);
    if (! is_array($data) || ! isset($data['enterprises'])) {
        $this->error('Not a KBO extract.');

        return 1;
    }
    $r = RegistryImport::run($data);
    AuditLog::record('crm.registry_import', null, $r + ['snapshot' => $data['snapshot'] ?? null]);
    $this->line(json_encode($r));
})->purpose('Store the filtered KBO/BCE extract per company');

Artisan::command('cluk:kbo-refresh {--n=50 : How many companies at most}', function () {
    if (! KboLookup::available()) {
        $this->error('KBO_WS_USER / KBO_WS_PASSWORD are not set.');

        return 1;
    }
    $done = RegistryImport::refreshStale((int) $this->option('n'));
    $levels = Company::active()->whereHas('registry')->with('registry')->get()->countBy(fn ($c) => CreditCheck::for($c)['level']);
    $this->line(json_encode(['looked_up' => $done, 'with_register_data' => CompanyRegistry::count(), 'still_to_do' => count(RegistryImport::numbers()) - CompanyRegistry::count(), 'levels' => $levels]));
    // Which ones are not green, and why (for a quick check after a run).
    foreach (Company::active()->whereHas('registry')->with('registry')->get() as $c) {
        $chk = CreditCheck::for($c);
        if ($chk['level'] !== 'green') {
            $this->line($chk['level'].' · '.$c->name.' · '.collect($chk['reasons'])->pluck('text')->implode(' | '));
        }
    }
})->purpose('Look up the companies with the oldest register data in the KBO web service');

Artisan::command('cluk:web-prune', function () {
    $this->line(json_encode(WebActivity::prune() + ['chats' => \App\Services\Website\Clukie::prune()]));
})->purpose('Delete website statistics older than 13 months and Clukie chats older than 12 months (also runs daily by itself)');

// Social media: publish planned posts and refresh the figures of the accounts.
Artisan::command('cluk:social', function () {
    $this->line(json_encode(['published' => Social::publishDue(), 'refreshed' => Social::syncDue(3)]));
})->purpose('Publish planned social posts and refresh social figures');
Schedule::command('cluk:social')->everyMinute()->withoutOverlapping(20);

// Commercial tasks for the salespeople (also every 15 minutes in the background).
Artisan::command('cluk:sales-tasks', function () {
    $this->line('open: '.CommercialTasks::refresh());
})->purpose('Make and close the commercial tasks');
Schedule::command('cluk:sales-tasks')->everyFifteenMinutes()->withoutOverlapping();

// Click through every page and link (read only: each page runs in a transaction that is rolled back).
Artisan::command('cluk:link-check {--as= : email of the staff user to click as} {--max=600} {--site : also the public website, as a visitor}', function () {
    $user = \App\Models\User::where('email', $this->option('as'))->first();
    if (! $user) {
        $this->error('No user with that email.');

        return 1;
    }
    $check = new \App\Support\LinkCheck((int) $this->option('max'));
    $check->run($user, [url('/dashboard')]);
    $lines = $check->lines();
    if ($this->option('site')) {
        $site = new \App\Support\LinkCheck((int) $this->option('max'));
        $site->run(null, [route('site.home')]);
        $lines = array_merge($lines, array_map(fn ($l) => 'site: '.$l, $site->lines()));
        $this->info('Website pages opened: '.$site->opened);
    }
    $this->info('Platform pages opened: '.$check->opened.' · problems: '.count($lines));
    foreach ($lines as $l) {
        $this->line($l);
    }
})->purpose('Click through all pages and links, read only');
