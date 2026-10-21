<?php

namespace App\Services\Factory;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Factory\Booking;
use App\Models\Rental\Order;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Services\Rental\QuoteAnswer;
use App\Support\InLocale;
use App\Support\SafeMail;
use App\Support\VatNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** The Cluk Factory flow: request or option → deal and quote → confirmed when the quote is accepted. */
class Bookings
{
    public static function optionUntil(): Carbon
    {
        return today()->addDays((int) config('factory.option_days'))->setTime(17, 0);
    }

    /** Find the company and contact we already know from the email address or company name. */
    public static function matchKnown(Booking $b): void
    {
        if ($b->req_email && ! $b->contact_id) {
            $contact = Contact::where('email', Str::lower($b->req_email))->first();
            $b->contact_id = $contact?->id;
            $b->company_id ??= $contact?->companies()->first()?->id;
        }
        if (! $b->company_id && $b->req_company) {
            $b->company_id = Company::whereRaw('lower(name) = ?', [Str::lower(trim($b->req_company))])->value('id');
        }
    }

    /** A request from the booking page; "hold" asks for a 3-day option when the dates are free. */
    public static function fromWeb(array $d, bool $hold, string $locale): Booking
    {
        return DB::transaction(function () use ($d, $hold, $locale) {
            $b = new Booking($d + ['number' => Booking::nextNumber(), 'status' => 'request', 'source' => 'web', 'language' => $locale, 'load_in_time' => '08:00', 'doors_time' => '18:00', 'end_time' => '01:00']);
            $b->title = trim(__('factory.types.'.$b->event_type).' · '.($b->req_company ?: $b->req_name));
            self::matchKnown($b);
            if ($b->clashes()) {
                throw ValidationException::withMessages(['start_date' => __('factory.errors.taken')]);
            }
            if ($hold) {
                $b->status = 'option';
                $b->option_until = self::optionUntil();
            }
            $b->save();
            AuditLog::record('factory.requested', $b, ['status' => $b->status, 'date' => $b->start_date->toDateString()]);
            self::mailCustomer($b, $hold ? 'option' : 'request');

            return $b;
        });
    }

    public static function hold(Booking $b, User $by): void
    {
        if ($b->clashes()) {
            throw ValidationException::withMessages(['start_date' => __('factory.errors.taken')]);
        }
        $b->update(['status' => 'option', 'option_until' => self::optionUntil()]);
        AuditLog::record('factory.option', $b, ['until' => $b->option_until->format('d/m/Y H:i')], $by);
        self::mailCustomer($b, 'option', $by);
    }

    public static function decline(Booking $b, string $reason, User $by): void
    {
        $b->update(['status' => 'declined', 'decline_reason' => $reason, 'option_until' => null]);
        AuditLog::record('factory.declined', $b, ['reason' => $reason], $by);
        self::mailCustomer($b, 'declined', $by);
    }

    /** Make the deal (Cluk Factory) and a draft quote with the space, level and days. */
    public static function makeQuote(Booking $b, User $by): Quote
    {
        if ($b->quote_id) {
            return $b->quote;
        }

        return DB::transaction(function () use ($b, $by) {
            self::matchKnown($b);
            $company = $b->company;
            if (! $company && $b->req_company) {
                $country = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $b->req_vat), 0, 2)) ?: 'BE';
                $company = Company::create([
                    'name' => $b->req_company, 'vat_number' => VatNumber::normalize($b->req_vat, ctype_alpha($country) ? $country : 'BE'),
                    'country' => ctype_alpha($country) && strlen($country) === 2 ? $country : 'BE', 'email' => $b->req_email, 'phone' => $b->req_phone,
                    'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => $b->language,
                ]);
            }
            $contact = $b->contact;
            if (! $contact && ($b->req_name || $b->req_email)) {
                $first = Str::before(trim((string) $b->req_name), ' ') ?: Str::before((string) $b->req_email, '@');
                $contact = Contact::create(['first_name' => $first, 'last_name' => trim(Str::after(trim((string) $b->req_name), ' ')) ?: null,
                    'email' => $b->req_email ? Str::lower($b->req_email) : null, 'phone' => $b->req_phone, 'language' => $b->language]);
            }
            if ($company && $contact && ! $company->contacts()->whereKey($contact->id)->exists()) {
                $company->contacts()->attach($contact->id);
            }

            $deal = $b->deal ?? Deal::create([
                'number' => Deal::nextNumber(), 'title' => $b->title, 'module' => 'factory', 'stage' => 'qualified', 'source' => $b->source === 'web' ? 'website' : 'other',
                'company_id' => $company?->id, 'contact_id' => $contact?->id, 'owner_id' => $by->id, 'event_start' => $b->start_date, 'event_end' => $b->lastEventDay(),
                'location' => 'Cluk Factory', 'value_cents' => (int) $b->estimateCents(), 'check_date' => true, 'check_budget' => false, 'check_decider' => false,
            ]);
            $deal->log('system', __('factory.log.deal', ['number' => $b->number]), $by);

            $quote = Quote::create([
                'number' => Quote::nextNumber(), 'version' => 1, 'status' => 'draft', 'deal_id' => $deal->id, 'company_id' => $company?->id,
                'contact_id' => $contact?->id, 'owner_id' => $by->id, 'title' => $b->title, 'language' => $b->language,
                'out_from' => $b->firstDay()->setTime(8, 0), 'out_until' => $b->lastDay()->setTime(23, 59),
                'use_from' => $b->start_date, 'use_until' => $b->lastEventDay(), 'location' => config('factory.address'),
                'intro' => InLocale::run($b->language, fn () => __('factory.quote_intro', ['guests' => $b->guests, 'type' => __('factory.types.'.$b->event_type)])),
            ]);
            $pos = 0;
            if ($item = $b->sizeItem()) {
                $quote->lines()->create(['kind' => 'item', 'item_id' => $item->id, 'number' => $item->number, 'name' => $item->name, 'qty' => max(1, $b->days), 'unit_price_cents' => $item->day_price_cents, 'factor' => 1, 'position' => $pos++]);
            }
            if ($level = $b->levelItem()) {
                $quote->lines()->create(['kind' => 'item', 'item_id' => $level->id, 'number' => $level->number, 'name' => $level->name, 'qty' => 1, 'unit_price_cents' => $level->day_price_cents, 'factor' => 1, 'position' => $pos++]);
            }
            InLocale::run($b->language, function () use ($b, $quote, &$pos) {
                if ($b->buildup_days || $b->breakdown_days) {
                    $quote->lines()->create(['kind' => 'charge', 'name' => __('factory.buildup_line', ['bu' => $b->buildup_days, 'bd' => $b->breakdown_days]), 'qty' => 1, 'unit_price_cents' => 0, 'factor' => 1, 'position' => $pos++]);
                }
                foreach ($b->extras ?? [] as $x) {
                    $quote->lines()->create(['kind' => 'charge', 'name' => __('factory.extras.'.$x), 'qty' => 1, 'unit_price_cents' => 0, 'factor' => 1, 'position' => $pos++]);
                }
            });
            $quote->load('company');
            $quote->recalculate();
            $b->update(['company_id' => $company?->id, 'contact_id' => $contact?->id, 'deal_id' => $deal->id, 'quote_id' => $quote->id, 'owner_id' => $b->owner_id ?? $by->id]);
            AuditLog::record('factory.quote', $b, ['quote' => $quote->number], $by);

            return $quote;
        });
    }

    /** The booking that belongs to a quote (any version). */
    public static function forQuote(Quote $quote): ?Booking
    {
        return Booking::whereIn('quote_id', Quote::where('number', $quote->number)->pluck('id'))->first();
    }

    /** The customer accepted the quote: the date is theirs. */
    public static function confirm(Booking $b, ?User $by = null): void
    {
        $b->update(['status' => 'confirmed', 'confirmed_at' => now(), 'option_until' => null]);
        Tasks::sync($b);
        AuditLog::record('factory.confirmed', $b, [], $by);
        self::mailCustomer($b, 'confirmed', $by ?? $b->owner);
    }

    /**
     * The equipment for the event as a rental quote on the same deal, for the days on site.
     * Sent to the customer when the gear is charged, or booked as included in the level.
     */
    public static function gearQuote(Booking $b, User $by): Quote
    {
        if ($b->gearQuote) {
            return $b->gearQuote;
        }

        return DB::transaction(function () use ($b, $by) {
            self::matchKnown($b);
            $quote = Quote::create([
                'number' => Quote::nextNumber(), 'version' => 1, 'status' => 'draft', 'deal_id' => $b->deal_id, 'company_id' => $b->company_id,
                'contact_id' => $b->contact_id, 'owner_id' => $by->id, 'language' => $b->language,
                'title' => InLocale::run($b->language, fn () => __('factory.gear.title', ['title' => $b->title])),
                'out_from' => $b->firstDay()->setTimeFromTimeString($b->load_in_time ?: '08:00'), 'out_until' => $b->lastDay()->setTime(23, 59),
                'use_from' => $b->start_date, 'use_until' => $b->lastEventDay(), 'location' => config('factory.address'),
            ]);
            $pos = 0;
            foreach (config('factory.packages.'.$b->level, []) as $number => $qty) {
                if ($item = Item::where('number', $number)->first()) {
                    $quote->lines()->create(['kind' => 'item', 'item_id' => $item->id, 'number' => $item->number, 'name' => $item->name, 'qty' => $qty, 'unit_price_cents' => $item->day_price_cents, 'position' => $pos++]);
                }
            }
            $quote->load('company');
            $quote->recalculate();
            $b->update(['gear_quote_id' => $quote->id]);
            AuditLog::record('factory.gear', $b, ['quote' => $quote->number], $by);

            return $quote;
        });
    }

    /** The gear is part of the level (Advanced, All-in): no prices, straight to order allocation. */
    public static function bookGearIncluded(Booking $b, User $by): Order
    {
        $quote = $b->gearQuote;
        if (! $quote || ! in_array($quote->status, ['draft', 'change'], true) || $quote->lines()->where('kind', 'item')->doesntExist()) {
            throw ValidationException::withMessages(['gear' => __('factory.gear.errors.empty')]);
        }

        return DB::transaction(function () use ($b, $by, $quote) {
            $quote->lines()->update(['unit_price_cents' => 0]);
            $quote->load('company');
            $quote->recalculate();
            QuoteAnswer::accept($quote->fresh(), $by->name, null, null, $by);
            AuditLog::record('factory.gear_booked', $b, ['quote' => $quote->number], $by);

            return $quote->fresh()->order;
        });
    }

    public static function mailCustomer(Booking $b, string $what, ?User $by = null): bool
    {
        if (! $b->req_email && ! $b->contact?->email) {
            return false;
        }
        $to = $b->req_email ?: $b->contact->email;
        $locale = array_key_exists($b->language, config('cluk.locales')) ? $b->language : 'en';
        $mail = InLocale::run($locale, function () use ($b, $what, $by) {
            $fmt = fn ($d, $f = 'l j F Y') => $d->locale(app()->getLocale())->translatedFormat($f);
            $est = $b->estimateCents();
            $details = array_filter([
                __('factory.mail.label_event') => __('factory.types.'.$b->event_type).' · '.trans_choice('factory.guests_n', $b->guests, ['n' => $b->guests]),
                __('factory.mail.label_date') => $fmt($b->start_date).($b->days > 1 ? ' – '.$fmt($b->lastEventDay()) : ''),
                __('factory.mail.label_site') => $fmt($b->firstDay(), 'j M').' → '.$fmt($b->lastDay(), 'j M'),
                __('factory.mail.label_space') => $b->size.' · '.config('factory.sizes.'.$b->size.'.0').' m² · '.__('factory.levels.'.$b->level),
                __('factory.mail.label_extras') => collect($b->extras ?? [])->map(fn ($x) => __('factory.extras.'.$x))->implode(', '),
                __('factory.mail.label_estimate') => $est !== null ? __('factory.from', ['amount' => Pricing::eur($est)]) : __('factory.custom'),
                __('factory.mail.label_option') => $what === 'option' ? $fmt($b->option_until, 'l j F, H:i') : null,
                __('factory.mail.label_reason') => $what === 'declined' ? $b->decline_reason : null,
                __('factory.mail.label_ref') => $b->number,
            ]);

            return new Confirmation(
                subject: __('factory.mail.'.$what.'_subject', ['date' => $fmt($b->start_date, 'j M Y')]),
                greeting: ($first = Str::before(trim((string) ($b->req_name ?: $b->contact?->first_name)), ' ')) !== '' ? __('mails.hello', ['name' => $first]) : __('mails.hello_plain'),
                intro: [__('factory.mail.'.$what.'_intro')],
                details: $details,
                next: [__('factory.mail.'.$what.'_next', ['date' => $b->option_until ? $fmt($b->option_until, 'j F, H:i') : ''])],
                signer: $by,
                team: __('factory.mail.team'),
            );
        })->locale($locale);

        return SafeMail::notify(Notification::route('mail', $to), $mail);
    }
}
