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

Phone app for the crew (scan item → location), locations and storage positions (G / M, Dymo 11354), return route with cleaning and test bench, unannounced deliveries, Afrit 6 tasks with photos and scans, wall screens.

## Phase 4 · Product sales & purchasing, Cluk Factory

Sales items with suppliers, PDFs and pictures; sales quote with 10% margin approval; purchase price requests and orders; order confirmation with delivery dates. Cluk Factory booking page and staff module.

## Phase 5 · Finance & management

Pre-invoicing with red flags, OkiOki connection, management dashboard, revenue per salesperson and module.

Late payments (agreed 21 Oct 2026): members and customers accept payment terms with interest at the statutory rate for commercial transactions (law of 2 August 2002; 2026: 10.5% per year, set every half year) and a fixed € 40 compensation per late invoice (config `afrit6.payment`). Simple interest on the unpaid invoice amount only, never interest on interest. When OkiOki reports an invoice as unpaid after its due date, the app creates one reminder per invoice for the accountant with the interest so far (amount × rate × days late / 365) and the € 40. The accountant decides: invoice now, invoice when the customer pays (interest up to the payment date), or skip with a reason. Private persons (consumer rules) are skipped unless the accountant decides otherwise. Nothing is invoiced automatically.
