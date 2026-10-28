# Hosting checklist (one.com)

Check these in the one.com control panel before the first deployment.

| Need | Why |
|---|---|
| PHP 8.3 or newer | Laravel 13 requires it · **one.com: PHP 8.4 available ✓** |
| PHP extensions: pdo_mysql, mbstring, openssl, intl, bcmath, fileinfo, zip, gd | database, languages, money calculations, uploads, PDF and label images |
| SSH access | install and update the app, run database migrations |
| Web root can point to the `public` folder (or a subdomain such as app.cluk.live can) | only `public` may be reachable from the internet |
| Scheduled task (cron) every minute: `php artisan schedule:run` | reminders, option expiry, monthly Afrit 6 statements, background jobs |
| MySQL 8 or MariaDB 10.6+ | the database |
| HTTPS on the domain | logins and customer data |
| Outgoing HTTPS allowed | VIES VAT check, Microsoft 365, later OkiOki and Meta |

## Limits of shared hosting

- No always-on background workers. Background jobs run from the scheduler every minute, which is fine for this app's volume.
- Wall screens refresh by polling every 30–60 seconds instead of live push.

If one of the items above is missing, the app moves to a small Laravel-ready server without code changes.

## Address

The app runs on **https://app.cluk.live** (subdomain of cluk.live). Microsoft 365 redirect URI: `https://app.cluk.live/auth/microsoft/callback`.

## Backups

- Twice a day (about 6:00 and 18:00) the GitHub workflow **Backup** runs `php artisan cluk:backup` on the server: the whole database as gzipped SQL (`cluk-db-YYYYMMDD-HHMM.sql.gz`) and all uploaded files (`cluk-files-….tar.gz`).
- They are kept in `shared/storage/backups/` on one.com, outside the website (never downloadable). Kept: the last 2 database backups plus the last one of each of the last 7 days; the last 2 file archives. Each new database backup is read back once to check it is complete.
- Start one by hand: GitHub › Actions › Backup › Run workflow. A failed backup shows in red and GitHub emails about it.
- A copy of every backup goes to Microsoft 365: the OneDrive of the account in the GitHub variable `BACKUP_OFFSITE_USER`, folder “CLUK app backups” (last 14 database backups, last 4 file archives). Needs the application permission Files.ReadWrite.All on the CLUK app. A failed copy is shown in the Backup workflow but does not stop the backup.
- List: `php artisan cluk:backups`. Restore: `php artisan cluk:restore cluk-db-20261022-1800.sql.gz --force` (database) or a `cluk-files-…` name (files). A restore first makes a safety backup of the current database, so it can be undone.
- Still to decide: an extra, encrypted copy outside one.com (e.g. CLUK's Microsoft 365 OneDrive / SharePoint).
