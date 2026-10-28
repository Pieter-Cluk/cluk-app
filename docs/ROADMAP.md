# Build roadmap

The design mockups (Claude design canvas "CLUK App Mockups") are the reference for every screen.
Each step is small, tested automatically, and can be tried on a test address before it goes live.

## Phase 1 · Foundation + Afrit 6

1. Skeleton, automated tests, languages (EN, NL, FR, ES), project docs
2. Users, roles, module levels, per-person overrides, extra rights, "reports to", reassignment rule, audit log
3. Staff login with Microsoft 365 (Entra ID); menu shows only allowed modules
4. App layout: sidebar, header, account menu, day/night mode, dashboard with task tiles
5. Companies & contacts: customer / supplier / both, VIES check, VAT treatment, filters
6. Settings: company details, VAT rates (scheduled changes), document basics, media & connections (placeholders)
7. Spreadsheet import: equipment, sales items, companies, contacts (templates)
8. Afrit 6 member registration and login: VAT check in VIES, email confirmation, manager approval, password rules
9. Afrit 6 member portal: desks (swap / add), room booking per half hour, warehouse requests (receive, 24 h shelf, storage per day, packing, sending)
10. Afrit 6 staff tasks: member requests, ask the member, reassign (same role & title, or reports to you)
11. Monthly Afrit 6 statement → proforma (feeds Pre-invoicing in phase 5)

## Phase 2 · Rental

Items & assets (serial / counted, packages, cases), QR labels (Dymo 1933083), quote editor with factor, deals pipeline with Incoming, customer accept online with PO, order allocation, sub-hire (SHQ / SHC), documents (R- family).

## Phase 3 · Warehouse

Phone app for the crew (scan item → location), locations and storage positions (G / M, Dymo 2112290 location labels), return route with cleaning and test bench, unannounced deliveries, Afrit 6 tasks with photos and scans, wall screens.

## Phase 4 · Product sales & purchasing, Cluk Factory

Sales items with suppliers, PDFs and pictures; sales quote with 10% margin approval; purchase price requests and orders; order confirmation with delivery dates. Cluk Factory booking page and staff module.

## Phase 5 · Finance & management

Pre-invoicing with red flags, OkiOki connection, management dashboard, revenue per salesperson and module.

Late payments (agreed 21 Oct 2026): members and customers accept payment terms with interest at the statutory rate for commercial transactions (law of 2 August 2002; 2026: 10.5% per year, set every half year) and a fixed € 40 compensation per late invoice (config `afrit6.payment`). Simple interest on the unpaid invoice amount only, never interest on interest. When OkiOki reports an invoice as unpaid after its due date, the app creates one reminder per invoice for the accountant with the interest so far (amount × rate × days late / 365) and the € 40. The accountant decides: invoice now, invoice when the customer pays (interest up to the payment date), or skip with a reason. Private persons (consumer rules) are skipped unless the accountant decides otherwise. Nothing is invoiced automatically.

Rental quotes (built 21 Oct 2026): the factor follows the days the customer uses the equipment (config `rental.factor`): 1 day 1, up to a week 1.5, two weeks 2.5, three weeks 3, then + 0.5 per started week; the salesperson can override it per line. Availability is checked on the out-of-warehouse period: sent quotes hold equipment during the option time (company details: option days, 17:00), accepted quotes book it. A sent version is frozen; changes make a new version under the same number. The customer accepts online with a PO number; the deal is then won. CLUK's rental conditions are PDFs per language at /terms/{lang} (config `rental.terms_files`), linked from the rental documents.

Order allocation and sub-hire (built 21 Oct 2026): an accepted quote becomes an order (R-OC) in Order allocation. Per line, pieces we don't have free on the out period are rented in: one price request (SHQ) per partner per order, emailed in the partner's language; the asset manager types the partner's prices and accepts, which emails the confirmation (SHC). Own stock is allocated by serial number (auto picks pieces in the warehouse first, or type/scan a code) or by count. When every line is covered, someone with the right "Orders: approve" approves: the customer gets the order confirmation, the salesperson is told. Orders hold stock firmly; sub-hired pieces never use own stock. Hand-over follows the reassignment rule; dry-hire level 3 can work on every order.

Rental documents (built 21 Oct 2026): once an order is approved, its page lists the pick list (R-PICK, internal: serial numbers with their warehouse location, kits, package contents, incoming sub-hire), the delivery note (R-DN, customer language, signatures, no prices), the return checklist (R-RET, internal) and the proforma (R-PF, payment before collection with IBAN and the reference; OkiOki makes the invoice). The proforma can be emailed to the customer and opened from their quote link. Documents are printable pages (browser → PDF); recording what was picked or returned comes with the warehouse phone app (phase 3).

Cluk Factory (built 21 Oct 2026): public booking page at /book (4 languages, for the new website too): event type, guests, days, build-up and breakdown, a calendar with booked and optioned days, size XS–XXL and level priced from the CF-… service items, extras, details; "Request a quote" or "Hold this date" (3-day option until 17:00, config factory.option_days). The customer gets an email of what they asked. Staff page /factory: tiles, requests, month calendar with event / build-up / option bars, bookings and a before-the-event checklist. "Make deal and quote" creates the company, contact, a Cluk Factory deal and a draft quote (space × event days, Advanced, build-up and extras lines to price). When the customer accepts that quote, the booking is confirmed (no rental order) and they get a booking confirmation. Expired options free the date automatically. Venue tasks per level (config `factory.tasks`) and the rental order for Advanced / All-in equipment are built; the default equipment lists per level (config `factory.packages`) are still empty.

Pre-invoicing (built 21 Oct 2026): every proforma (dry-hire order, return charges, Cluk Factory booking, product sales delivery, Afrit 6 statement) lands in Finance › Pre-invoicing, made automatically at the right moment or with "Send to pre-invoicing" on the source page. Automatic checks: VIES, invoice address, PO number (new tick per company "Needs a PO number"), VAT code against the customer, other overdue invoices, amount against the accepted quote, margin (10% rule, sales approval). Red flags block unless the accountant ticks the override. The accountant approves (the OkiOki invoice number is typed by hand until the OkiOki connection exists), sends back with a reason (the maker gets an email and a dashboard task, fixes the source and sends it again), marks paid by hand, and decides on late-payment interest for overdue invoices. OkiOki: approved invoices are numbered and sent as Peppol e-invoices with the PDF (built 26 Oct 2026). Still to do: reading the payment status back from OkiOki.

Management (built 21 Oct 2026): /management for people with "Finance: see margins". Revenue = invoiced proformas (excl. VAT, on the invoice date) for this month, year to date or the last 12 months against the same period last year; gross margin after purchases and sub-hire; quotes in progress weighted by deal stage; proformas to approve; unpaid and overdue; Afrit 6 recurring desk contracts. Charts per month, module, salesperson (people ticked "commercial" in their profile, plus Counter & Online) and top customers. Figures fill up as proformas go through pre-invoicing; history before the app is not imported.

## Open items (checked 28 Oct 2026)

- Late payments: overdue is worked out in the app; OkiOki's payment status is not read yet, reminders are not created automatically, and "invoice when paid" / "invoice now" do not yet make the interest proforma.
- Afrit 6 monthly statement → proforma is made by hand per member; no automatic monthly run yet.
- Cluk Factory: default equipment per level (`factory.packages`) to fill in.
- Website: a privacy policy page (accounts, downloads with name and email, visit statistics).
- Extra backup copy outside one.com.
- LinkedIn: waiting for LinkedIn's approval of the Community Management API.
