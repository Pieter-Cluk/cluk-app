<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\Contact;
use App\Support\VatNumber;
use Illuminate\Support\Str;

/**
 * Contacts from a spreadsheet. The same email address updates the same
 * person; the company is found by VAT number or name and linked.
 */
class ContactImport extends Importer
{
    public static function sheetNames(): array
    {
        return ['contacts', 'contactpersonen', 'contacten', 'people', 'personen', 'contactos'];
    }

    protected function requiredField(): string
    {
        return 'first_name';
    }

    protected function columns(): array
    {
        return [
            'first_name' => ['First name', 'Firstname', 'Voornaam', 'Prénom', 'Nombre'],
            'last_name' => ['Last name', 'Lastname', 'Surname', 'Achternaam', 'Familienaam', 'Naam', 'Nom', 'Apellidos'],
            'company_vat' => ['Company VAT number', 'VAT number', 'Btw-nummer', 'Btw-nummer bedrijf'],
            'company_name' => ['Company name', 'Company', 'Associated Company', 'Bedrijf', 'Bedrijfsnaam', 'Entreprise', 'Empresa'],
            'job_title' => ['Job title', 'Title', 'Function', 'Functie', 'Fonction', 'Puesto'],
            'email' => ['Email', 'E-mail', 'Email address', 'E-mailadres'],
            'phone' => ['Phone', 'Phone Number', 'Telefoon', 'Téléphone', 'Teléfono'],
            'mobile' => ['Mobile', 'Mobile Phone Number', 'Mobile phone', 'GSM', 'Mobiel', 'Portable', 'Móvil'],
            'language' => ['Language', 'Taal', 'Langue', 'Idioma'],
            'newsletter' => ['Newsletter consent', 'Newsletter', 'Nieuwsbrief', 'Marketing contact status', 'Opt-in'],
        ];
    }

    protected function importRow(array $row, int $line): void
    {
        $first = trim((string) $row['first_name']);
        if ($first === '') {
            $this->report['skipped']++;
            $this->message($line, 'error', __('import.errors.no_first_name'));

            return;
        }

        $email = isset($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL) ? Str::lower($row['email']) : null;
        if (isset($row['email']) && $email === null) {
            $this->message($line, 'warning', __('import.warnings.email', ['value' => $row['email']]));
        }

        $company = null;
        $vat = VatNumber::normalize($row['company_vat'] ?? null);
        if ($vat !== null) {
            $company = Company::where('vat_number', $vat)->first();
        }
        if ($company === null && ! empty($row['company_name'])) {
            $company = Company::whereRaw('lower(name) = ?', [Str::lower(trim($row['company_name']))])->first();
        }
        if ($company === null && ($vat !== null || ! empty($row['company_name']))) {
            $this->message($line, 'warning', __('import.warnings.company', ['name' => trim($first.' '.($row['last_name'] ?? '')), 'company' => $row['company_name'] ?? $row['company_vat']]));
        }

        $contact = $email !== null ? Contact::where('email', $email)->first() : null;
        if ($contact === null && $company !== null) {
            $contact = $company->contacts()
                ->whereRaw('lower(first_name) = ?', [Str::lower($first)])
                ->whereRaw('lower(coalesce(last_name, \'\')) = ?', [Str::lower(trim((string) ($row['last_name'] ?? '')))])
                ->first();
        }
        $isNew = $contact === null;
        $contact ??= new Contact;

        $values = array_filter([
            'first_name' => $first,
            'last_name' => $row['last_name'] ?? null,
            'email' => $email,
            'phone' => $row['phone'] ?? null,
            'mobile' => $row['mobile'] ?? null,
            'language' => self::language($row['language'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        $contact->fill($values);
        if (isset($row['newsletter']) && $row['newsletter'] !== '') {
            $wanted = self::yes($row['newsletter']) || Str::contains(Str::lower($row['newsletter']), ['marketing contact', 'subscribed']) && ! Str::contains(Str::lower($row['newsletter']), 'non');
            if ($contact->newsletter !== $wanted) {
                $contact->newsletter = $wanted;
                $contact->newsletter_changed_at = now();
            }
        }

        $changed = $contact->isDirty();
        $contact->save();

        if ($company !== null) {
            $pivot = $company->contacts()->whereKey($contact->id)->first()?->pivot;
            $title = $row['job_title'] ?? null;
            if ($pivot === null) {
                $company->contacts()->attach($contact->id, ['job_title' => $title]);
                $changed = true;
            } elseif ($title !== null && $pivot->job_title !== $title) {
                $company->contacts()->updateExistingPivot($contact->id, ['job_title' => $title]);
                $changed = true;
            }
        }

        $this->report[$isNew ? 'created' : ($changed ? 'updated' : 'skipped')]++;
    }
}
