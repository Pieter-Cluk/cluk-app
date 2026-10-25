<?php

namespace App\Services\Web;

use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuote;
use App\Models\Sales\IncomingMessage;
use App\Models\Web\Basket;
use App\Models\Web\BasketLine;
use App\Models\Web\CustomerAccount;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Services\ProductSales\CustomerPrices;
use App\Services\ProductSales\SalesFlow;
use App\Services\Website\DryHireCatalogue;
use App\Services\Website\WebRequests;
use App\Support\InLocale;
use App\Support\StaffMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The two baskets of a website login, kept on the server so they follow the
 * customer to every device:
 *  - dryhire: items and dates of use, sent to the team as a request (Deals › Incoming);
 *  - sales: products at the customer's price (list minus their brand discount), ordered directly on invoice (a sales quote, accepted).
 * Only what the website shows can go in a basket.
 */
class Baskets
{
    public const KINDS = [Basket::DRYHIRE, Basket::SALES];

    /** Sales products the website may sell: active, with a price (and ticked, when the website shows only ticked items). */
    public static function salesQuery(): Builder
    {
        return SalesItem::query()->where('active', true)->where('list_price_cents', '>', 0)
            ->when(config('website.only_ticked'), fn ($q) => $q->where('on_website', true));
    }

    public static function of(CustomerAccount $a, string $kind): Basket
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        return Basket::firstOrCreate(['account_id' => $a->id, 'kind' => $kind]);
    }

    private static function query(string $kind): Builder
    {
        return $kind === Basket::DRYHIRE ? DryHireCatalogue::query() : self::salesQuery();
    }

    private static function column(string $kind): string
    {
        return $kind === Basket::DRYHIRE ? 'item_id' : 'sales_item_id';
    }

    /**
     * Put a quantity in the basket (0 takes the line out).
     * Returns null when done, otherwise why not: "unknown" or "too_many".
     */
    public static function set(CustomerAccount $a, string $kind, int $id, int $qty): ?string
    {
        $basket = self::of($a, $kind);
        $col = self::column($kind);
        if ($qty <= 0) {
            $basket->lines()->where($col, $id)->delete();
            $basket->touch();

            return null;
        }
        if (! self::query($kind)->whereKey($id)->exists()) {
            return 'unknown';
        }
        if (! $basket->lines()->where($col, $id)->exists() && $basket->lines()->count() >= (int) config('website.max_lines', 200)) {
            return 'too_many';
        }
        $basket->lines()->updateOrCreate([$col => $id], ['qty' => min($qty, (int) config('website.max_qty', 9999))]);
        $basket->touch();

        return null;
    }

    /** Add lines from the browser (a basket put together before signing in): [['id' => …, 'qty' => …], …]. */
    public static function merge(CustomerAccount $a, string $kind, array $lines): void
    {
        $basket = self::of($a, $kind);
        $col = self::column($kind);
        foreach (array_slice($lines, 0, (int) config('website.max_lines', 200)) as $l) {
            $id = (int) ($l['id'] ?? 0);
            $qty = (int) ($l['qty'] ?? 0);
            if ($id > 0 && $qty > 0) {
                $have = (int) $basket->lines()->where($col, $id)->value('qty');
                self::set($a, $kind, $id, $have + $qty);
            }
        }
    }

    /** Dates of use for the dry-hire basket. */
    public static function dates(CustomerAccount $a, ?string $from, ?string $until): void
    {
        self::of($a, Basket::DRYHIRE)->update(['first_day' => $from ?: null, 'last_day' => $until ?: null]);
    }

    public static function clear(CustomerAccount $a, string $kind): void
    {
        $basket = self::of($a, $kind);
        $basket->lines()->delete();
        $basket->update(['first_day' => null, 'last_day' => null]);
    }

    /**
     * What is in a basket, for the website's basket page, in the customer's language.
     * Lines of items the website no longer shows are left out.
     */
    public static function show(CustomerAccount $a, string $kind): array
    {
        $basket = self::of($a, $kind);
        $col = self::column($kind);
        $lines = $basket->lines()->get();
        $items = self::query($kind)->whereKey($lines->pluck($col))->get()->keyBy('id');
        $rows = $lines->filter(fn (BasketLine $l) => $items->has($l->{$col}))->map(function (BasketLine $l) use ($items, $col, $kind, $a) {
            $i = $items[$l->{$col}];
            // Sales: the customer's own price (their standard sales conditions), the same as Prices::for() on the catalogue.
            $p = $kind === Basket::SALES ? Prices::for($a, $i) : null;
            $price = $kind === Basket::DRYHIRE ? (int) $i->day_price_cents : $p['price_cents'];

            return ['id' => $i->id, 'number' => $i->number, 'name' => $kind === Basket::DRYHIRE ? (string) $i->name : $i->name(),
                'qty' => $l->qty, 'price_cents' => $price, 'amount_cents' => $price * $l->qty]
                + ($kind === Basket::SALES ? ['list_price_cents' => $p['list_cents'], 'discount_pct' => $p['discount_pct'], 'in_stock' => (int) $i->stock_qty] : []);
        })->values();
        $sum = (int) $rows->sum('amount_cents');

        if ($kind === Basket::DRYHIRE) {
            $days = $basket->first_day && $basket->last_day ? (int) $basket->first_day->diffInDays($basket->last_day) + 1 : null;
            $factor = $days ? DryHireCatalogue::factor($days) : null;

            return ['kind' => $kind, 'lines' => $rows->all(), 'first_day' => $basket->first_day?->toDateString(), 'last_day' => $basket->last_day?->toDateString(),
                'days' => $days, 'factor' => $factor, 'per_day_cents' => $sum, 'estimate_cents' => $factor ? (int) round($sum * $factor) : null];
        }

        $rate = self::orderVat($a);

        return ['kind' => $kind, 'lines' => $rows->all(), 'net_cents' => $sum, 'vat_pct' => ($rate?->rate_bp ?? 0) / 100,
            'vat_cents' => (int) round($sum * ($rate?->rate_bp ?? 0) / 10000), 'gross_cents' => $sum + (int) round($sum * ($rate?->rate_bp ?? 0) / 10000)];
    }

    private static function orderVat(CustomerAccount $a)
    {
        return (new SalesQuote)->setRelation('company', $a->company)->vatRateNow();
    }

    private static function active(CustomerAccount $a): void
    {
        if (! $a->canOrder()) {
            throw ValidationException::withMessages(['lines' => __('web.roles.no_order')]);
        }
        abort_unless($a->isActive(), 403);
    }

    /**
     * Send the dry-hire basket to the team as a request, with the account's
     * details and its contact and company linked. $d: first_day, last_day
     * (else the basket's dates), message, page.
     */
    public static function submitDryHire(CustomerAccount $a, array $d = []): IncomingMessage
    {
        self::active($a);
        $basket = self::of($a, Basket::DRYHIRE);
        $show = self::show($a, Basket::DRYHIRE);
        $from = $d['first_day'] ?? $show['first_day'];
        $until = $d['last_day'] ?? $show['last_day'];
        if (! $show['lines']) {
            throw ValidationException::withMessages(['lines' => __('web.basket.empty')]);
        }
        if (! $from || ! $until || Carbon::parse($until)->lt(Carbon::parse($from)) || Carbon::parse($from)->lt(today())) {
            throw ValidationException::withMessages(['first_day' => __('web.basket.dates')]);
        }
        $a->loadMissing(['company', 'contact']);

        $msg = DB::transaction(function () use ($a, $basket, $show, $from, $until, $d) {
            $msg = WebRequests::dryHire([
                'first_day' => $from, 'last_day' => $until,
                'lines' => collect($show['lines'])->map(fn ($l) => ['id' => $l['id'], 'qty' => $l['qty']])->all(),
                'name' => $a->name(), 'email' => $a->email, 'phone' => $a->phone,
                'company' => $a->company?->name ?? $a->company_name, 'vat' => $a->company?->vat_number ?? $a->vat_number,
                'message' => $d['message'] ?? null, 'page' => $d['page'] ?? null,
            ], $a->locale ?: 'en');
            $msg->forceFill(['customer_account_id' => $a->id, 'contact_id' => $a->contact_id ?? $msg->contact_id, 'company_id' => $a->company_id ?? $msg->company_id,
                'payload' => array_merge($msg->payload ?? [], ['account_id' => $a->id])])->save();
            $basket->lines()->delete();
            $basket->update(['first_day' => null, 'last_day' => null]);

            return $msg;
        });
        AuditLog::record('web.dryhire_sent', $a, ['email' => $a->email, 'ref' => 'W-'.$msg->id, 'lines' => count($show['lines'])]);
        // A customer of a known company: straight into Quotes & sales as a deal with a draft quote.
        if ($a->company_id) {
            WebDeals::fromDryHire($a, $msg, collect($show['lines'])->map(fn ($l) => ['id' => $l['id'], 'qty' => $l['qty']])->all(), $from, $until, $d['message'] ?? null);
        }

        return $msg->fresh();
    }

    /**
     * Order the sales basket on invoice: a sales quote at the customer's prices, accepted
     * by the customer straight away (the same as accepting a quote online:
     * stock is reserved, missing quantities are asked from the suppliers and
     * the customer gets the order confirmation). $d: po_number, message.
     */
    public static function submitSales(CustomerAccount $a, array $d = []): SalesQuote
    {
        self::active($a);
        $basket = self::of($a, Basket::SALES);
        $lines = $basket->lines()->get();
        $items = self::salesQuery()->with('suppliers')->whereKey($lines->pluck('sales_item_id'))->get()->keyBy('id');
        $lines = $lines->filter(fn ($l) => $items->has($l->sales_item_id));
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('web.basket.empty')]);
        }
        $a->loadMissing(['company', 'contact', 'approvedBy']);
        $loc = $a->locale ?: 'en';
        $owner = WebDeals::owner($a);

        $q = DB::transaction(function () use ($a, $basket, $lines, $items, $loc, $owner, $d) {
            $q = SalesQuote::create([
                'number' => SalesQuote::nextNumber(), 'status' => 'sent', 'company_id' => $a->company_id, 'contact_id' => $a->contact_id, 'owner_id' => $owner?->id,
                'title' => InLocale::run($loc, fn () => __('web.order.title', ['date' => today()->format('d/m/Y')])), 'language' => $loc,
                'notes' => ! empty($d['message']) ? Str::limit(trim($d['message']), 2000, '') : null,
                'valid_until' => today()->addDays(CompanyDetail::current()->quote_valid_days ?: 30),
                'token' => Str::random(48), 'sent_at' => now(), 'sent_to' => $a->email,
            ]);
            $q->forceFill(['customer_account_id' => $a->id])->save();
            // The order is a won deal in Quotes & sales, for the colleague who looks after this customer.
            if ($a->company_id) {
                $deal = WebDeals::deal($a, 'sales', $q->title);
                $q->forceFill(['deal_id' => $deal->id, 'owner_id' => $q->owner_id ?? $deal->owner_id])->save();
            }
            $pos = 0;
            foreach ($lines as $l) {
                $i = $items[$l->sales_item_id];
                $q->lines()->create(['sales_item_id' => $i->id, 'kind' => 'item', 'name' => $i->name(), 'description' => $i->description, 'qty' => $l->qty,
                    'cost_cents' => $i->costCents(), 'position' => ++$pos] + CustomerPrices::lineValues($a, $i));
            }
            $q->recalculate();
            AuditLog::record('web.order_placed', $a, ['email' => $a->email, 'number' => $q->number, 'total' => Pricing::eur($q->net_cents)]);
            SalesFlow::accept($q, $a->name(), $d['po_number'] ?? null);
            $basket->lines()->delete();

            return $q->fresh(['lines', 'contact', 'company']);
        });

        // The colleague who looks after this customer hears about it (My preferences › "a customer accepts your quote").
        if ($owner) {
            StaffMail::send($owner, 'quote_answered', InLocale::run($owner->locale ?: 'en', fn () => new Confirmation(
                subject: __('web.order.staff_subject', ['number' => $q->number, 'name' => $q->company?->name ?? $a->name()]),
                greeting: __('mails.hello', ['name' => $owner->first_name]),
                intro: [__('web.order.staff_intro', ['name' => $a->name(), 'email' => $a->email])],
                details: array_filter([__('mails.label.quote') => $q->number, __('quotes.po') => $q->po_number, __('web.order.note') => $q->notes]),
                button: [__('web.order.staff_button'), route('psales.show', $q)],
                rows: $q->lines->map(fn ($l) => [(string) $l->qty, $l->name, Pricing::eur($l->amount_cents)])->all(),
                totals: [__('psales.total_excl') => Pricing::eur($q->net_cents)],
            ))->locale($owner->locale ?: 'en'));
        }

        return $q;
    }

    /** The customer's history for "My account": dry-hire requests and sales orders, newest first. */
    public static function history(CustomerAccount $a, int $limit = 20): array
    {
        return [
            'requests' => $a->requests()->latest('received_at')->limit($limit)->get()->map(fn (IncomingMessage $m) => [
                'ref' => 'W-'.$m->id, 'date' => $m->received_at?->toDateString(), 'first_day' => $m->payload['first_day'] ?? null, 'last_day' => $m->payload['last_day'] ?? null,
                'lines' => count($m->payload['lines'] ?? []), 'estimate_cents' => $m->payload['estimate_cents'] ?? null, 'status' => $m->status,
            ])->all(),
            'orders' => $a->salesOrders()->latest()->limit($limit)->get()->map(fn (SalesQuote $q) => [
                'number' => $q->number, 'date' => $q->created_at?->toDateString(), 'status' => $q->status, 'po_number' => $q->po_number,
                'net_cents' => (int) $q->net_cents, 'gross_cents' => (int) $q->gross_cents,
            ])->all(),
        ];
    }

    /** Totals for the customer card. */
    public static function counts(CustomerAccount $a): array
    {
        return ['requests' => $a->requests()->count(), 'orders' => $a->salesOrders()->count(),
            'in_baskets' => BasketLine::whereIn('basket_id', $a->baskets()->pluck('id'))->count()];
    }

    /** Is an item (dry-hire or sales) something the website may put in a basket? */
    public static function allowed(string $kind, int $id): bool
    {
        return self::query($kind)->whereKey($id)->exists();
    }
}
