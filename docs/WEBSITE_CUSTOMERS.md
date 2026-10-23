# Website customer logins and baskets (back end)

The back end for customer logins on the website is ready. The pages (sign in,
register, my account, the two baskets) belong to the website (`routes/website.php`,
`App\Http\Controllers\Site\*`, route names `site.*`). This note says what to call.

**One login = two baskets**
- **dryhire**: items and dates of use, sent to the team as a request (lands in Deals › Incoming).
- **sales**: products at list price, ordered straight away on invoice.

## Signing in

- Guard: `customer` (session), model `App\Models\Web\CustomerAccount`, provider `customers`.
- Middleware `customer.auth` sends guests to `site.account.login`. Use it for my account and the basket pages.
- A login that staff block is signed out on its next click (`LogoutBlockedCustomers` runs on every web request).

All calls are on `App\Services\Web\CustomerAuth`:

| Call | What it does |
|---|---|
| `register($d)` | Fields: email, password, first_name, last_name, company_name, vat_number, phone, locale. Sends the "confirm my email" mail. The status is `pending` until staff approve it on the customer card. |
| `attempt($email, $password, $remember)` | Returns `null` when the customer is signed in. Otherwise it returns the reason: `wrong`, `unverified`, `pending` or `blocked`. Show `__('web.api.login_'.$reason)`. |
| `logout()` | Signs out only the customer. A staff session in the same browser stays signed in. |
| `current()` | The signed-in, active account, or null. |
| `sendReset($account)` | Mails a link to `site.account.reset?email=…&token=…`. The link is valid for 60 minutes and works once. Always answer `__('web.api.reset_sent')`, even when the address is unknown. |
| `reset($email, $token, $password)` | Returns true or false (`web.api.reset_invalid` / `web.api.reset_done`). |
| `saveProfile($account, $d)` | Saves first_name, last_name, company_name, vat_number, phone and locale. The email address can't be changed: it is the login. |
| `changePassword($account, $current, $new)` | Returns true or false. |

The confirm link is `/account/verify/{id}/{hash}` (route `customer.verify`, signed, valid 48 hours). It is served by the app and redirects to `site.account.login?verified=1`.

**Route names the back end links to:** `site.account.login` and `site.account.reset`.
Until those routes exist, the links fall back to `/site/account/login` and `/site/account/reset`.

## Baskets

The baskets are kept on the server, so they follow the customer to every device. All calls are on `App\Services\Web\Baskets`; `$kind` is `'dryhire'` or `'sales'`.

| Call | What it does |
|---|---|
| `set($account, $kind, $id, $qty)` | Sets a quantity; 0 takes the line out. Returns `null` when done, otherwise `unknown` (the website doesn't show that item) or `too_many` (more than `website.max_lines` lines). |
| `merge($account, $kind, [['id' => …, 'qty' => …]])` | After signing in, adds the guest basket kept in the browser to the server basket. |
| `dates($account, $from, $until)` | Dates of use for the dry-hire basket. |
| `show($account, $kind)` | For the basket page: the lines (id, number, name, qty, price_cents, amount_cents, plus in_stock for sales). Dry-hire also gets first_day, last_day, days, factor, per_day_cents and estimate_cents. Sales also gets net_cents, vat_pct, vat_cents and gross_cents (with the customer's VAT). |
| `submitDryHire($account, ['message' => …, 'page' => …])` | Uses the basket's dates, or `first_day`/`last_day` if you pass them. Goes through `WebRequests::dryHire`, then links the account, contact and company, and empties the basket. Returns the IncomingMessage; the reference is `'W-'.$id`. Throws a ValidationException (`lines` / `first_day`) when the basket is empty or the dates are wrong. |
| `submitSales($account, ['po_number' => …, 'message' => …])` | Makes a sales quote at list price (title "Web order …") and accepts it at once in the customer's name, the same as accepting a quote online. The customer gets the order confirmation, and missing stock is asked from the suppliers. The colleague who approved the login gets an email. Returns the SalesQuote. |
| `history($account)` | For My account: `requests` and `orders`, newest first. |

**What can go in a basket**
- **Dry-hire**: what `DryHireCatalogue::query()` shows.
- **Sales**: `Baskets::salesQuery()`, meaning active products with a list price above 0 (only the ticked ones when `website.only_ticked` is set). A product without a price can't be ordered; ask for a quote instead.

## Staff side (already built)

- **Companies › Website logins**: approve (link to an existing company, a new one, or none), block, unblock, send a password link.
- **Customer card**: shows the "Website logins" card with requests, orders and lines in the baskets.
- **Dashboard**: a task for logins that are waiting.

**Texts**: all in `lang/*/web.php` (keys `api.*`, `basket.*`, `order.*`).

## Sales catalogue: brands and codes

These are for the sales pages of the website.

**Brands**
- Model: `App\Models\Product\Brand`, linked from `SalesItem::brandRef()` through `brand_id`. `SalesItem::brand` keeps the name as text.
- `kind`: `own` (our own products), `distributed` (we are the distributor) or `other`. Show own and distributed first.
- `slug` is for brand pages. `old_slugs` holds earlier slugs: redirect them to the current one. `Brand::findByName()` finds a brand by name, slug or old slug.
- `introText($locale)` gives the brand's text, with English as the fallback.
- The logo is at `route('brand.logo', $brand)` (public); `logo_path` is empty when there is no logo.
- Only show brands with `active = true`.

**Codes**
- Our S-number (`number`) is the main product number.
- `manufacturer_code` is the item code in the manufacturer's catalogue. Show it on the product page; customers often search on it.
- `ean` is the barcode.
- Supplier article numbers (`sales_item_suppliers.supplier_ref`) are internal only. Don't show them on the website.

## The customer's own sales prices

Staff set a discount per brand on the customer card. They can also set one for "all other brands".

**On the website**
- Signed-in customer: show `CustomerPrices::priceCents($account, $salesItem)` as their price, with the list price struck through when `discountBp(...)` > 0.
- Guests and private customers (no company) see the list price.
- `Baskets::show($account, 'sales')` already uses these prices. Each line has `price_cents` (their price), `list_price_cents` and `discount_pct`.
- `submitSales` orders at the same prices.
- Prices are excl. VAT.
