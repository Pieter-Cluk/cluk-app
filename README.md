# CLUK app

One web app for CLUK BV (Zulte, BE) that replaces HireHop, Outlook bookings, HubSpot/Agile and Zoho.

Modules: dry-hire rental, product sales & purchasing, warehouse, Afrit 6 co-working, Cluk Factory venue, pre-invoicing (to OkiOki) and management dashboards.

The clickable design mockups are the reference for every screen. The build roadmap is in [docs/ROADMAP.md](docs/ROADMAP.md).

## Stack

- Laravel 13, PHP 8.3+
- MySQL 8 / MariaDB 10.6+ in production, SQLite in the automated tests
- Staff sign in with Microsoft 365; Afrit 6 members sign in with email and password
- Languages: English, Dutch, French and Spanish (more later)

## Run it locally

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Tests

Every push to GitHub runs the test suite automatically (see the Actions tab). Locally:

```sh
php artisan test
```

## Hosting

The first target is the existing one.com hosting. What the plan must offer is listed in [docs/HOSTING.md](docs/HOSTING.md).
