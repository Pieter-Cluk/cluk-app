<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Contacts and extra addresses on a company card. */
class CompanyContactController extends Controller
{
    public function store(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request);

        // Someone already known by email (e.g. at another company) is linked, not copied.
        $contact = $data['email'] ? Contact::where('email', $data['email'])->first() : null;
        $linked = $contact !== null;

        DB::transaction(function () use (&$contact, $company, $data) {
            if ($contact === null) {
                $contact = new Contact($this->contactFields($data));
                $contact->newsletter_changed_at = $data['newsletter'] ? now() : null;
                $contact->save();
            }

            if ($data['is_main']) {
                $company->contacts()->newPivotStatement()->where('company_id', $company->id)->update(['is_main' => false]);
            }
            $company->contacts()->syncWithoutDetaching([$contact->id => $this->pivotFields($data)]);
        });

        return redirect()->route('companies.show', $company)
            ->with('status', __($linked ? 'companies.flash.contact_linked' : 'companies.flash.contact_added', ['name' => $contact->name]));
    }

    public function update(Request $request, Company $company, Contact $contact): RedirectResponse
    {
        abort_unless($company->contacts()->whereKey($contact->id)->exists(), 404);
        $data = $this->validated($request, $contact);

        DB::transaction(function () use ($company, $contact, $data) {
            $fields = $this->contactFields($data);
            if ($fields['newsletter'] !== $contact->newsletter) {
                $contact->newsletter_changed_at = now();
            }
            $contact->fill($fields)->save();

            if ($data['is_main']) {
                $company->contacts()->newPivotStatement()->where('company_id', $company->id)->update(['is_main' => false]);
            }
            $company->contacts()->updateExistingPivot($contact->id, $this->pivotFields($data));
        });

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.contact_saved', ['name' => $contact->name]));
    }

    /** Removes the person from this company; someone linked to no company any more is deleted. */
    public function destroy(Company $company, Contact $contact): RedirectResponse
    {
        $company->contacts()->detach($contact->id);
        if (! $contact->companies()->exists()) {
            $contact->delete();
        }

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.contact_removed', ['name' => $contact->name]));
    }

    public function storeAddress(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'street' => ['required', 'string', 'max:160'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
        ]);
        $data['country'] = strtoupper($data['country']);
        $company->addresses()->create($data);

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.address_added'));
    }

    public function destroyAddress(Company $company, CompanyAddress $address): RedirectResponse
    {
        abort_unless($address->company_id === $company->id, 404);
        $address->delete();

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.address_removed'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Contact $contact = null): array
    {
        $request->merge(['email' => $request->filled('email') ? mb_strtolower(trim((string) $request->input('email'))) : null]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190', $contact ? Rule::unique('contacts', 'email')->ignore($contact->id) : 'nullable'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'language' => ['nullable', Rule::in(array_keys(config('cluk.locales')))],
            'job_title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_main'] = $request->boolean('is_main');
        $data['is_billing'] = $request->boolean('is_billing');
        $data['newsletter'] = $request->boolean('newsletter');

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function contactFields(array $data): array
    {
        return array_intersect_key($data, array_flip(['first_name', 'last_name', 'email', 'phone', 'mobile', 'language', 'newsletter', 'notes']))
            + ['last_name' => null, 'email' => null, 'phone' => null, 'mobile' => null, 'language' => null, 'notes' => null];
    }

    /** @param array<string, mixed> $data */
    private function pivotFields(array $data): array
    {
        return ['job_title' => $data['job_title'] ?? null, 'is_main' => $data['is_main'], 'is_billing' => $data['is_billing']];
    }
}
