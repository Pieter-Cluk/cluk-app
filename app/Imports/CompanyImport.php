<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\User;
use App\Support\VatNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Companies from a spreadsheet. A row updates the company with the same
 * VAT number (or else the same name); otherwise a new company is made.
 * Empty cells never wipe what is already there.
 */
class CompanyImport extends Importer
{
    /** @var Collection<int, User>|null */
    private $users = null;

    public static function sheetNames(): array
    {
        return ['companies', 'bedrijven', 'entreprises', 'empresas', 'companys'];
    }

    protected function requiredField(): string
    {
        return 'name';
    }

    protected function columns(): array
    {
        return [
            'name' => ['Company name', 'Company', 'Name', 'Bedrijfsnaam', 'Bedrijf', 'Naam', 'Nom', 'Empresa'],
            'vat_number' => ['VAT number', 'VAT', 'VAT ID', 'Btw-nummer', 'Btw nummer', 'BTW', 'Ondernemingsnummer', 'Numéro de TVA', 'TVA', 'CIF', 'NIF'],
            'legal_form' => ['Legal form', 'Rechtsvorm'],
            'street' => ['Street', 'Street Address', 'Address', 'Adres', 'Straat', 'Rue', 'Dirección'],
            'postcode' => ['Postcode', 'Postal Code', 'Zip', 'Zip Code', 'Code postal', 'Código postal'],
            'city' => ['City', 'Gemeente', 'Stad', 'Plaats', 'Ville', 'Localité', 'Ciudad'],
            'country' => ['Country', 'Country/Region', 'Land', 'Pays', 'País'],
            'phone' => ['Phone', 'Phone Number', 'Telephone', 'Telefoon', 'Téléphone', 'Teléfono'],
            'email' => ['Email', 'E-mail', 'Email address', 'E-mailadres'],
            'website' => ['Website', 'Website URL', 'Web', 'Site web', 'Domain', 'Company Domain Name'],
            'relationship' => ['Relationship', 'Type', 'Relatie', 'Relation', 'Relación'],
            'language' => ['Language', 'Taal', 'Langue', 'Idioma'],
            'payment_days' => ['Payment terms (days)', 'Payment terms', 'Payment term', 'Betalingstermijn', 'Délai de paiement'],
            'owner' => ['Account owner', 'Company owner', 'Owner', 'Accountbeheerder', 'Eigenaar', 'Responsable'],
            'notes' => ['Notes', 'Notities', 'Opmerkingen', 'Description'],
        ];
    }

    protected function importRow(array $row, int $line): void
    {
        $name = trim((string) $row['name']);
        if ($name === '') {
            $this->report['skipped']++;
            $this->message($line, 'error', __('import.errors.no_name'));

            return;
        }

        $country = self::country($row['country'] ?? null);
        if (($row['country'] ?? null) !== null && $country === null) {
            $this->message($line, 'warning', __('import.warnings.country', ['value' => $row['country']]));
        }

        $vat = VatNumber::normalize($row['vat_number'] ?? null, $country ?? 'BE');
        if ($vat !== null && ! VatNumber::looksValid($vat)) {
            $this->message($line, 'warning', __('import.warnings.vat', ['number' => $row['vat_number'], 'name' => $name]));
            $vat = null;
        }
        $country ??= $vat !== null ? (substr($vat, 0, 2) === 'EL' ? 'GR' : substr($vat, 0, 2)) : null;

        $company = ($vat !== null ? Company::where('vat_number', $vat)->first() : null)
            ?? Company::whereRaw('lower(name) = ?', [Str::lower($name)])->first();
        $isNew = $company === null;
        $company ??= new Company(['name' => $name, 'country' => 'BE', 'language' => 'nl', 'vat_regime' => 'auto', 'is_customer' => true, 'is_supplier' => false]);

        $values = array_filter([
            'name' => $name,
            'vat_number' => $vat,
            'legal_form' => $row['legal_form'] ?? null,
            'street' => $row['street'] ?? null,
            'postcode' => $row['postcode'] ?? null,
            'city' => $row['city'] ?? null,
            'country' => $country,
            'phone' => $row['phone'] ?? null,
            'email' => isset($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL) ? Str::lower($row['email']) : null,
            'website' => $row['website'] ?? null,
            'language' => self::language($row['language'] ?? null),
            'notes' => $row['notes'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (isset($row['email']) && ! isset($values['email'])) {
            $this->message($line, 'warning', __('import.warnings.email', ['value' => $row['email']]));
        }

        $relationship = self::key((string) ($row['relationship'] ?? ''));
        if ($relationship !== '') {
            $customer = (bool) preg_match('/customer|klant|client|prospect|lead|both|beide|deux|ambos/', $relationship);
            $supplier = (bool) preg_match('/supplier|vendor|leverancier|fournisseur|proveedor|both|beide|deux|ambos/', $relationship);
            if ($customer || $supplier) {
                // Adding a role never removes the other one.
                $values['is_customer'] = $customer || ($company->exists && $company->is_customer);
                $values['is_supplier'] = $supplier || ($company->exists && $company->is_supplier);
            } else {
                $this->message($line, 'warning', __('import.warnings.relationship', ['value' => $row['relationship']]));
            }
        }

        if (isset($row['payment_days']) && is_numeric($row['payment_days'])) {
            $days = (int) $row['payment_days'];
            $isSupplierOnly = ($values['is_supplier'] ?? $company->is_supplier) && ! ($values['is_customer'] ?? $company->is_customer);
            $values[$isSupplierOnly ? 'supplier_payment_days' : 'customer_payment_days'] = max(0, min(365, $days));
        }

        if (! empty($row['owner'])) {
            $this->users ??= User::query()->get();
            $owner = $this->users->first(fn (User $u) => Str::lower($u->name) === Str::lower(trim($row['owner'])) || Str::lower((string) $u->email) === Str::lower(trim($row['owner'])));
            if ($owner) {
                $values['account_owner_id'] = $owner->id;
            } else {
                $this->message($line, 'warning', __('import.warnings.owner', ['value' => $row['owner']]));
            }
        }

        $company->fill($values);
        if (isset($values['vat_number']) && $company->isDirty('vat_number')) {
            $company->vies_status = null;
            $company->vies_checked_at = null;
        }
        if (! $company->isDirty()) {
            $this->report['skipped']++;

            return;
        }
        $company->save();
        $this->report[$isNew ? 'created' : 'updated']++;
    }
}
