# Farmmantra — Requirements & Functionality Matrix

Master checklist for the October 2026 update. Updated 27 Oct 2026.

**Sources**
- **DOC §x** — *Android Mobile Application Development Requirements* (June 2026; the TXT and PDF are identical). Baseline.
- **N1–N6** — client's handwritten notes (six photographed pages, in the order supplied):
  N1 extra features (bulk SMS, printing, digital signature no.) · N2 delivery acceptance, T&C, price tiers, register retailers ·
  N3 payment types, customer/district/region rules · N4 franchise dashboard, statements, "need of the app", admin security ·
  N5 deactivate-not-delete, roles (incl. new Warehouse), daily payments, stock reports · N6 payment example, statement layout, 15-day dispatch target.

**Status key** — ✅ Working (verified by an executed test) · 🟡 Partial · ❌ Missing · ⛔ Blocked (needs credentials / device / service) ·
*Before* = state found in the audit; *Now* = state after this work.

**Test key** — `F:` Laravel feature test (`agro_web/tests/Feature/…`) · `E2E` live-server API run (28/28) · `SMOKE` every web page × every role on demo data ·
`D:` Dart unit test written but **not executed** (no Flutter SDK available) · `S` static review only.

---

## A. Client requirements from the handwritten notes

| ID | Requirement | Src | Laravel | Flutter | Before | Now | Test |
|---|---|---|---|---|---|---|---|
| N-01 | Bulk SMS to franchises / customers (by region, franchise, type) or a custom list | N1 | `SmsService`, `AdminExtrasController@sms/sendSms`, `admin/sms` page, `sms_messages` log | — (admin web) | ❌ | ✅ logic · ⛔ delivery | `F: ClientRequirementsTest::test_bulk_sms…` (log driver). Real sending needs Africa's Talking credentials (`SMS_DRIVER=africastalking`). |
| N-02 | Anyone can print the necessary documents at will (own franchise only for partners) | N1 | `AccountsController@print/printSigned`, `PrintAccess`, `print/*` views (order/invoice, delivery note, payment & sales receipts, credit note, statement, stock report) | `PrintService` (signed link → browser) on order detail, staff detail, statement, menus | ❌ | ✅ web · 🟡 mobile | `F: test_documents_are_printable…`, `E2E` signed link. Mobile: `S` (needs device). |
| N-03 | Auto-generated digital signature number identifying who confirmed orders and deliveries | N1 | `SignatureService`, `digital_signatures` table; numbers on order approval, dispatch, delivery receipt, payment acceptance, credit notes/returns | shown on order screens | ❌ | ✅ | `F: test_warehouse_accepts…`, `test_franchise_must_accept_delivery…`; `E2E` |
| N-04 | Franchise must accept a delivered order from Farmmantra | N2 | `OrderService::receive` (only after dispatch; per-line received qty ≤ dispatched; charged for received qty) | "Accept delivery" card + dialog | ❌ web · ❌ mobile (no button existed) | ✅ web · 🟡 mobile | `F:` + `E2E`; mobile `D:` payload test |
| N-05 | Accept the reception and read the T&C | N2 | `accept_terms` required on receipt; T&C text shown | checkbox + "Read terms" | ❌ | ✅ / 🟡 mobile | `F:` + `E2E` |
| N-06 | First login: read and approve T&C (re-accept on new version) | N2 | `users.terms_*`, `AuthenticateWeb` gate, `EnsureTermsAccepted` (API 403 `terms_required`), `/terms`, admin editor with versioning | `TermsScreen`, login/splash routing | ❌ | ✅ / 🟡 mobile | `F: test_franchise_must_accept_terms…`, `E2E` |
| N-07 | Unit prices per SKU: Franchise (base, admin) / Retail / Farmer | N2 | `products.farmer_price`; `standard_price`=franchise, `selling_price`=retail; admin product form relabelled | `Product.retailPrice/farmerPrice` | ❌ (sales used the franchise buy price) | ✅ | `F: test_sales_use_admin_fixed…` (10,000 / 10,500 / 11,400 from the sheet) |
| N-08 | Franchise sells to Retail or Farmer at the listed price only (no typed prices) | N2, N4 | `SaleService` picks tier from customer type; discounts off unless admin enables | price-tier chips; payment method | ❌ | ✅ / 🟡 mobile | `F:` + `E2E` |
| N-09 | Customer types retailer / farmer; register retailers | N2, N3 | `customers.customer_type`; admin + franchise forms | customer type field | ❌ | ✅ | `F:` |
| N-10 | Admin (warehouse) has one customer account per franchise | N2 | Interpreted as the franchise's own **account** at Farmmantra: the franchise account statement (ledger) | statement screen | — | ✅ (interpretation) | see *Ambiguities* |
| N-11 | Franchise payment terms Credit or Cash; cash via mobile money or bank transfer | N3 | `orders.payment_terms`; credit limit applies to credit orders; cash orders need accepted payment before dispatch; payments limited to mobile money / bank transfer, reference required | order chips; payment methods | ❌ | ✅ | `F: test_dispatch_requires…`, `test_cash_payment_methods…`, `E2E` |
| N-12 | Admin creates customers and assigns them to a franchise | N3 | `admin/customers` (auto-assign by district's region, or explicit) | — | ❌ | ✅ | `F: test_franchise_only_sees…` |
| N-13 | All districts of Uganda, assigned to regions | N3 | `districts` (136, official region), `regions`, `admin/regions` to assign districts + franchise | district picker (franchise region only) | ❌ | ✅ | `F:` (asserts >130 districts) |
| N-14 | A franchise sees only customers (retailers & farmers) of the districts in its region (Gulu example) | N3 | `Customer::visibleToFranchise`; sales refused for out-of-region customers | — | ❌ | ✅ | `F:` Gulu/Amuru/Omoro vs Wakiso |
| N-15 | Franchise dashboard: stock value, sales of the month, profit of the month, pending orders, number of sales, stock | N4 | `profit_this_month`, `sales_count_this_month`, `awaiting_acceptance`; cost captured per sale line | "This month" card | 🟡 (no profit / count) | ✅ / 🟡 mobile | `F:` + `E2E` |
| N-16 | Franchise accounts visible: credit & debit statements | N4, N6 | `LedgerService`, `account_transactions`, `/franchise/statement`, `/accounts/statements`, print | Statement screen | ❌ | ✅ / 🟡 mobile | `F: test_account_statement…`, `E2E` |
| N-17 | Statement layout: name, person, contact, location, period; opening balance, purchase, payments, credit note, sales return, balance | N6 | statement view + print match the sketch (Purchase = Credit; Payments/Credit note/Sales return = Debit) | same data | ❌ | ✅ | `F:` running balances 1.0M → 0.5M → 0.3M → 0.2M |
| N-18 | Credit notes and sales returns | N6 | `CreditNoteService`, `accounts/credit-notes` (finance + admin); returns move stock back to warehouse | — | ❌ | ✅ | `F:` |
| N-19 | Security process for admin deletions | N4 | `ConfirmSensitiveAction`: password + reason for every admin delete / business reset; audit-logged | — | ❌ (JS confirm only) | ✅ | `F: test_admin_deactivates…` |
| N-20 | Admin does not delete — only deactivate/activate and edit users | N5 | users, franchises, products, categories → deactivate; delete buttons removed for users | — | ❌ (hard delete; product delete cascaded to order history) | ✅ | `F:` |
| N-21 | Sales staff only monitor the system and make support tickets | N5 | staff routes read-only; order actions require Warehouse/admin; web support tickets for every role, admin replies | read-only staff detail; ticket link | ❌ | ✅ / 🟡 mobile | `F:` 403 checks; `E2E` |
| N-22 | New **Warehouse** user: accepts orders and dispatches, recording the means (bus, trucks…) and remarks | N5 | `Warehouse` role; dispatch board; `dispatch_means/vehicle/remarks` | role mapped; dispatch dialog | ❌ | ✅ / 🟡 mobile | `F:` + `E2E` |
| N-23 | Roles: Admin, Sales staff, Finance, Warehouse, Franchise | N5 | roles seeded + migration | enum updated | 🟡 (no Warehouse) | ✅ | `F:` |
| N-24 | Franchise pays daily, as its own customers pay | N5 | any amount any time; not tied to an order | payment form | 🟡 | ✅ | `F:` + `E2E` |
| N-25 | Payments applied across orders (order 1: 1,000,000 paid 900,000; order 2: 500,000 paid 200,000 → balance 400,000) | N6 | `PaymentService::allocateOutstanding` (oldest first) → `orders.amount_paid` | paid / balance per order | ❌ | ✅ | `F: test_daily_payments…client_example` reproduces the sheet exactly |
| N-26 | Stock reports — franchise and company — for Admin, Sales staff, Franchise | N5 | `/reports/stock`, `/api/stock-report`, print | print from menus | 🟡 (pages, no reports) | ✅ | `F: test_stock_reports…`, `E2E` |
| N-27 | 15-day order dispatch target | N6 | `Order::dispatchOverdue`, configurable `dispatch_target_days`; KPI + filter + row highlight | KPI in staff dashboard API | ❌ | ✅ | `F: test_orders_not_dispatched…` |
| N-28 | Need of the app: transparency, fewer loopholes, convenient/reliable | N4 | signatures + audit trail; fixed prices; T&C; dispatch/receipt rules; fixes listed in §C | — | — | ✅ (design goal) | — |

## B. Baseline requirements (June 2026 document)

| ID | Requirement | Src | Before | Now | Notes / test |
|---|---|---|---|---|---|
| B-01 | Roles with distinct logins; RBAC | §5, §12.1 | ✅ | ✅ | extended with Warehouse · `F:` |
| B-02 | Categories, SKUs, standard price, price slabs, admin-only edits | §6, §10.1 | ✅ | ✅ | `SMOKE` |
| B-03 | Franchise: catalogue, order with slab pricing, real-time notification, status tracking | §7.2 | ✅ | ✅ | `F:` existing + new |
| B-04 | Staff approval / decline with remarks / adjust / delivery date | §7.2, §8.2 | 🟡 web approve skipped stock + receipt; adjust dropped lines | ✅ | now Warehouse role; `F: test_web_order_approval…`, `test_adjusting_one_line…` |
| B-05 | Stock receipt: view, verify, approve, auto-update stock, note discrepancies | §7.3 | 🟡 API only, could confirm before dispatch; no mobile button | ✅ web / 🟡 mobile | `F:` |
| B-06 | Sales: customers, products, auto price, remarks, stock decrease, history | §7.4 | 🟡 mobile sales always rejected (no payment_method); priced at franchise cost | ✅ web/API · 🟡 mobile | `F:` + `E2E` |
| B-07 | Inventory levels, value, movements, low-stock alerts | §7.5, §8.3 | ✅ | ✅ | `SMOKE` |
| B-08 | Payment submission, proof upload, status, balance, history | §7.6 | ✅ API · ❌ web accept *added* payments to the debt | ✅ | `F: …web_payment_acceptance_reduces_balance` |
| B-09 | Finance verify/accept/reject with remarks, reports | §9 | ✅ API · 🟡 web skipped verify | ✅ | verify+accept recorded and signed |
| B-10 | Chat, chat history, support tickets | §7.7 | 🟡 tickets had no admin handling on web | ✅ | `web.support.tickets` · `F:` |
| B-11 | Franchise dashboard & analytics | §7.8 | ✅ | ✅ | + profit/count |
| B-12 | Admin user management, password reset, activity logs, activate/deactivate | §10.2 | ✅ | ✅ | delete → deactivate |
| B-13 | Real-time monitoring KPIs | §10.3 | ✅ | ✅ | + overdue dispatch |
| B-14 | Reports + CSV export | §10.4 | 🟡 | 🟡 | Excel/PDF export: printable pages can be saved as PDF; native .xlsx and scheduled e-mail reports **not** implemented |
| B-15 | Push notifications (FCM) | §8.4, §11.3 | ⛔ | ⛔ | code present; needs Firebase project files + server key |
| B-16 | Real-time master-data sync | §11.4 | 🟡 | 🟡 | API refresh on screen load (unchanged) |
| B-17 | Security: bcrypt, sessions, throttled login, audit trail | §12 | 🟡 | 🟡 | + web login throttle, signatures, confirmation logs. 2FA, encryption at rest, TLS, backups are deployment tasks |

## C. Defects found and fixed

| # | Defect (root cause) | Fix | Verified by |
|---|---|---|---|
| 1 | Web "accept payment" **added** the payment to the franchise's outstanding balance (`+` instead of `-`, duplicated logic in `WebController`) | web and API both call `PaymentService` | `F: test_web_order_approval…_reduces_balance` |
| 2 | Web order approval did not reserve warehouse stock or create the stock receipt → web-approved orders could never be received | `OrderService::approve` shared | same test |
| 3 | Order adjust recomputed the total from submitted lines only, dropping un-adjusted lines | recompute over all lines; approval uses adjusted qty | `F: test_adjusting_one_line…` |
| 4 | Franchise could confirm a stock receipt before dispatch, and receive more than dispatched | dispatch + quantity checks | `F: test_franchise_must_accept_delivery…` |
| 5 | `confirm-delivery` API marked an order received without touching stock | now runs the full receipt flow | `E2E` |
| 6 | Mobile: no way to confirm receipt; stock never reached franchise inventory from the app | Accept-delivery card/dialog | `D:` + `S` |
| 7 | Mobile: every sale rejected (missing `payment_method`, `remarks` vs `notes`) | fields sent | `S` (server side `E2E`) |
| 8 | Mobile: unknown roles (Warehouse) silently treated as Franchise Partner | role mapped; staff area | `D:` |
| 9 | Sales priced at the franchise's own buy price (zero margin) | tier prices | `F:` |
| 10 | Web sale form crashed on insufficient stock; used invalid `payment_status` `pending` | `SaleService` | `SMOKE` + `F:` |
| 11 | Web decline stored the decliner as `approved_by` | shared decline | `F:` |
| 12 | Product / category delete cascaded into order history | deactivate instead | `F:` |
| 13 | Seeded demo balances had no ledger history (statements wrong on fresh installs) | `ledger:sync-opening-balances`, run by seeder | `E2E` |
| 14 | Test suite failed on a fresh checkout (Vite manifest) | `withoutVite()` in `TestCase` | full suite |

## D. Ambiguities and decisions

- **"Staff should just monitor"** vs DOC §8.2 (staff approve orders): the later handwritten note wins. Approve/decline/adjust/dispatch moved to the new **Warehouse** role (admin keeps them too). Sales staff keep read access and support tickets.
- **"One customer created for each franchise" (N2)**: implemented as each franchise's account (ledger/statement) with Farmmantra. If the client meant a separate customer record per franchise in a warehouse customer list, that is a small follow-up.
- **"Franchise should only choose amounts listed"** (N4): read as "only listed prices"; discounts are therefore disabled by default and can be enabled by the admin (Terms & Operations).
- **Region model**: business regions are admin-defined (Central/Eastern/Northern/Western seeded; e.g. "Gulu Region" can be created and districts moved into it). A franchise with no region can register customers in any district.
- **Statement columns** follow the client sketch (purchases in *Credit*, payments/credit notes/returns in *Debit*), which is the reverse of the usual receivables convention; the page explains it.
- **Received less than dispatched**: the franchise is charged for what it accepts; the shortfall stays visible on the delivery note and in the audit log for follow-up.

## E. Not done / blocked

- ⛔ **Flutter build and tests not executed** — no Flutter SDK could be installed in the available environments (pub.dev / Google storage blocked). Run `flutter pub get && flutter analyze && flutter test && flutter build apk` (see README).
- ⛔ Real SMS delivery (Africa's Talking credentials), FCM push (Firebase files), Android release signing, production TLS/backups.
- ❌ Native Excel export and scheduled e-mailed reports (§10.4) — printable pages can be saved as PDF.
- 🟡 Admin web layout is styled by Tailwind via Vite: run `npm install && npm run build` after pulling, or new pages show without utility styling (they use their own inline styles, so they remain usable).
