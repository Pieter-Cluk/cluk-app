# Hosting checklist (one.com)

Check these in the one.com control panel before the first deployment.

| Need | Why |
|---|---|
| PHP 8.3 or newer | Laravel 13 requires it |
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
