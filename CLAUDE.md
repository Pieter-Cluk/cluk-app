# CLUK app · working notes

## What this is
Laravel 13 app for CLUK BV (Zulte, BE). Owner: Pieter Van de Velde. The design canvas "CLUK App Mockups" is the reference for every screen and flow. Roadmap: docs/ROADMAP.md.

## Conventions
- PHP 8.3, Laravel 13 style: anonymous migrations, `casts()` methods, model attributes (`#[Fillable]`, `#[Hidden]`).
- All user-facing text goes through translations (`__()`), with files for en, nl, fr and es in `lang/`. Never hard-code Dutch or English text in views.
- Money is stored in cents (integer). VAT rates come from the `vat_rates` table, never hard-coded.
- Every important change (rights, door codes, approvals, prices sent to customers) is written to the audit log.
- Staff passwords are never stored or shown: staff sign in with Microsoft 365. Member passwords are hashed and never viewable by staff.
- Tests: feature tests for every rule (rights, reassignment, margin approval…). `php artisan test` must pass before a push; GitHub Actions runs it on every push.
- Names in the UI: Afrit 6 (co-working), Cluk Factory (venue), CLUK (company).

## Environment note
Packagist is not reachable from the Claude workspace, so dependencies cannot be installed there. Code is checked with `php -l` locally and the full test suite runs in GitHub Actions.
