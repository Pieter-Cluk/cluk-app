<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Factory\Booking;
use App\Models\Finance\PreInvoice;
use App\Models\ProductSales\SalesQuote;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireRequest;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The search in the top bar: jobs (deals, quotes, orders, Factory bookings, sub-hire,
 * pre-invoices), companies and contacts, equipment and pieces. Each group only for the
 * people who may open that module.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = $this->term($request);

        return view('search.index', ['q' => $q, 'groups' => $q === '' ? [] : self::results($request->user(), $q, 25)]);
    }

    /** Suggestions while typing. */
    public function suggest(Request $request): JsonResponse
    {
        $q = $this->term($request);

        return response()->json(['groups' => mb_strlen($q) < 2 ? [] : array_values(self::results($request->user(), $q, 5))]);
    }

    private function term(Request $request): string
    {
        return mb_substr(trim((string) $request->query('q', '')), 0, 80);
    }

    /** @return array<string, array{key: string, label: string, items: list<array{title: string, sub: string, url: string}>}> */
    public static function results(User $user, string $q, int $limit): array
    {
        $like = '%'.addcslashes($q, '%_\\').'%';
        $has = fn (Builder $b, array $cols) => $b->where(function ($w) use ($cols, $like) {
            foreach ($cols as $c) {
                $w->orWhere($c, 'like', $like);
            }
        });
        $groups = [];
        $add = function (string $key, $rows, callable $map) use (&$groups) {
            if ($rows->isNotEmpty()) {
                $groups[$key] = ['key' => $key, 'label' => __('search.groups.'.$key), 'items' => $rows->map($map)->values()->all()];
            }
        };

        if ($user->canUseModule('sales')) {
            $add('deals', $has(Deal::query()->with('company'), ['number', 'title'])->latest('id')->take($limit)->get(),
                fn (Deal $d) => ['title' => $d->number.' · '.$d->title, 'sub' => trim(($d->company?->name ?? '').' · '.__('deals.stages.'.$d->stage), ' ·'), 'url' => route('deals.show', $d)]);
            $add('quotes', $has(Quote::query()->with('company')->where('status', '!=', 'replaced'), ['number', 'title', 'po_number'])->latest('id')->take($limit)->get(),
                fn (Quote $x) => ['title' => $x->code().' · '.$x->title, 'sub' => trim(($x->company?->name ?? '').' · '.__('quotes.statuses.'.$x->displayStatus()), ' ·'), 'url' => route('quotes.show', $x)]);
            $add('salesquotes', $has(SalesQuote::query()->with('company'), ['number', 'title', 'po_number'])->latest('id')->take($limit)->get(),
                fn (SalesQuote $x) => ['title' => $x->code().' · '.$x->title, 'sub' => (string) ($x->company?->name ?? ''), 'url' => route('psales.show', $x)]);
        }
        if ($user->canUseModule('dryhire', 2)) {
            $add('orders', $has(Order::query()->with('company'), ['number', 'title', 'po_number'])->latest('id')->take($limit)->get(),
                fn (Order $o) => ['title' => $o->number.' · '.$o->title, 'sub' => trim(($o->company?->name ?? '').' · '.$o->out_from?->format('d/m/Y'), ' ·'), 'url' => route('orders.show', $o)]);
        }
        if ($user->canUseModule('factory')) {
            $add('factory', $has(Booking::query()->with('company'), ['number', 'title', 'req_company'])->latest('id')->take($limit)->get(),
                fn (Booking $b) => ['title' => $b->number.' · '.$b->title, 'sub' => trim(($b->company?->name ?? $b->req_company ?? '').' · '.$b->start_date?->format('d/m/Y'), ' ·'), 'url' => route('factory.show', $b)]);
        }
        if ($user->canUseModule('purchasing')) {
            $add('subhire', $has(SubhireRequest::query()->with('supplier'), ['number', 'confirm_number', 'their_ref'])->latest('id')->take($limit)->get(),
                fn (SubhireRequest $r) => ['title' => $r->code(), 'sub' => (string) ($r->supplier?->name ?? ''), 'url' => route('subhire.show', $r)]);
        }
        if ($user->canUseModule('preinvoice')) {
            $add('preinvoices', $has(PreInvoice::query()->with('company'), ['number', 'invoice_number', 'source_code', 'description', 'po_number'])->latest('id')->take($limit)->get(),
                fn (PreInvoice $p) => ['title' => $p->number.($p->invoice_number ? ' · '.$p->invoice_number : ''), 'sub' => trim(($p->company?->name ?? '').' · '.($p->source_code ?? ''), ' ·'), 'url' => route('finance.print', $p)]);
        }
        if ($user->canUseModule('companies')) {
            $add('companies', $has(Company::query(), ['name', 'vat_number', 'email', 'city'])->orderBy('name')->take($limit)->get(),
                fn (Company $c) => ['title' => $c->name, 'sub' => trim(($c->vat_number ?? '').' · '.($c->city ?? ''), ' ·'), 'url' => route('companies.show', $c)]);
            // Every word must match the first name, last name, email or phone.
            $contacts = Contact::query()->with('companies');
            foreach (preg_split('/\s+/', $q) as $word) {
                $w = '%'.addcslashes($word, '%_\\').'%';
                $contacts->where(fn ($b) => $b->where('first_name', 'like', $w)->orWhere('last_name', 'like', $w)->orWhere('email', 'like', $w)->orWhere('phone', 'like', $w)->orWhere('mobile', 'like', $w));
            }
            $add('contacts', $contacts->orderBy('first_name')->take($limit)->get()->filter(fn (Contact $c) => $c->companies->isNotEmpty()),
                fn (Contact $c) => ['title' => trim($c->first_name.' '.$c->last_name), 'sub' => trim($c->companies->first()->name.' · '.($c->email ?? ''), ' ·'), 'url' => route('companies.show', $c->companies->first())]);
        }
        if ($user->canUseModule('dryhire')) {
            $add('items', $has(Item::query(), ['number', 'name', 'brand', 'type'])->orderBy('number')->take($limit)->get(),
                fn (Item $i) => ['title' => $i->number.' · '.$i->name, 'sub' => (string) ($i->productCategory?->label() ?? $i->categoryLabel() ?? ''), 'url' => route('equipment.show', $i)]);
            $add('assets', $has(Asset::query()->with('item'), ['code', 'serial_number'])->orderBy('code')->take($limit)->get(),
                fn (Asset $a) => ['title' => $a->code.($a->serial_number ? ' · S/N '.$a->serial_number : ''), 'sub' => (string) ($a->item?->name ?? ''), 'url' => route('equipment.assets.show', $a)]);
        }

        return $groups;
    }
}
