# izSuite project status

What is done, what comes next, and how to carry on from another PC.

- **Last updated:** 2026-11-27
- **Code:** Doc 19 Phase F was split on 2026-11-23 into F1 to F6, F5 on 2026-11-24 into F5a and F5b, and F6 into F6a and F6b (see [Doc 19](#7-doc-19-ecommerce-next-phase-g)); with F6b, Phase F is done. Phase F6b (a payment link's customer reporting how they paid, and their reference; the business recording it from the report, into that way's account, or marking it not received; migration 000235) is built and checked on the second PC - the full suite, in two halves, and in a headless browser its 7 steps, all passing on the final code - and committed and pushed: admin the commit after `0ba3372`, frontend the commit after `a2c30e1`. Phase F6a (payment links sent for an order not fully paid - what each asks, its methods and days - e-mailed to the customer, revoked, and their page on the store; migration 000234) was begun on 2026-11-24 in admin `7d6d2f8` ("workign on doc 19 f5c": its table, model, services and resources, without routes, e-mail, tests or frontend) and finished on 2026-11-27: built and checked on the second PC - the full suite, in two halves, and in a headless browser its 9 steps, all passing on the final code - and committed and pushed: admin `0ba3372`, frontend `a2c30e1`. Since F5b, shuvo's `38f2469` and frontend `61188d1` (an item's attachments), `a9c753c` (a local Gotenberg script for running without Docker) and `03080c0` (admin profile pictures), and frontend `bbfdd94` (purchases fixes), were pulled; they have no migration. Phase F5b (an order's products added, their quantities changed or taken off, and its discount, before payment - its Limited stock, shipping, totals and a confirmed order's invoice following; migration 000233) is built and checked on the second PC - the full suite, run in two halves for want of memory, and in a headless browser its 7 steps, the discount's looked at again after its save outlasted the wait - and committed and pushed: admin `430cb30`, frontend `316c273`. Phase F5a (editing an order's customer, addresses and delivery before payment, the shipping re-priced and a confirmed order's invoice with it; no migration) is built and checked on the second PC - the full suite, and in a headless browser each of its 7 steps passing on the final code, over two runs: the last, stopped for want of memory, after 4, all passing - and committed and pushed: admin `dfbffc3`, frontend `18b9bfe`. Phase F4 (holding, releasing and cancelling an order, each with why; what may want a look; Review Orders Above and holding such orders as placed; migration 000232) is committed and pushed: admin `ecfd145`, frontend `52a0d1c`. Phase F3 (an order's payment recorded or marked failed, the order following its invoice, and the customer e-mailed either way; migration 000231) is committed and pushed: admin `e289e75`, frontend `f7cb13b`. Phase F2 (confirming an order: its invoice issued, Limited quantities counted down and the customer e-mailed; migration 000230) is committed and pushed: admin `630466f`, frontend `82421c5`. Phase F1 (the Orders page and an order's page, migration 000229) is committed and pushed: admin `46725ba`, frontend `4679f08` (both "workign on doc 19 f2", F1's work). Doc 19 Phase E3 (what a signed-in customer is offered) is committed and pushed: begun in admin `569d679`, the rest admin `06f4a77`, frontend `597c17e`. Doc 19 Phases E1 (the customer's account) and E2 (the account's contact, orders and addresses, and checkout signed in) are committed and pushed: admin `569d679`, frontend `a4df0be`. Doc 19 Phase D - D1 (shipping zones and methods, and pickup), D2 (payment methods without a gateway), D3 (the cart), D4 (checkout and the order) and D5 (the order's page and its confirmation e-mail) - is built, checked on the second PC through the API and in a headless browser, and committed and pushed: frontend `b4befbb` on `nuxt`, and on `main` the admin commit right after `47257f3`. Doc 19's gap check and Phases A to C (eCommerce on with its storefronts, the catalog, and the storefront's pages) are committed and pushed: admin `47257f3`, frontend `e7782c7`. Doc 18 is done and pushed: admin `85d748a`, frontend `7933c6d`. Doc 17 is done and pushed: Phase I4, the last, is frontend `e20fa70` and admin `e5cd080`. Before Doc 18 Phase I2, shuvo's `a5e46cb` and `9bf4525` (rich text for payment instructions and the Sales settings' notes and terms) were pulled on 2026-11-19; `nuxt` has no frontend change from shuvo to go with them yet.
- **Tests:** 1287 backend tests, last full run 2026-11-27 on the second PC (after Doc 19 Phase F6b, on its final backend code, in two halves - see [Things that catch you out](#things-that-catch-you-out)): 1286 pass, shuvo's `ItemAttachmentsApiTest` among them. `SalesSettingsApiTest` fails since shuvo's `a5e46cb`, not because of Docs 17 to 19 (see [Small things left](#small-things-left)).
- **Next step:** Doc 19 (eCommerce) Phase G: fulfilment - fulfilments, partial ones too, with carrier and tracking; ready for pickup, shipped and delivered; the delivery document; digital delivery and services (see [Doc 19](#7-doc-19-ecommerce-next-phase-g)) - to be split, with its defaults written down, on starting it. Phase D's, E's and F1's to F6b's testing checklists (blocks D1 to D5, E1 to E3 and F1 to F6b) are still to be worked through by a person, beyond the flows a headless browser checked, and so is Doc 18's. Trying card payments with a Stripe test account (Doc 13 Phase D2) is still worth doing: Phase C1's Retry Card and Phase C2a's card refunds have only met a faked Stripe.
- **Since A1, every admin signs in with an authenticator app as well.** `admin@izsuite.io` is asked to set it up at its next sign-in.

Update this file at the end of every phase and commit it with that phase.

## Starting on another PC

1. On a new PC, do the [setup](#setting-up-a-new-pc) below. On a PC set up already, bring it up to date first:
   - pull both repos (`main` and `nuxt`);
   - back up its database if its data matters (the command is under [the setup](#setting-up-a-new-pc), "To bring the same test data");
   - run the new migrations, then restart the queue workers, so they run the new code;
   - restart the frontend, so new pages and components show.

   ```bash
   cd /d/projects/izsuite/izsuite-admin        # izsuite-backend on the second PC
   git pull
   docker compose exec -T app php artisan migrate:status | grep -i pending   # what is still to run
   docker compose exec app php artisan migrate
   docker compose exec app php artisan queue:restart
   cd ../izsuite-antigravity                   # izsuite-frontend on the second PC
   git pull
   docker compose restart
   ```

   Each phase's migration is in the table under [What is done](#what-is-done), and its deploy note under [Deploying to a server](#deploying-to-a-server). Up to Doc 19 Phase F6b the latest is 000235. A new permission is not given to existing custom roles: give it in Settings → Users & Roles.
2. Open `izsuite-admin` in Claude Code and say:

   > Read PROJECT_STATUS.md, then start Doc 19 Phase G.

A new Claude session does not read this file by itself, so ask it to. It reads `CLAUDE.md` (the code rules) on its own.

## Where things stand

The requirements are numbered documents. Doc 00 sets the order: Docs 01 to 15 first, then the full apps by priority.

| Doc | Title | Status |
|---|---|---|
| 00 | Index and delivery order | Reference only |
| 01 | Product Foundation, Packages and Navigation | Done |
| 02 | Billing Starter Dashboard | Done |
| 03 | Contacts | Done |
| 04 | Items | Done |
| 05 | Payment Accounts | Done, two small fields left (see [Small things left](#small-things-left)) |
| 06 | Documents | Done |
| 07 | Basic Reports | Done |
| 08 | Sales Lite | Done |
| 09 | Purchases Lite | Done |
| 10 | Expenses Lite | Done |
| 11 | Business Setup and Settings | Done |
| 12 | Users, Roles, Permissions and Locations | Done, except package change rules (Doc 13) |
| 13 | Apps Center, Packages, Subscriptions and Billing | Done, except usage charges (section 17), which wait for their services |
| 14 | Shared Platform Services | Done, except what was left on purpose (see [Doc 14](#2-doc-14-shared-platform-services-done-2026-11-12)) |
| **15** | **izSuite System Administration Portal** | **Done (2026-11-14): gap check and Phases A to G; a few parts left by choice (see below)** |
| **16** | **Sales** | **Done (2026-11-15): gap check and Phases A to H; some parts wait for other apps (see below)** |
| **17** | **CRM** | **Done (2026-11-19): gap check and Phases A to I; Campaign-to-Opportunity Results waits for Doc 21** |
| **18** | **Point of Sale** | **Done (2026-11-20): gap check and Phases A to I; offline mode (18.29) later, and what waits for Inventory and the integrations (see below)** |
| **19** | **eCommerce** | **In progress: gap check, Phases A (A1 to A3), B (B1 to B3) and C (C1 to C6), done 2026-11-20; Phase D (D1 to D5) and Phase E (E1 to E3) done 2026-11-21; Phase F split into F1 to F6, F1 done 2026-11-23, F2 to F5b 2026-11-24; F6 split into F6a and F6b, both done 2026-11-27, and with them Phase F; G next** |
| 20 and 21 | Customer Subscriptions, Marketing and Loyalty | Written, not started |
| 22 to 52 | Planned in Doc 00 | Not written yet |

Stock, full Sales, OCR and full Accounting features are left out of the Lite apps on purpose. They wait for their own docs.

## What is next

### 1. Doc 13: Apps Center, Packages, Subscriptions and Billing (done 2026-11-09)

The gap check was done on 2026-11-08. Doc 13 was split into Phases A to D: B in two parts, C in three (C1, C2a, C2b) and D in three (D1 to D3). All are built (see [Decisions worth knowing](#decisions-worth-knowing)). Card payments have only been checked against a faked Stripe so far: try them with a Stripe test account (Phase D2 below).

**Already built:**

- The catalog: the seven categories of section 4, app families in Lite and full editions, dependencies, which apps each package includes (`package_apps`), and per-business activation with its history (`workspace_apps`, `workspace_app_logs`).
- Only activated apps reach the sidebar. The Lite apps start on with Billing Starter. A full edition replaces its Lite one and keeps its records (section 9). Turning an app off deletes nothing, and the Activity Log records both directions.
- The Apps Center page groups apps by category, with each edition's state and its turn-on or turn-off button. Dependencies are checked both ways.
- Packages Billing Starter (100 MB, 1 business, 1 user, 1 location), Standard and Premium, with limits set in the admin. Adding past a limit is refused (`PlanLimits`, `StorageUsage`), and a business over a limit keeps everything.
- Settings > Storage & Usage shows usage against the limits. The admin can move a business to another package.
- The Dashboard, Reports and Documents follow the apps that are on (section 20).

**Phase A: Apps Center and app details** is built: search, filters and tabs, each app's own page, Manage Apps for roles, the turn-off summary with dependents, and the public Explore Apps and Pricing pages from the live catalog.

**Phase B1: prices, the subscription page and branding** is built:

- Plans have prices (monthly and yearly, in one currency), trial days, grace days and whether they remove "Powered by izSuite", set in the admin's plan form and shown in its list. The Pricing page shows them.
- Settings > Apps & Subscription is the Subscription and Billing page: the plan and its status, its branding and apps, the plans compared side by side, usage and limits, and billing details (name, e-mail, address, country, tax ID), whose changes go in the Activity Log. A role without Manage Subscription sees only the plan, its apps and the usage.
- "Powered by izSuite" follows the plan, and an issued document keeps what it was issued with (section 18).

**Phase B2: moving to a lower plan, and limit warnings** is built:

- Until Phase C only the admin moves a business between plans (Admin > Clients). The Move button opens a review first: the apps that go to Lite or off, and the users, locations and storage against the new plan's limits.
- Apps outside the new plan turn off at once, their records kept, and their Lite editions come back (section 13). This was already so; the review shows it before the move.
- Over a user or location limit, nothing is switched off by the move. The Business Owner chooses who and what stays active in Settings > Apps & Subscription; the other users are suspended, their invitations withdrawn, and the other locations made inactive (section 14). Until then, adding more is refused.
- Usage and Limits flags each limit as nearly reached (80%), reached or over, and the Dashboard warns the roles with Manage Subscription.

**Phase C: subscriptions, paid by bank transfer.** Your decisions (2026-11-09):

- Each plan's prices, billing currency, trial days and grace days are set in the admin panel. The currency is picked from the currencies the admin keeps active.
- When a paid period ends unpaid, the grace period starts, as long as the plan's grace days. Each day of it an e-mail says the subscription has ended, how many days are left, and that the business then goes back to the free plan. Example: 5 grace days and a subscription ending on 30 November. The e-mails say 4 days left on 30 November, then 3, 2, 1, and the last day on 4 December. On 5 December the plan is cancelled and the business is on Billing Starter, through Phase B2's move down.
- Upgrades are prorated: the unused part of the current period counts against the new plan.

**Phase C1: subscribing and paying by bank transfer** is built:

- The Subscription page lets the owner or a role with Manage Subscription pick a paid plan, monthly or yearly. A plan with a trial starts at once (one trial per business), and its invoice is due on the trial's last day. Without a trial, izSuite issues the invoice, and the plan starts when izSuite confirms the payment. An invoice waiting for the first payment can be withdrawn.
- Invoices and receipts have izSuite's own numbers (`IZS-INV-00001`, `IZS-RCT-00001`). Their PDFs carry izSuite's logo and its details (Admin > Settings > Billing) and the Bank Transfer method's instructions. The business adds its transfer reference to the invoice.
- The admin confirms payments in Admin > Subscription Invoices, which issues the receipt and starts the paid period on the plan. An invoice can be cancelled there too.
- The Subscription page shows the status (Trial, Awaiting payment, Active and paid until a date), the invoice to pay with how to pay it, and the billing history with each invoice's and receipt's PDF. E-mails with the PDF go to the billing e-mail and the owner when an invoice is issued and when a payment is confirmed.
- Each plan's currency is picked from the admin's active currencies.

**Phase C2a: renewals, grace, trial endings and cancelling** is built, with your defaults:

- An hourly job (`subscriptions:advance`) moves each subscription on by its business's own day. Seven days before a paid period ends, the renewal invoice is issued, due on the period's last day. Paid by then, the next period starts as the old one ends.
- Unpaid, the plan's grace starts the next day, with an e-mail every day saying how many days are left: 4 on the first of 5 grace days, down to the last day. Then the plan is cancelled and the business is on Billing Starter. Paying during grace keeps the plan, from where the period ended. A plan without grace days ends with its period.
- A trial reminds 3 days and 1 day before it ends, and unpaid goes back to Billing Starter the day after.
- Cancelling an active subscription keeps it to the end of the paid period, and Keep My Plan undoes it until then. A trial, or a subscription in grace, ends at once.
- The Subscription page and the Dashboard show the grace with its days left, and an ending plan.

**Phase C2b: plan changes** is built, with your defaults:

- An upgrade (a higher plan, or monthly to yearly) shows its price first: the new plan's price less a credit for the unused days of the paid period. Its invoice is issued, and the new plan starts when izSuite confirms the payment, for a full period from that day. It can be withdrawn; left unpaid, it lapses as the period ends.
- A downgrade (a lower paid plan, or yearly to monthly) waits for the end of the paid period, and Keep undoes it until then. The renewal invoice is for the lower plan, and at the period's end the business moves to it (Phase B2's move down).
- Billing Starter is reached by cancelling. A cancellation and a downgrade replace each other.

**Still missing**, by phase:

**Phase D: card payments through Stripe.** Your decisions (2026-11-09):

- Stripe takes the card payments. izSuite stays the seller, so its own invoices, renewals, upgrades and grace keep running as built. Stripe needs izSuite's company to be registered in a country Stripe supports.
- The other five follow the suggested defaults:
  - The card is saved at the first payment and charged automatically, with retries during the grace.
  - Trials still start without a card.
  - Subscription tax comes from izSuite's own table.
  - Bank transfer stays beside the card.
  - Refunds, discounts and promotions wait for Doc 15.
- Whatever the phase, card details are entered only on Stripe's own page, never in izSuite. The code reaches Stripe through a provider layer, so more providers can be added by country later (Doc 15 section 15).

Phase D is split into D1 to D3, with these suggested defaults.

**Phase D1: subscription tax** (Doc 13 section 16, Doc 15 section 16) is built, with your defaults:

- Admin > Subscription Taxes, under App System, holds one rule per country and start date:
  - the tax's name (VAT, GST, sales tax);
  - its rate and the date it applies from;
  - whether a buyer's tax ID removes it (reverse charge), with the note the invoice then prints.
- The list marks each rule In force, Upcoming or Replaced. A later rule for a country takes over from its own day.
- Prices are before tax. The tax is worked out when an invoice is issued, from the billing details' country and tax ID, on the amount after any upgrade credit, rounded to the cent. The invoice keeps it as issued. A country without a rule pays no tax.
- The subscribe dialog, the upgrade preview and the invoice to pay show the price, any credit, the tax and the total. The invoice PDF and the admin list show the tax too, and a reverse charge prints its note.
- Compare Plans and the public Pricing page say prices are before tax.

**Phase D2: paying by card** is built, with your defaults:

- Admin > Payment Methods > Stripe:
  - takes test and live keys (publishable, secret and webhook signing secret) and a Test or Live switch;
  - stores the keys encrypted and shows them only masked ("sk_test_…4242"); a key left blank keeps the saved one;
  - won't switch on without the chosen mode's secret key and signing secret;
  - gives the webhook address to enter in Stripe.
  The old PayPal, Paddle, Paystack and Razorpay entries are hidden.
- The invoice to pay offers Pay by Card beside the bank transfer details. It opens Stripe's own payment page in the invoice's currency, which saves the card for later payments.
- A finished payment is settled once, however it arrives: by Stripe's webhook (taken only with Stripe's signature) or by the business coming back from Stripe's page. The invoice is then paid as when the admin confirms a transfer: the receipt and its e-mail go out, and the plan starts or renews. The receipt names the card and Stripe's reference.
- A card payment that can't pay its invoice (cancelled meanwhile, paid already, or a different amount) is kept with the reason. The admin invoice list shows it, to refund in Stripe.
- The Subscription page's Payment Method section shows the saved card (brand, last four digits, expiry), with Replace Card and Remove. Each change goes in the Activity Log.
- Checked without Stripe: the tests fake Stripe's API. On dev the admin page, the masked keys, the webhook refusing unsigned calls, and the new parts of the Subscription page were checked; nothing was sent to Stripe.

**To try D2 with Stripe** (test mode, on dev):

1. In Stripe's test mode, copy the publishable and secret keys.
2. In Admin > Payment Methods > Stripe, paste the test keys, choose Test, and tick Take card payments.
3. Run `stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe` and paste the signing secret it prints. Without it, coming back from Stripe's page still settles a payment.
4. Pay an invoice with Stripe's test card 4242 4242 4242 4242, any future expiry date and any CVC.
5. For D3, save a card, then run `php artisan subscriptions:advance` on the renewal's due day. Stripe's test card 4000 0000 0000 0341 is declined later, after it is saved, and 4000 0027 6000 3184 asks to be confirmed.

On a server, the Stripe webhook needs these events: `checkout.session.completed` and `payment_intent.succeeded`, and since Doc 15 Phase C2a `refund.created` and `refund.updated` (`charge.refund.updated` on an older API version).

**Phase D3: automatic renewals and retries** is built, with your defaults:

- With a saved card, the hourly job charges it, without the business there, on the renewal's due day (the paid period's last) and on a trial's last day. Paid, the invoice is settled as any card payment is. Each invoice is tried at most once a business day, and Stripe gets the same key for a repeat, so it never charges twice.
- A refusal shows Past Due on the Subscription page and the Dashboard, and an e-mail gives the bank's reason. In the grace that follows the card is tried again on the days set in Admin > Settings > Billing ("Card retries in grace", 1 and 3) and on the last grace day. That day's grace e-mail says how the try went. Paid in grace, the plan carries on from where its period ended.
- When the bank asks the cardholder to confirm (3-D Secure), the e-mail and the page ask them to pay with Use Another Card, on Stripe's page, where the bank can check.
- The invoice to pay offers Pay with Visa ending 4242, charged at once, beside Use Another Card. This is also how an upgrade is paid with the saved card, right after its preview, rather than from the preview itself.
- Removing the card stops the charges; invoices then wait to be paid by card or transfer.
- The admin invoice list shows each try, with its date, card, result and reason.
- A charge still processing is settled by Stripe's `payment_intent.succeeded` webhook, and no other is tried meanwhile.
- Checked with a faked Stripe in the tests, and on dev with a stand-in Past Due state (made-up keys, no Stripe calls) that was removed afterwards.

**Waits:** usage charges such as SMS, AI, extra storage and API (section 17) wait until those services exist. Section 19's notifications came with Doc 14 Phase B. Translated app names wait for Doc 14, and admin-side configuration beyond packages and the catalog waits for Doc 15.

**Careful:** the old `plans`, `subscriptions`, `invoices` and `payment_methods` tables and admin screens come from an earlier recruitment product. They work per user account, not per business, and do not fit Doc 13. Phase C replaces them rather than reusing them, except `payment_methods`, which holds Bank Transfer and, from Phase D2, Stripe's keys (encrypted).

### 2. Doc 14: Shared Platform Services (done 2026-11-12)

The gap check was done on 2026-11-09. Much of Doc 14 came with the earlier docs.

**Already built:**

- Each business's data kept apart, and package, app, role and location checked on the server.
- Shared master data picked from one place. On a sale or purchase, a contact or item can be added from its picker.
- Numbering for 9 document types: prefix, starting number, length, location code, a yearly, financial-year or monthly sequence, a preview, and unique numbers. Subscription invoices have their own sequence.
- One calculator (`SalesCalculator`, bcmath) for sales, purchases, expenses and credit notes. The server recalculates every total, and base-currency totals are stored.
- Tax: inclusive and exclusive prices, recoverable and non-recoverable purchase tax, withholding, effective dates, zero-rated, exempt and out of scope, and rounding per line or per document. Turning taxes off keeps their history.
- Payments: partial and multiple, withholding, reversal with a reason, attachments. Transfers between accounts.
- Files within the storage limit. PDFs, an e-mail delivery log with retries, revocable share links, and revisions of quotations and proformas.
- Contact and item imports: template, field mapping, required fields, duplicates, a review before importing, row errors and a summary. Exports follow the list's filters and are logged.
- Contacts, items, accounts and locations used in transactions can't be deleted; they are deactivated instead.
- Each app registers its Dashboard, Reports and Documents parts (Doc 13 section 20).

**Missing**, as suggested phases with defaults:

**Phase A: safe saving** (section 22) is built, with your defaults:

- A payment locks its invoice, purchase or expense while it checks what is still due, so two payments sent at the same moment can't both pass. Reversing a payment locks it too, so it is reversed once.
- Every create sends a key. Sent again because its answer never arrived, the request gets the record made the first time instead of a second one. This covers contacts, items, payment accounts, locations, transfers, sales documents and their conversions, credit notes, refunds, purchases, expenses and the three kinds of payment.
- Saving a contact, item, payment account, location, draft sales document, credit note, purchase or expense checks that nobody changed it since it was opened. If someone did, the form says "Someone else changed this contact after you opened it. Reload it to see their changes, then make yours again."
- Checked in the browser: a contact made from its form carried its key, and saving over a change made meanwhile was refused with that message.

**Phase B: notifications** (section 15), split on starting it (2026-11-09), with suggested defaults:

**Phase B1: the notification service and the bell** is built, with these defaults (checked in the browser: the bell's count, its list, opening a notification, Mark all as read):

- Each notification belongs to one user and records its deliveries: its trigger, channel (in-app or e-mail), related record, status and any failure. The same event reaches a user once.
- E-mails go through the queue and are tried 3 times. One still failing is kept as failed, with the reason.
- A bell in the header shows the unread count and the latest notifications, with Mark all as read. Each notification opens its record.
- A notification reaches only users allowed to see its record, by permission and location.
- First triggers:
  - Doc 13 section 19's subscription notices, in-app for those with Manage Subscription. Their e-mails to the billing e-mail and the owner stay as they are.
  - Apps turned on or off, in-app for those with Manage Apps.
  - An invitation accepted, in-app and by e-mail to whoever sent it.

**Phase B2: preferences, and usage limits** is built, with your defaults (checked in the browser: both screens show the kinds and save, and the business's change is logged):

- Settings > Notifications & Email has a Notifications card: what the business sends, in the app and by e-mail, for each kind: Subscription and billing, Usage limits, Apps and Users (Doc 11). Without Sales the tab is just Notifications, since document e-mail needs Sales.
- My Profile has the same card for what the user receives, in every business (Doc 12).
- A notification goes where both the business and the user allow it. A business starts with each kind's defaults (app notices by e-mail off), and a user receives whatever the business sends until they turn it off.
- Subscription and billing is always on, in the app. Its e-mails go to the billing e-mail and the owner, as before. Security and ownership notices will join it, always on, when they are sent.
- Sent by e-mail only, a notification stays out of the bell; by neither, it isn't made.
- Usage limits (Doc 13 section 19), checked hourly for those with Manage Subscription, in the app and by e-mail: storage nearly full or full; users or active locations near their limit, at it, or past it after a move to a lower plan. Each state is told once while the limit stays the same. A limit of one that is simply reached isn't told, as on the Dashboard, since every Billing Starter business is at it.
- E-mails go in the recipient's language where a translation exists (English for now).
- Mentions came with comments in Phase D, as a fifth kind: Comments and mentions.

**Phase C: global search and the header** (sections 3 and 16) is built, with these defaults:

- **Global Search.** A search box sits in the header. On narrower screens it is a button that opens the box over the bar. Ctrl K (⌘K on a Mac) jumps to it from anywhere. From two characters typed, it finds:
  - contacts by name, trading name, contact person, e-mail, contact number or phone. A phone typed with other spaces or dashes still matches, since only its digits are compared.
  - items by name, SKU, barcode or item number.
  - quotations, proformas, invoices and credit notes by number or customer or sales reference; purchases by number, reference or supplier invoice number; expenses by number, reference or receipt number.
  - customer payments and refunds, supplier payments and expense payments by number or reference.
- Results are grouped by kind, at most 5 of each. Each shows its name or number, its type, who it is for, its date, location, app and status, and opens its record. The arrow keys and Enter work. Archived contacts and items are marked and come after active ones.
- A kind comes only when the role may view it. Transactions come only from the user's locations. Records stay findable after their app is turned off, like the Documents page.
- **The header** shows the business's logo beside its name (hidden on phones), and a Help button that opens the Contact page (FAQ and contact form) in a new tab.
- **The location picker** lists the user's active locations, after All Locations. It is hidden while there is one location or none. A pick:
  - is where new sales documents, purchases and expenses start, in place of the user's default location;
  - is what the Dashboard and the reports open on, and they follow a new pick. Their own location filters still narrow one view;
  - is cleared when another business is picked.

  It is kept in the `izsuite_location` cookie as before.
- Checked in the browser:
  - As the owner: search by name, phone, SKU and a sales reference, the arrow keys and Enter, nothing found, the narrow-screen button and Ctrl K.
  - A new invoice starting at the picked location, after a reload too. The Dashboard and a report opening on the pick, and following All Locations again.
  - The logo beside the name, and the picker gone with one active location.
  - As a Sales User limited to one location: another location's invoice not found, and the business's contacts found.
  - The test data was removed afterwards.

**Phase D: notes, comments and activity** (section 14) is built, with these defaults:

- **Internal notes** sit beside the notes on sales documents (credit notes too), purchases and expenses. They never reach a PDF, print, e-mail or secure link. The record's page shows them in an Internal Notes card.
  - They change like the rest of the form, so only while the record can be edited. A conversion copies them into the new document.
  - The purchase and expense Notes field now says "Shown on its PDF", which it was all along.
- **Comments** sit on the same three record pages, in a Comments and Activity card, oldest first.
  - Anyone who may view the record reads and writes them, while its app is on. Only the author deletes one, and nobody edits them.
  - "@" suggests the business's users who may also view the record at its location. They are told in the app and by e-mail, under a new Comments and mentions kind that each business and user can turn off.
  - A mention's link opens the record at its comments.
- **The activity timeline** is that card's Activity tab, newest first. It shows what was done to the record and its money, by whom and when. izSuite's own steps say "izSuite".
  - The entries come from the Activity Log: the record's own, its payments' or refunds', the entry that made each document converted or credited from it, and comments written or deleted.
  - Anyone who may view the record sees it. Without the app's values the amounts are taken out of the text ("Recorded payment RCT-00001 for invoice INV-00001"), and the previous and new values are left out.
- Checked in the browser:
  - As the owner: the Internal Notes field and card, and a comment mentioning a Sales User, which suggested only those who may view invoices. The timeline showed it.
  - As that Sales User: the bell's notification opened the invoice at its comments, the owner's comment had no Delete, and "@" offered only the owner.
  - The test data was removed afterwards.

**Phase E: smaller gaps** (sections 4, 5, 6, 9, 12, 17 and 23) was split on starting it (2026-11-12): E1, who and where from; E2, numbers, files and names.

**Phase E1: who and where from** (sections 5, 9 and 17) is built, with these defaults:

- **Who added and last changed** each contact, item, payment account and location. A line under the record page's title says it ("Added by Nadia Karim, 28 Oct 2026 · Last changed by Salma Uddin, 30 Oct 2026"), and so does the Edit Location dialog.
  - Any save writes it down: a form, a deactivation, an import (put down to whoever started it).
  - A location's creator was already kept as its user, and carried over.
  - Records from before have nobody, and their line says only "Added" and the date.
- **The app that made** each sales document, purchase, expense and payment: "Made In: Sales Lite by Nadia Karim" in a sales document's History and among a purchase's or expense's facts, and "Made In" among a payment's details. Everything made before was made by its family's Lite app.
- **Where a document's exchange rate came from**, beside the rate on its page:
  - "your rate of 01 Nov 2026": the business's own rate in force on its date, from Settings > Exchange Rates;
  - "entered on it": a rate typed or changed on the document. Its date is the document's.
  - It is worked out whenever the rate is saved, and again when an invoice is issued at the business's rate. A conversion and a credit note keep their source's.
  - Payments are left out: a payment's rate is worked out from the amount paid and the amount it settles, not taken from a rate. Documents from before stay unknown and say nothing.
- **The payment account transactions export** is logged like the others, with its filters.
- Checked in the browser: a contact's added line, the Edit Location dialog's, and a EUR draft invoice whose rate the form filled from the business's rates ("your rate of 01 Nov 2026") and whose History says it was made in Sales Lite. The test data was removed afterwards.

**Phase E2: numbers, files and names** (sections 4, 6, 12 and 23) is built, with these defaults:

- **The action names of section 23.**
  - The lists' buttons: New Sale on Invoices, Create Quotation, Create Proforma Invoice, Record Purchase and Record Expense. The new sales pages are titled Create Invoice, Create Quotation and Create Proforma Invoice.
  - Add Item on a document's lines.
  - Archive and Restore in place of Deactivate and Activate, for contacts, items and payment accounts. Their filters, pills and messages say Archived, and so does the Activity Log.
  - Locations, roles and tax rates keep Deactivate, the word Docs 11 and 12 use for them.
- **A payee typed in on an expense** can be added as a contact: "Add "Corner Taxi Stand" as a contact" under it, for roles that may add contacts. It opens the quick supplier form with the name filled in, and the new contact becomes the payee.
- **Numbers typed by hand** (section 6), behind a new permission: Documents > Enter Numbers by Hand (`documents.number`).
  - The Business Owner and Administrator hold it, and so do Manager roles made from now on. Roles made before get it when it is ticked.
  - The Issue dialog on an invoice's or credit note's page, and the Record dialog on a purchase's or expense's page, have a Number field. Left empty, the document gets the next number, and so does one issued or recorded straight from its form.
  - A typed number must not be on another document of its type: "INV-00002 is on another invoice already. Choose another number." It takes letters, digits, spaces and `. _ / -`, up to 40 characters.
  - The next automatic number steps over one typed by hand.
  - Quotations and proformas get the next number when sent, and payments always do.
- **What a business may attach** (section 12), set in Admin > Settings > Files.
  - Attachment types, from those izSuite takes, and Largest attachment, 1 to 50 MB (10 by default).
  - Every record's attachments follow them: contacts, sales documents, purchases, expenses and payments. The file picker, the words under it ("PDF or PNG files, up to 5 MB each.") and the error ("Attach PDF or PNG files.") follow the admin's choice.
  - Files attached already stay. Item images, digital files and signatures keep their own limits.
- **Removing a file from a settled record** (section 12) needs the permission that undoes the record.
  - Settled means a sales document issued or sent, a purchase or expense recorded, or a payment completed, and the same once voided, cancelled or reversed.
  - Void for invoices, credit notes, purchases and expenses; Cancel for quotations and proformas; Reverse for payments. A draft's files go with editing it, as before.
  - The record's page hides Remove from a role without it, and the API refuses it: "This invoice is issued. Removing its files needs the permission to void it."
  - The Activity Log keeps the status: 'Removed attachment "signed-order.pdf" from invoice INV-2026/0042 (issued)'.
- Checked in the browser:
  - As the owner: an invoice issued as INV-2026/0042, typed by hand; a file attached to it and removed, logged with "(issued)"; a payee typed on an expense added as a supplier; Archive and Archived on contacts; Add Item on the lines.
  - In the admin: the Files tab. Narrowed to PDF and PNG at 5 MB, the invoice's Attachments said so and refused a JPG with "Attach PDF or PNG files." The defaults were put back.
  - Not checked in the browser, only by tests: the Number field when recording a purchase or expense, a role without Enter Numbers by Hand, and Remove hidden from a limited role.
  - The test data was removed afterwards.

**Suggested to leave, unless you say otherwise:**

- More than one tax on a line. Each line keeps one rate; compound taxes wait for country localization or full Accounting.
- Scanning uploads for viruses. It needs a scanner service, such as ClamAV, on the server; decide when deploying.
- Temporary draft numbers, which the doc allows but doesn't require. Drafts stay "Draft" until issued.
- A general internal-events framework and a log of background processes with retries. These fit Doc 15's system monitoring.
- Share links that expire. They stay until revoked.
- Imports beyond contacts and items.

### 3. Doc 15: izSuite System Administration Portal (done)

The gap check was done on 2026-11-13. The admin panel (`/admin`, Blade, web sign-in) had grown through Docs 01 to 14, partly from the earlier recruitment product, and was checked against all 27 sections.

**Already built:**

- A separate admin panel with its own sign-in page and web sessions, ending after 120 minutes idle. Admin roles (`roles`) are apart from the businesses' roles (`workspace_roles`).
- Admin > Roles: Super Admin may do everything; any other role lists the admin pages it may open, by route. Admin > Staff gives staff a role and can switch them off. (Both have faults; see below.)
- Admin > Users (the clients): each account's status, verification, sign-up and last sign-in, its businesses with their plans, and totals across them (users, locations, contacts, items, storage). It can be searched and filtered by plan.
- Login as: a one-time link that opens the user's dashboard for 2 hours, with a Back to admin button. It is logged against the user's account with the admin's name, but outside any business's Activity Log. The user's sessions list shows it as "izSuite support". *Replaced by support sessions in Phase B4.*
- Moving a business to another plan, after a review of what changes (Doc 13 Phase B2).
- Packages: the three plans, with name, description, limits (storage, businesses, users, locations), one price currency with monthly and yearly prices ("to be announced" when empty), trial and grace days, branding, and offered or not. Package Access sets which apps each plan includes, checked against the apps' dependencies.
- The Apps Center catalogue: categories, and apps in families of Lite and full editions, each with its key, icon, descriptions, features, support link, status (Available or Coming Soon), order and dependencies. The Menu Builder adds an app's sidebar pages by family, edition and permission, with no frontend rebuild.
- Billing (Doc 13):
  - Subscription Invoices: filters, confirming a transfer, cancelling, the PDF, and each card try with its result.
  - Subscription Taxes per country, with dates and reverse charge.
  - Payment Methods: Bank Transfer, and Stripe with test and live keys kept apart, encrypted and shown masked. Its webhooks are checked, and a payment is settled once.
- Languages with their translations, and currencies with their formatting. Settings has izSuite's billing details, the card retry days, mail, branding and the attachment limits.
- Website content: pages, header and footer, FAQs, and the contact form's messages with replies.
- Tickets with departments, answered by staff. They come from the earlier product: a business cannot open one from the app.
- Laravel's `/up` answers while the app is up, and failed queue jobs are kept in `failed_jobs`.

**Found while checking**, to fix first (proved with a throwaway test, then deleted):

- **A staff member switched off in Admin > Staff can still sign in.** Neither the sign-in nor the admin pages check the account's status. *Fixed in Phase A1.*
- **A staff member given a role made in Admin > Roles cannot sign in at all.** Only the roles named Super Admin, Admin and Staff count as admin (`User::isAdmin()` checks the role's slug), so new admin roles do nothing yet. *Fixed in Phase A1.*
- **The admin sign-in has no rate limit.** Thirty wrong passwords in a row were all answered. *Fixed in Phase A1.*
- **No admin action is recorded.** The activity log skips anything an admin does (`ActivityLogService::shouldSkipAdminActivity`), so the admin services' 61 log calls write nothing, sign-ins included. The 15 admin entries in the dev database are seeded demo rows. *Fixed in Phase A2.*
- The sidebar still shows the earlier product's Plans & Pricing and Subscription History. They work per user account and no longer fit, since Doc 13 replaced them. *Removed in Phase C1.* The Overview's figures are that product's too: Pro Users and Monthly Revenue are always 0.

**Missing**, as suggested phases with defaults:

**Phase A: admin access, roles and the audit log** (sections 2, 3, 25 and 26) was split on starting it (2026-11-13): A1, signing in to the admin; A2, the audit log, roles and critical actions.

**Phase A1: signing in to the admin** (section 2) is built, with your defaults:

- **The three sign-in faults fixed.**
  - A switched-off account is refused at sign-in, once the password is right: "This account is switched off. A Super Admin can switch it on again." An open session ends at its next request.
  - Every role but a website account's is an admin role, so a role made in Admin > Roles signs in and opens the pages it lists.
  - Five wrong passwords a minute, per address and IP address. The next try waits: "Too many sign-in attempts. Try again in 42 seconds."
- **Roles stay what they are.** Admin > Roles lists and edits admin roles only: User, Recruiter, Candidate and Affiliate are the website's and are left out. A role keeps the slug it was made with when renamed, and a new role cannot take a website role's name or a taken one.
- **Two-factor for every admin**, with the authenticator app the businesses use.
  - An admin without it signs in, then sees only its set-up page: a QR code and the key to type, then the app's first code. Ten recovery codes are shown once.
  - With it on, the code is asked after the password, or a recovery code instead. Five wrong codes, or five minutes, and the sign-in starts again.
  - The profile shows it with the recovery codes left, and makes new ones with the password.
  - A Super Admin resets another admin's from their Staff page, for a lost phone. They are signed out and set it up again.
- **Sessions end:**
  - after 30 minutes without a request, at the next one: "You were signed out after 30 minutes without activity." "Remember me" is gone from the admin sign-in.
  - when the account is switched off, gets a new role or password, or has its two-factor reset. An admin's own new password ends their other sessions only.
  - Nobody switches themselves off.
- **Passwords:** at least 12 characters for admins, with mixed case, a number and a symbol, on the staff forms and the profile. Website accounts keep 8.
- **Sign-in history:** each sign-in, and each failed attempt with its reason, with the time, IP address and browser. The profile shows the admin's own latest ten, and a staff member's Staff page shows theirs. The staff list says whose two-factor is set up.
- **New-browser e-mail:** a sign-in from a browser the admin has not signed in on before is e-mailed to them, with the time, browser and IP address. A browser is known by its own random cookie. The very first sign-in sends none.
- Every admin may open their own profile, password and two-factor pages, whatever their role.
- Checked in the browser as the seeded admin:
  - signing in without two-factor led to the set-up page, whose QR code drew, and other pages led back to it;
  - a wrong code was refused under the field, and the right one showed the ten recovery codes once;
  - the profile showed two-factor on and the sign-in;
  - after signing out, a wrong password was refused, and the right one asked for the code, which signed in;
  - the staff list and a staff page showed two-factor not set up, and Admin > Roles showed only Admin and Staff.

  The admin's two-factor was then reset and the check's sign-ins removed, so `admin@izsuite.io` sets two-factor up at its next sign-in. The idle end, the throttle, the reset and the e-mail are covered by tests only.

**Phase A2: the audit log, roles and critical actions** (sections 3, 25 and 26) is built, with your defaults:

- **The last fault fixed: admin actions are recorded**, in their own audit log, never a business's Activity Log.
  - Everything the admin services already logged now lands there, with each record's previous and new values.
  - What was never logged is too: Login as, clearing the cache, the admin's own profile and password, confirming or cancelling a subscription invoice, and each settings value saved.
  - Sign-ins, sign-outs and failed sign-ins (with the address tried) are there, and so is a page a role does not allow, as refused.
  - **Secrets are never kept**: payment keys, API keys, the mail password, client secrets and tokens. A settings save names the secrets it changed, without their values.
- **Admin > Audit Log** (under System): each entry's time, admin and their role then, what was done, the record, the result and the IP address.
  - It filters by admin, action, kind of record, result, dates and words or a record's ID.
  - An entry's page adds its previous and new values, the reason, the approval, the browser and a mark of the session.
  - **Export CSV** gives what the filters pick to roles allowed it, and the export is logged itself.
  - Nothing in the panel changes or deletes an entry, and the model refuses to.
- **The starting roles of section 3**, beside Super Admin, Admin and Staff. Each opens the pages of its area, and all stay editable:
  - Product Administrator: the app catalogue, Package Access, the Menu Builder, the website's pages, FAQs and content, and languages.
  - Billing Administrator: packages, subscription invoices and taxes, payment methods, currencies, and moving a business's plan.
  - Support Administrator: users and Login as, tickets and contact messages.
  - Compliance and Security Administrator: the audit log with its export, and the staff and role lists.
  - Read-Only Administrator: lists and details only. No forms, no exports, and not Settings, which holds keys.

  A migration made them on dev; a new database gets them from `RoleSeeder`. One edited since is never overwritten.
- **Critical actions** say what they affect, then ask for the password again and a reason. The audit log keeps the reason and "Password given again".
  - A wrong password is logged as refused, and five lock the step for ten minutes.
  - Critical now: changing a payment method that takes real payments (an offline one switched on, or Stripe live) by adding, changing or deleting it; saving Package Access, which changes every plan's apps; and resetting an admin's two-factor.
  - Not in the panel yet, so nothing to guard: making a Super Admin (Staff never offers it), overriding an ownership, suspending in bulk, and refunds. Each asks the same once built.
- Checked in the browser as the seeded admin:
  - Package Access refused a save with no password, then a wrong one ("That password is not right."), and saved with the right one and a reason;
  - the Audit Log listed the sign-in, two-factor turning on, the refused attempt and the save with "Reason given", and its page showed the reason and "Password given again";
  - the export gave the refused entry as CSV, and Admin > Roles listed the five roles;
  - an active Bank Transfer's Delete dialog asked for the password and a reason, and closed unused.

  The check's audit entries and sign-in were then removed, and the admin's two-factor reset again. Dev's admin styles were rebuilt (`npm run build` in the container), because the build dated from July and lacked newer Tailwind classes.

**Phase B: businesses, users and support access** (sections 5, 6, 7 and 22) was split on starting it (2026-11-13): B1, businesses and their statuses; B2, users and support access. Two of its items moved: extending a trial or grace period to Phase C, with the other subscription actions, and resending the owner's verification e-mail to B2, with the other account actions.

**Phase B1: businesses and their statuses** (sections 5 and 6) is built, with your defaults:

- **Admin > Businesses**, in the sidebar before Users, one row per business: name and ID, owner, plan and subscription, status, usage (users, locations and storage against the plan), sign-up date and last activity.
  - It searches by name, `BW-` ID, or the owner's name or e-mail. It filters by status, plan, subscription (Free, Trial, Awaiting Payment, Active, Cancellation Scheduled, Grace Period), country, sign-up dates, and "At or past a limit". A limit of one, such as Billing Starter's one user, counts only once passed.
  - Admin > Users stays the list of people.
- **A business's page:** its profile, owner (and whether their e-mail is checked), plan and subscription, billing details, usage, contacts, items and apps, its status with the last 20 changes, and the latest admin activity on it from the audit log.
  - Its buttons: Change Status, Send a Message, Owner's Account (the owner's page in Admin > Users) and Move Plan. Moving a plan now starts and ends on this page.
- **Business statuses**, apart from the subscription's. Nothing is deleted.
  - **Restricted** and **Under Review:** members read everything, but the API refuses every change: "izSuite has restricted this business: you can view its records, but not change them until the restriction is lifted." Marking notifications read still works. A banner above every page says so.
  - **Suspended** and **Closed:** a panel takes the place of every page, with Contact Support and Your Businesses. The API answers nothing for the business but its navigation and the bell.
  - **Closure Scheduled:** it works as usual, with a banner giving the day. Hourly, `businesses:close-due` closes it on that day, for the reason it was scheduled. Choosing another status before then cancels the closure.
  - The owner sees why and what reactivates it, on the banner or the panel. Anyone else reads "Its owner has the details."
  - Contact Support opens the website's contact page until B3's support cases.
- **Changing a status** asks for:
  - a reason, which the owner sees;
  - what reactivates it, optional, which the owner sees;
  - internal notes, for admins only;
  - for a closure, its day: today or later.

  "Tell the owner" is ticked by default: a notice in their bell, and an e-mail. Each change keeps the status before it, the reason, the notes, the day it took effect, the closing day, whether the owner was told, and the admin. The audit log has it too, with the reason.
- **Send a Message:** a title and a message, to the owner or to everyone in the business, in the bell. The owner gets it by e-mail as well. The audit log keeps it, with how many it reached.
- **A new notification kind, Messages from izSuite**, always on, in everyone's preferences. It carries the status notices and the messages.
- **Roles:** Billing and Read-Only Administrators open the list and the pages. Support Administrators may also send messages, and Compliance and Security Administrators may also change a status. A migration added these to the starting roles on dev, keeping whatever else each holds.
- Checked in the browser as the seeded admin and `user@izsuite.io`:
  - the list, its limits filter on MySQL, and the business page;
  - Restricted with a reason: the history said the owner was told, the audit log had it, the app showed the banner and the bell the notice, and saving a contact was refused with the message;
  - Send a Message with empty fields showed both errors under them, then reached the business's bell;
  - Suspended: the panel on every page, with why and what reactivates it, and the bell still opening;
  - Closure Scheduled for today: the banner with the day. `businesses:close-due` then closed it, and the app showed the closed panel;
  - Active again: the app was back, with no banner.

  The check found that the bell was refused while a business was shut, hiding izSuite's notice of it, so it now opens; the app also stopped asking a shut business for its lists. The check's status changes, notices, audit entries, activity and sign-in were then removed, workspace 1's setup (marked done for the check) undone, and the admin's two-factor reset.

B2 was split again on starting it (2026-11-13), as support access is a phase of its own: B2, users and their accounts; B3, support cases and support access.

**Phase B2: users and their accounts** (section 7) is built, with your defaults:

- **Admin > Users**, one row per website account:
  - its name, e-mail and phone; the businesses it owns, with their plans, and how many it is in; its status (Active, Locked or Inactive) with "Email verified" and "Two-factor on" under it; its security alerts; and when it joined and last signed in.
  - It searches by name, e-mail, phone or a business's name, and filters by plan, status, email verified, two-factor, and "With security alerts".
  - The row's View button went, as the name opens the page, so the list fits a laptop's width.
- **Security alerts:** 5 or more failed sign-ins in 7 days, and a phone number another account gives too, as typed. A wrong password or code for an account is now kept against it, with the IP address, without changing what the sign-in answers.
- **A user's page** adds:
  - two-factor (on since when) and the open sessions;
  - the alerts, with the latest failed sign-ins and the accounts sharing the phone number;
  - "Businesses They Are In": each business, theirs marked Owner, with their role and their place (Active, Invited or Suspended), and the business's status when it is not Active;
  - the latest admin activity on the account, from the audit log.
- **Its actions**, each asking for a reason, which the audit log keeps. Nobody sees or sets a password.
  - **Resend Verification**, for an account not verified yet: a new code by e-mail. No reason needed.
  - **Send Reset Link:** an e-mail with a link to choose a new password. It works once, for 60 minutes, and opens the website's reset page at the password step. It goes to a locked account too, so its password can change before it is unlocked.
  - **Sign Out Everywhere:** ends every session.
  - **Lock** and **Unlock:** a locked account cannot sign in ("This account is locked. Contact izSuite support to unlock it."), and its sessions end. Its businesses keep working for everyone else in them. The user is told by e-mail, without the reason.
  - **Reset Two-Factor**, for a lost phone and lost recovery codes: how support checked who is asking, then the admin's password and a reason, as a critical action. Its sessions end, and the user is told by e-mail.
- **Roles:** Support Administrators recover accounts (verification, reset link, signing out, two-factor). Compliance and Security Administrators open Users, and lock, unlock and sign out. A migration added these to the starting roles on dev.
- Checked in the browser as the seeded admin, with two throwaway accounts sharing a phone number:
  - five wrong passwords on the website made the alert, and the list's alerts filter found both accounts on MySQL;
  - the page showed the failed sign-ins with their IP address, and the other account;
  - Lock refused an empty reason under the field, then locked it;
  - Send Reset Link reached the mail log, its link opened the website at the password step and dropped the ticket from the address bar, and the new password worked;
  - signing in while locked gave the locked message; after Unlock it worked;
  - Sign Out Everywhere sent the website back to its sign-in page;
  - Reset Two-Factor refused a missing identity check, then reset it;
  - Resend Verification sent the code. The audit log had all six with their reasons.

  The list was reworked during the check to fit 1366 pixels. The throwaway accounts, their activity, the audit entries and the sign-in were then removed, and the admin's two-factor reset.

B3 was split again on starting it (2026-11-13), as a support session names a support case: B3, support cases; B4, support sessions.

**Phase B3: support cases** (section 22) is built, with your defaults:

- **In the app, Support** (the top bar's help button, and Contact Support on the status banner and panel): the business's cases, a page at a time, filtered by status; a new case (subject and message); and each case's conversation with a box to write on it.
  - Anyone in the business opens cases. The owner sees every case about it; anyone else sees the cases they opened.
  - Support stays open whatever the business's status: a suspended or closed business shows its panel everywhere else, but Support opens, and the API takes cases from a restricted one.
  - Writing on a resolved case opens it again; a closed one takes no more ("Open a new one if you still need help").
  - Text only from the app, no attachments yet. Staff's attachments show as links.
- **In the admin, Tickets are the cases:**
  - Each case names its business, which the list shows and searches, and the case's page links to.
  - A business's page lists its latest ten cases, with All Cases (the list kept to that business) and New Case, which opens the form for that business with its owner chosen; only people in it can be chosen.
  - Staff's reply moves an open case to In Progress. It reaches whoever opened the case in their bell (as a Message from izSuite) and by e-mail, with the reply's words and a link to the case. So does a case being resolved or closed.
- **Words stay words, both ways.** The app's messages show in the admin as text, never as markup: before this, the admin printed every message as HTML, so a message from the app could have run a script in an admin's browser. Staff's editor markup reaches the app as plain text, with paragraphs and link addresses kept.
- **Fixed on the way:** the new-ticket form offered the earlier product's recruiters and candidates instead of izSuite's users, and the assignee list offered website users as staff. The case count in the list counts messages, and reads "1 message".
- Checked in the browser as `user@izsuite.io` and the seeded admin:
  - the help button opened Support; an empty New Case showed both errors; the case opened with its notice, and markup typed into it showed as typed;
  - in Admin > Tickets the case named the business; its page showed the app's words as text; a reply with bold text reached the app as text, in the bell and by e-mail, and moved the case to In Progress;
  - a reply from the app, then Resolved in the admin: the bell and e-mail said so, and the app said writing opens it again;
  - the business's page listed the case, and New Case opened the form for it with the owner chosen;
  - with the business suspended, Contact Support on the panel opened the new-case form, and other pages showed the panel again.

  The check found Support kept behind the panel when opened from the panel's own button, which is fixed (see [Decisions worth knowing](#decisions-worth-knowing)). The check's case, its notices, the status change, activity, audit entries and sign-in were then removed, workspace 1's setup undone, and the admin's two-factor reset.

**Phase B4: support sessions** (section 22) is built, with your defaults. Phase B is done.

- **Support sessions replace Login as**, which is gone: its button, route, service and page.
- **Opening one**, from a business's page:
  - **Support Session** opens a read-only one; **With Edit Access** opens one that can make changes. Each is its own permission. Edit access is the Super Admin's until a role is given it.
  - Either names one of the business's open cases, a reason the business sees, and how long: an hour to four. Edit access also keeps how the customer agreed.
  - Both are kept with the admin, start and end. The owner is told in the bell and by e-mail; the business's Activity Log and the audit log keep it.
- **Entering it:** the business's page lists its sessions, live ones first. **Enter in the App** - for the admin who opened it only, as what it does is logged as theirs - opens the business in the app as its owner, through a one-time link that works for a minute.
- **In the app**, a banner throughout: "izSuite support is viewing this business, read only, until …", with the case, the admin and End Session, which goes back to the business's admin page. The help button, the bell and My Profile do not show.
- **What a session may do:**
  - only that business, whatever else its owner has;
  - never the owner's own account, password, bell or invitations, nor an export;
  - read only: no change at all ("This support session is read only: nothing can be changed.");
  - with edit access: changes, but never to the business itself, its subscription and billing, its ownership, or the support cases ("izSuite support cannot change this, even with edit access.");
  - payment accounts' full details show masked, whatever the access.
- **The business's Activity Log** shows the session's start (case, reason, until when), the first time it opens Settings, Payment Accounts or Users, everything an edit session changes, and its end - each by "izSuite support (name)".
- **Ending:** a session ends by itself at its time (its tokens stop then, and a job every five minutes records the end), from the business's page (End), or in the app (End Session, every tab of it).
- **Admin > Support Sessions** (sidebar, after Tickets) lists every session for review: business, admin, case and reason, access and the customer's agreement, when, and the areas it opened; by state, access, and business or admin. Support, Compliance and Security, and Read-Only Administrators open it.
- Checked in the browser as the seeded admin, on workspace 1 with an open case:
  - the dialog refused an empty reason under the field, then opened the session, and the business's page offered Enter in the App;
  - the link opened the app at the dashboard with the banner, without the help button and bell; saving a contact was refused with the read-only message;
  - the business's Activity Log showed the start, then Settings and Payment Accounts opened, by "izSuite support (Admin)";
  - End Session went back to the business's admin page; the session showed ended, its tokens gone, and Admin > Support Sessions listed it with the areas it opened.

  The check found My Profile still in Settings' tab row, and a generic log line for the session's sign-out; both fixed. The first link expired before the freshly restarted dev app had built its page (a minute); the second worked. The check's session, case, notices, activity, audit entries and sign-in were then removed, workspace 1's setup undone, and the admin's two-factor reset.

**Phase C: subscriptions and billing** (sections 9, 13, 14 and 17) was split on starting it (2026-11-13): C1, subscriptions in the admin; C2, billing records, refunds, credits and reports; C3, prices in several currencies and promotions. The adjustment, a credit taken off the next invoice, moved from the subscription actions to C2, with the other credits.

**Phase C1: subscriptions in the admin** (section 13) is built, with these defaults:

- **The earlier product's Plans & Pricing and Subscription History are gone:** their pages, routes, controllers, services and requests, and its invoice PDF (`InvoicePdfService` and the recruiter PDF views). One Subscriptions link in the sidebar takes their place, and the Overview's Plans card is now Subscriptions. Their tables stay (see [Open decisions](#open-decisions)), and so does the API's `/plans`.
- **Admin > Subscriptions** lists every business's subscription, newest first:
  - the business with its BW- ID and owner's e-mail; the plan, cycle and currency; the status; the dates (the trial, the paid period with its renewal or end, the grace, or the first invoice's due day); and how it is paid (the saved card, or bank transfer), with when it was last paid or the invoice waiting;
  - filters: status, plan, cycle, paid by card or bank transfer, and a search by the business's name or BW- ID, or its owner's name or e-mail.
- **Statuses** as the doc names them, worked out as the app does: Trial, Awaiting Payment, Active, Past Due (active, its renewal waiting and the card's last try refused, as the app says it), Cancellation Scheduled, Downgrade Scheduled (a move down waiting for the period's end), Grace Period and Cancelled. The business's own status (Suspended and the rest) shows beside it when not Active. A business on Billing Starter has no subscription, so Free is not listed.
- **A subscription's page**, from the list or the business's new Subscription button: the plan, the dates, what waits for the period's end, how it is paid, the invoice waiting and the owner; the business's latest 12 invoices, each with its card tries; and the admin activity on the business.
- **Its actions**, each its own permission and offered only where the subscription's state fits:
  - **Change Plan** (Active), as the business itself would, through Doc 13's plan changes. Up (a higher plan, or monthly to yearly) issues its invoice now and changes the plan once paid; down waits for the paid period's end. Billing Starter is not offered: that is a cancellation.
  - **Extend Trial** and **Extend Grace**, by 1 to 60 days. The trial's invoice is issued again, due on its new last day, and the old one voided.
  - **Schedule Cancellation** (Active or Past Due): the plan stays until its paid period ends, then the business moves to Billing Starter and keeps its records. An issued renewal is cancelled.
  - **Undo Scheduled Change**: a scheduled cancellation (its renewal is issued at once if due) or a move down.
  - **Resend Invoice**: the invoice waiting, else the latest, as its receipt once paid.
  - **Retry Card**: the saved card charged again, now, for the invoice it failed on. It shows only when card payments are set up and the card failed on an invoice still waiting; the message says what happened.
- Every change asks for a reason. The audit log keeps it, and the owner is told it in the bell, as a Message from izSuite, and by e-mail, with a button to their subscription. Resending and retrying change nothing, so they ask for none.
- **Roles** (migration 000131, roles only): Billing Administrators get the list, the page and every action; Support Administrators the list, the page and Resend Invoice; Read-Only Administrators the list and the page. The old screens' permissions were taken off every role. A role that had Subscription History (in dev, the earlier product's Admin) keeps the list alone; give it more in Admin > Roles if wanted.
- Checked in the browser as the seeded admin, on workspace 1 with Standard and Premium priced for the check and a Premium trial started as the app does:
  - the list, its filters, and the trial's page with its invoice;
  - Extend Trial refused an empty reason under the field, then moved the trial's end to 3 Dec and issued its invoice again, the first one voided;
  - Resend Invoice kept the page and said "Invoice IZS-INV-00002 is on its way again.";
  - with the trial's invoice confirmed (Active, paid early): Change Plan to Standard, monthly, scheduled the move for 4 Jan 2027 (Downgrade Scheduled), and Undo took it back; Schedule Cancellation showed "Ends after Jan 03, 2027", and Undo took that back too;
  - each change kept its reason in the audit log and reached the owner's bell, and the business's page linked to the subscription.

  The check found the plan filter offering Billing Starter, Resend's message lost to the page's reload, a trial paid early not showing that it still runs, and two dialogs' wording; all fixed. A review found the actions handing Doc 13's billing a business loaded without most of its columns, also fixed (see [Things that catch you out](#things-that-catch-you-out)). The check's subscription, invoices and their numbering, billing details, notices, activity, audit entries and sign-ins were then removed, workspace 1 moved back to Billing Starter, the plans' prices emptied again, and the admin's two-factor reset.

**Phase C2: billing records** (section 14) was split on starting it (2026-11-13): C2a, payments and refunds; C2b, credit adjustments and the billing reports.

**Phase C2a: payments and refunds** is built, with these defaults:

- **Every payment is kept as one.** A bank transfer izSuite confirms is now a payment too, as a card's always was (provider `transfer`, its reference the invoice's number). Migration 000132 made one for each transfer confirmed before. Payments are never deleted; refunds correct them.
- **Admin > Payments** (sidebar, after Subscription Invoices) lists every payment, newest first: when, the business, the invoice, how it was paid, the amount, and its status with what of it is refunded. The filters cover status (Paid, Failed for a declined card or one waiting for the cardholder, Processing, Not applied, Refunded), card or bank transfer, and a search by business, BW- ID, owner, invoice number or reference.
- **An invoice's own page**, from its number in Subscription Invoices, Payments or a subscription's page, shows the fields section 14 names:
  - who it is billed to (name, address, country, tax ID, e-mail), the business and owner;
  - the plan, the billing period, and the issue, due and paid dates;
  - subtotal, any upgrade credit, tax, total, amount paid, refunded and balance;
  - its payments, each with its refunds.

  Its actions are the PDF and receipt, Confirm Payment and Cancel Invoice while it is open, and links to the subscription and the business. The list shows what is refunded under each total.
- **Refunding a payment**, from the invoice's page:
  - how much, up to what is left: its amount less the refunds done or still waiting, so two admins at once never give back more than was paid;
  - the admin's password again and a reason, as for every critical action (section 15.25 makes large refunds critical; every refund asks for now);
  - a card payment's refund goes through Stripe and counts once Stripe confirms it, at once or later through its webhook (`refund.updated`). A refund Stripe refuses is kept as Failed, with Stripe's words. A refund made in Stripe's own dashboard, of a payment izSuite knows, is recorded too, without an admin;
  - a transfer's is recorded at once, with the bank's reference of izSuite's transfer back. Send the money first.
- A refund changes neither the invoice nor the plan; cancel the subscription separately if it should end. Once a refund is done, the business is told in the bell and by e-mail to the billing e-mail and the owner ("izSuite has refunded USD 5.00"), and its Billing History shows "Refunded" under the invoice's total.
- **Admin > Refunds** lists every refund: when, the business, the invoice and payment, the amount, where it stands (Refunded, Waiting for the provider, Failed with why), the reason and who made it.
- **Roles** (migration 000132): Billing Administrators get the payments, the refunds, an invoice's page and refunding; Read-Only Administrators the payments, the refunds and the invoice's page; every role that lists the invoices may open one. Support Administrators see a business's invoices on its subscription's page only.
- The note on a card payment that could not pay its invoice now says to refund it from its invoice, not in Stripe.
- Checked in the browser as the seeded admin, on workspace 1 with Standard priced for the check and its first invoice paid by transfer:
  - Admin > Payments listed the transfer, and its invoice's page showed the billing details, the period, paid and confirmed, and Amount paid and Balance;
  - the refund dialog refused the refund without the password, under the field, then recorded USD 5.00 with the bank's reference. The page then showed Refunded USD 5.00, with USD 14.50 left to refund;
  - Admin > Refunds listed it with its reason, reference and admin, and the audit log kept the reason;
  - the owner had the bell notice and the e-mail, and the app's Billing History showed "Refunded $5.00" under the total.

  Card refunds were not tried on dev, which has no Stripe test keys: the tests fake Stripe's API. The check found long references breaking mid-word and an audit line saying "by Bank transfer"; both fixed. The check's subscription, invoice, payment, refund, numbering, billing details, notices, activity, audit entries, sign-ins and the app's sign-in token were then removed, workspace 1 moved back to Billing Starter with its setup undone, Standard's price emptied again, and the admin's two-factor reset.

**Phase C2b: credit adjustments and the billing reports** (sections 13 and 14) is built, with these defaults. Phase C2 is done.

- **izSuite's credit to a business**, the adjustment section 15.13 names:
  - **Give Credit** on a subscription's page: an amount in the plan's currency and a reason. **Take Back Credit** takes back what is not used yet, never more. Each is its own permission, the audit log keeps the reason, and the owner is told in the bell and by e-mail.
  - It comes off the business's next invoice, before tax: a renewal, an upgrade or a new subscription's. An invoice waiting now keeps its amount, and the credit comes off the one after. What is left carries on to the invoices after.
  - An invoice the credit covers in full is paid by it at once. Its receipt goes out in place of the invoice, and it says "Paid with izSuite credit".
  - An invoice cancelled gives back what it took: by the admin, by a cancellation scheduled, by an upgrade, or by the plan ending.
  - The invoice shows it as "Credit from izSuite", in the admin, on its PDF and in the app. The subscribe and plan change dialogs show it too, and the app's Subscription page shows the credit left.
- **Admin > Credits** (sidebar, after Refunds) lists every movement: given, taken off an invoice, given back, taken back - each with its business, amount, invoice, reason and admin.
- **Admin > Billing Reports** (sidebar, after Credits), for a span of months (the last twelve unless chosen), a currency and a plan:
  - revenue by month and by plan, by currency: invoices paid, collected, the tax in it, refunded and kept (collected less refunded), with totals. Invoices count in the month they were paid, refunds in the month the provider confirmed them;
  - invoices open and overdue now, by currency, with the longest overdue listed;
  - failed card payments by month: tries, businesses and amount, with the latest and why;
  - credits: given and taken off invoices in the months, and what is left now.

  Amounts never mix currencies, and no business's own transactions show.
- **Roles** (migration 000133): Billing Administrators get giving and taking back credit, the credits and the reports; Read-Only Administrators the credits and the reports.
- Table headers over figures now line up with them across the admin (`.data-table thead th.text-end`).
- Checked in the browser as the seeded admin, on workspace 1 with Standard paid by transfer:
  - Give Credit gave USD 25.00 ("Billing Starter has USD 25.00 of credit, to come off the next invoice."), and Take Back Credit, its amount filled with what was left, took back USD 5.00;
  - Change Plan to Premium issued IZS-INV-00002 for USD 9.50: 49.00, less 19.50 for the unused Standard days, less the USD 20.00 credit. Its page and its PDF showed the lines, and Admin > Credits the three movements;
  - Billing Reports showed November's invoice by month and by plan, the upgrade open and not yet due, and no failed payments;
  - the app showed the invoice to pay with "Credit from izSuite", and the Subscription page "izSuite credit $3.00, taken off your next invoices before tax."

  The check found the dialog's wording when an invoice is waiting, a wrapping invoice number, and table headers not lining up with their figures; all fixed. The check's subscription, invoices, payment, credits, numbering, billing details, notices, activity, audit entries, sign-ins and the app's token were then removed, workspace 1 moved back to Billing Starter with its setup undone, the plans' prices emptied again, and the admin's two-factor reset.

**Phase C3: prices and promotions** (sections 8, 9 and 17) was split on starting it (2026-11-13): C3a, prices in several currencies; C3b, promotions.

**Phase C3a: prices in several currencies** is built, with these defaults:

- **A plan's prices** are `package_prices` rows, one per currency and cycle from a start day. A new one ends the one before the day before it starts; a price that has started is history and stays, while one not started yet can be removed. The plan's own currency is now its main one. The prices the plans had before carry over, in force from the start (dated 2000-01-01), and the two price columns on `packages` are gone.
- **Which price a business pays:** in its own base currency when the plan has a price in it, else in the plan's main currency. The business's own currency is left as it is. The app's Subscription page and its quotes, invoices and plan changes all use it; the website's Pricing page shows each plan's main currency.
- **Subscribers keep their price** (§15.8: "not silently moved"). A subscription records the price it pays, and a renewal on the same plan and cycle charges it. A move to another plan takes that plan's price then.
- **A new price reaches subscribers only when applied:** Apply to Subscribers, on the price in force, moves every subscriber renewing on another price of that currency and cycle. It applies from their first renewal on or after a day at least 30 days ahead, and each is told now by e-mail and in the bell. It asks for the password and a reason. Subscribers ending their plan or moving to another are left out.
- **Admin > Packages:** the list shows each plan's prices in force. A plan's page has a **Prices** card listing every price with its days, status (In force, Starts on a day, Ended) and how many subscribers pay it, move to it or pay another. It also has Add Price (currency, cycle, amount, from), Remove for a price not started yet, and Apply to Subscribers.
- **The app's Subscription page** shows the price the business pays, before tax, and a new price with its day once izSuite has applied one. Compare Plans shows each plan at today's price.
- **Roles** (migration 000134): Billing Administrators add, remove and apply prices.
- Left for later: prices by country rather than currency, by customer type, setup fees, and included or overage usage (they wait for usage charges).
- Checked in the browser as the seeded admin, on workspace 1 with Standard at USD 19.50 from 1 Nov and a subscriber on it:
  - the list showed Standard's prices in force, and its Prices card the two prices with "1 pays it";
  - Add Price USD 24.00 a month from today ended the 19.50 the day before, and showed "1 pays another price" with Apply to Subscribers;
  - Apply to Subscribers refused it without a reason, then moved the subscriber from 13 Dec 2026, its date filled in 30 days ahead. The card then said "1 moves to it", and the owner had the e-mail and the bell notice;
  - a yearly price from 1 Jan 2027 ended the yearly one before it on 31 Dec, and Remove took it away and reopened that one;
  - the subscription's admin page and the app's Subscription page showed "$19.50 a month… From your first renewal on or after 13 Dec 2026: $24.00", and Compare Plans showed Standard at $24.00 for new subscribers.

  The check found the card still offering Apply for a subscriber already moving, and "1 subscribers" in the audit line; both fixed. The browser pane was hidden, so the card was checked from its text rather than screenshots. The check's prices, subscription, invoice, payment, numbering, billing details, notices, activity, audit entries, sign-ins and the app's token were then removed, workspace 1 moved back to Billing Starter with its setup undone, and the admin's two-factor reset.

**Phase C3b: promotions** (section 17) is built, with these defaults:

- **A code** is a percent (up to 100) or a fixed amount in one currency, off a new subscription's **first invoice only**, before any credit and tax. Renewals are at the full price: the subscription keeps the plan's whole price as the one it pays.
- **Where it holds:** the plans, cycles and billing countries it names (none named, all), from and until its days (both included), up to its uses in all and per business (1 by default), and for new customers only (businesses that never paid an invoice) if set. Paused, it takes nothing off. A code is kept in capitals and typed in any case.
- **A use** is an invoice that took the code, until that invoice is voided (withdrawn, cancelled): the use is given back. The last use is held while an invoice takes it, so it cannot go twice.
- **Refused,** the business is told why: no such code, not in use, not started (with its day), ended, not for this plan, cycle or country, for prices in another currency, used up, used by this business already, or for new customers.
- **A code taking the whole price off** settles the first invoice at once, like a credit covering it: paid with "Promotion code", the receipt in place of the invoice, no payment row.
- **Admin > Promotions** (Billing): a list with search, each code's discount, what it is for, its days, uses (taken / limit, per business) and status (Active, Paused, Not started, Ended, Used up); Add and Edit; Delete only while never taken (taken, it is paused or ended instead, as its invoices name it). The invoice page and PDF show "Discount (CODE)".
- **The app's subscribe dialog** has "Have a promotion code?": Apply checks it with the price and shows the code's line, the total and what each renewal comes to, or why it is refused; Remove takes it off. The invoice to pay shows the code's line too.
- **Roles** (migration 000135): Billing Administrators add, edit and delete promotions; Read-Only Administrators see the list.
- Also fixed: a receipt paid with izSuite's credit printed "by card, izSuite credit"; it now says "with credit from izSuite".
- Left for later: codes for plan changes and renewals, a discount for several periods, and codes made in bulk or for one business.
- Checked in the browser as the seeded admin, on workspace 1 billed in Bangladesh with Standard at USD 19.50:
  - Add Promotion saved "launch20" as LAUNCH20, 20% off Standard, and the list showed it Active, taken 0;
  - in the app's subscribe dialog, "nope" was refused with "There is no promotion with this code.", and "launch20" showed "Code LAUNCH20 −$3.90", Total $15.60 and "Each renewal after it comes to $19.50";
  - Subscribe issued IZS-INV-00001 for $15.60; the invoice to pay showed the code's line, and the Subscription page the price as $19.50 a month;
  - the admin's invoice page showed "Discount − USD 3.90 (LAUNCH20)", the list "taken 1", and Edit "Taken 1 time".

  The check found the countries hint written for a plain list, where the admin's Select2 box searches; fixed. The browser pane was hidden, so most of it was checked from the page text. The check's promotion, prices, subscription, invoice, numbering, billing details, activity, audit entries, sign-ins and the app's token were then removed, workspace 1's setup undone, and the admin's two-factor reset.

**Phase D: the dashboard, usage and service health** (sections 4, 18 and 23) was split on starting it (2026-11-14): D1, the Overview; D2, usage and its history; D3, service health and incidents.

**Phase D1: the Overview** (section 4) is built, with these defaults:

- **Admin > Overview** (the sidebar's Dashboard) has real figures in place of the earlier product's (Pro Users and Monthly Revenue at 0, and a chart of accounts by month).
- **The businesses now:** all, Active, on a trial, Past Due (as Admin > Subscriptions has it: running, its renewal's card refused), in grace and Suspended, and how many are on each plan, with Open Businesses.
- **The chosen days** (the last 30, today included, unless chosen):
  - new businesses, with a chart by day (or by month, from 92 days);
  - new accounts: website accounts made, an owner's at sign-up or an invited user's;
  - active users: website accounts that signed in, used the app (a token used) or changed a record a business's Activity Log keeps.
- **The most activated apps** (the five most businesses have active), and **storage**: what the businesses' files take, and how many use 80% of their plan's storage or more.
- **Background jobs:** failed in the days, failed ones kept in all, and jobs waiting on the queue now.
- **Subscription billing**, by currency and never mixed: invoices paid in the days and what they came to (tax included), refunds made in them, revenue (the two apart), and card payments that did not go through (tries, businesses, amount), with Open Billing Reports. It shows only to admins who may open Billing Reports.
- **Filters** in one row: the days, plan, subscription (as Admin > Businesses), country (the business's) and app (one it has active) pick the businesses every figure counts; the currency picks the billing's. Days are in UTC. Only sums and counts show, never a business's own transactions.
- No migration, and nothing changes in the frontend.
- Platform service alerts wait for D3, which makes them.
- Checked in the browser as the seeded admin, on dev's own data (workspace 1 on Billing Starter, with three apps): the figures, the chart by day and, for 2026, by month, the apps' bars, the billing table empty ("Nothing paid, refunded or refused in these days."), and reversed days refused under the filters ("The last day cannot come before the first.").

  The check found the chart's canvas, drawn while the browser pane was hidden, wider than its card, which made the whole page scroll sideways; its box now clips it. The page only reads, so the check left nothing to remove.

**Phase D2: usage and its history** (section 18) is built, with these defaults:

- **Admin > Usage**, after Businesses in the sidebar, has one row per business:
  - its users (active or invited, the owner at least), active locations and storage, each with its plan's limit, the share used and a bar;
  - its apps on, and how many its plan includes;
  - where it stands.
- **States:**
  - OK;
  - Nearing its limit, from 80% (storage: Settings' warning share);
  - At its limit;
  - Past its limit, after a move to a lower plan.

  A limit of one at one, such as Billing Starter's one user and location, is OK, as in Admin > Businesses. A row takes the worst of its three.
- **Filters:** words (name, BW- ID or the owner), plan, state (nearing or past, at or past, past) and the limit it looks at (users, locations or storage; else any).
- **Order:** closest to a limit (the default), share of users, locations or storage, most storage, or newest. The shares it sorts by leave a limit of one out, so Billing Starter's businesses do not crowd the top.
- **A business's Usage page**, from the list or Usage History on its Admin > Businesses page:
  - each limit now, and its apps;
  - its history for the chosen days (the last 90 unless chosen): a chart of users, locations and apps, with storage in MB on its own axis, and the days its plan or usage changed.
- **Snapshots:** `usage:snapshot` keeps each business's usage once a day, at 23:50 UTC, in `workspace_usage_snapshots` (migration 000136), with its plan and that plan's limits then.
  - Closed businesses are left out.
  - Run again the same day, it overwrites that day's.
  - Nothing is deleted, and no limit removes a business's data.
- The Overview's storage figure links to Admin > Usage, filtered to businesses nearing a storage limit.
- **Roles** (migration 000136): Billing, Support and Read-Only Administrators open Admin > Usage.
- Left for later: API, automation, AI and messaging usage, other metered services and additional charges. They wait for those services (Doc 13's usage charges).
- Checked in the browser as the seeded admin, on workspace 1 (Billing Starter), with today's snapshot and four earlier days added for the check:
  - the list, where 1 of 1 user and location show full but OK;
  - the business's page, with its limits, the chart, and the days it changed (Nov 12, unchanged, left out);
  - the links from the business page and the Overview.

  The check found "1 businesses" in the command's line; fixed. The check's snapshots were then removed.

**Phase D3: service health and incidents** (section 23) was split on starting it (2026-11-14): D3a, service health; D3b, incidents.

**Phase D3a: service health** is built, with these defaults:

- **Admin > Service Health**, under System in the sidebar, asks each service afresh on every visit and shows it as OK, Warning, Failing or Not used, with what it found:
  - **the app**, which answers the page itself;
  - **the scheduler**, by a heartbeat `health:heartbeat` keeps each minute. Failing after 5 minutes without one;
  - **background jobs**: waiting now, failed (24 hours, and kept in all), and the workers, by a heartbeat job queued each minute. Failing after 10 minutes without a job run while the scheduler runs; a warning for any job failed in 24 hours;
  - **the database** (its answer time; a warning from 500 ms), **the cache and sessions**, **file storage** (a probe file written, read back and removed; a warning under 10% of the disk free) and **the PDF maker** (Gotenberg's health address);
  - **e-mail**: the mailer (a warning while it only writes to the log), and notification and document e-mails that failed;
  - **card payments**: Not used while Stripe is off; else its mode, the last webhook and how it went, and webhooks refused and calls failed in 24 hours (a warning for either);
  - **imports and exports**: imports failed, and stuck with no progress for an hour. Exports are made as they are asked, so none wait;
  - **sign-ins and security**: failed account and admin sign-ins, accounts failing 5 times in 7 days (as Admin > Users has it), locked accounts, and admin actions refused. A warning for repeated failures or refusals;
  - **text messages and exchange rates**: Not used. izSuite sends none, and businesses enter their own rates.
- **What failed:** the latest failed jobs, notification and document e-mails, imports, and card payment failures.
- **Running a failed job again** is offered only for jobs that check before they act, so they never send, add or charge twice: notification and document e-mails (both skip what is sent), a new account's set-up jobs, removing stored files, and the heartbeat. A queued mail may have gone out, and an import is the business's to run again, so neither is retried there; **Forget** takes a job off the list. Both are in the audit log.
- **Platform Alerts** on the Overview list each service warning or failing, with Open Service Health.
- Stripe's webhooks taken or refused, and its calls that failed, are kept as `service_events` (migration 000137) for 30 days.
- **Roles** (migration 000137): Compliance and Security Administrators open Service Health and run again or forget failed jobs. Billing, Support and Read-Only Administrators open it.
- Checked in the browser as the seeded admin, on dev, with two failed jobs added for the check (a notification e-mail with nothing to send, and a queued mail):
  - every service read as it is on dev: e-mail warns, as dev only writes it to the log, and card payments, text messages and exchange rates are not used;
  - Background Jobs warned about the two jobs. The mail had no Run Again, and said why;
  - Run Again put the e-mail's job back on the queue, which the worker ran, finding nothing to send. Forget, through its dialog, took the mail off. The audit log had both;
  - the Overview's Platform Alerts then listed only e-mail.

  The check found the storage probe leaving an empty folder behind; it now writes at the disk's root. The check's failed jobs and audit entries were then removed.

**Phase D3b: incidents** (section 23) is built, with these defaults:

- **An incident** is something wrong with a service. It has:
  - a title and the service it is about (as Service Health names them), or none;
  - a severity: Minor, Major, or Critical when businesses cannot work;
  - when it started (UTC, not in the future) and the admin responsible;
  - a status: Open, Investigating, Monitoring or Resolved.
- **Admin > Incidents**, under System after Service Health, lists them, those not resolved first. Filters: status (or not resolved), service, severity and words of the title.
- **Opening one** (Open Incident, or "Open an incident" beside a service that warns or fails on Service Health, which fills in the service) makes its summary the first note.
- **Its page** has its details, Edit (title, service, severity, summary, start) and its timeline, newest first:
  - **Add a Note**: what was found or done, the status it moves to and who is responsible from now (or nobody);
  - each note shows any status change ("Open → Resolved") and who it made responsible;
  - resolving stamps the time and how long after it started; moving back from Resolved clears it.
- **Service Health** lists the incidents not resolved, the most severe first. The Overview's Platform Alerts count and list them.
- Opening, editing and each note are in the audit log.
- **Roles** (migration 000138): Compliance and Security Administrators open, edit and add notes. Support Administrators add notes. Billing and Read-Only Administrators look.
- `incidents` and `incident_notes` (migration 000138). A note keeps the status before and after, and who it made responsible, by name as they were then.
- Left for later: e-mailing the admin made responsible, and telling businesses about an incident (Phase F's announcements).
- Checked in the browser as the seeded admin: "Open an incident" beside E-mail on Service Health opened the form with E-mail, Major, now and the admin filled in. Opening it showed its page with the summary as the first note; a note to Resolved stamped it "1 minute after it started" and showed "Open → Resolved"; the list showed it Resolved. The check's incident, notes and audit entries were then removed.

**Phase E: the catalogue and packages in depth** (sections 8, 10 and 12) was split on starting it (2026-11-14), the self-contained part first: E1, feature flags; E2, apps in depth; E3, packages in depth.

**Phase E1: feature flags** (section 12) is built, with these defaults:

- **A flag** is a feature the code asks about by its key: `Features::enabled('new_reports', $workspace)`. Each has:
  - a key, fixed once made, a name and a description;
  - its switch, On or Off. Off overrides everything: the emergency switch;
  - its audience: the businesses it names (by BW- ID) always, then a share (0 to 100%) of the businesses in the plans and countries chosen (none chosen: all). The share is steady: a business in at 10% stays in at 20%;
  - its days, from and until (UTC, either open), and the admin responsible.

  Internal testing is a flag naming izSuite's own businesses with a share of 0%. Without a business, only a flag for every business is on.
- **Its state:** Off, Scheduled (on, before its start), Live, or Ended (on, after its end).
- **Admin > Feature Flags**, under App System after the Menu Builder, lists them with their state, audience, days and the admin responsible.
  - Switch Off and Switch On act at once.
  - Add and Edit are there; a flag keeps its key.
  - Delete is only for a flag that is off. Nothing made while it was on is removed: the code asking for a deleted flag finds it off.
- **The app gets them:** `/navigation` gives the business's features (`features`, by key), read fresh each time like its logo and status. The frontend asks `useNavigation().feature('new_reports')`.
- Flags switched on are kept in the cache for 5 minutes; every change in the admin forgets them, so it counts at once.
- Every change is in the audit log.
- **Roles** (migration 000139): Product Administrators run flags; Read-Only Administrators see the list.
- No feature asks for a flag yet: they are ready for the next ones.
- Checked in the browser as the seeded admin: Add Flag made `browser_check` for BW-000001, off; Switch On made it Live, with "1 business named", and workspace 1's navigation carried it; Switch Off took it out at once; Delete, through its dialog, removed it. The audit log had all four. The check found "From when on" for a flag without a start; it now says "From when switched on". The check's audit entries were then removed.

**Phase E2: apps in depth** (section 10) is built, with these defaults:

- **An app's statuses:**
  - **Draft**: not listed;
  - **Coming Soon**: listed, cannot be turned on;
  - **Published**: the old Available, still stored as `available`;
  - **Temporarily Unavailable** and **Deprecated**: listed, cannot be turned on, and businesses with one on keep it;
  - **Retired**: not listed, and off for everyone.

  The Listed switch stays as it was: an unlisted app, or a Draft or Retired one, is out of the Apps Center and the website. Apps unlisted before became Drafts.
- **Where an app is offered:** the countries it names (none: all), by the business's country from its profile. A business elsewhere sees "Not in your country"; one with it on keeps it.
- **Apps it cannot be on beside:** app families, either way round: neither turns on while the other is on.
- **Its release version**, shown beside its plan in the Apps Center.
- **The Apps Center** has four new states, none turned on: Unavailable for now, Deprecated, Not in your country, and "Not with another app" (naming it). An edition on stays on, with a note when it is deprecated or paused. A family with none to turn on is "Not Available" in the status filter. Turning on is refused the same way, each with its reason.
- **Retire**, on an app's admin page, is a critical action (password and reason). It turns the app off for every business with it on, keeping their records as turning it off does: each logged, its sidebar rebuilt. The app is then Retired and unlisted. The form offers Retired only to an app already retired.
- **Roles** (migration 000140): Product Administrators retire apps.
- Left for later: the rest of section 10's record (required permissions, set-up requirements, dashboard parts, quick actions, document types, report definitions and additional charges, which the code now defines), and section 11's Lite-to-Full rules beyond what Doc 13 built.
- Checked in the browser as the seeded admin and `user@izsuite.io`, with three dev apps changed for the check: Inventory's admin page showed the release, countries and conflicts fields, Temporarily Unavailable and Retire This App. The app's Apps Center then showed Inventory "Unavailable for now" with v1.0.0, CRM "Not in your country", and Expenses Lite still on with its deprecation note. The three apps, workspace 1's setup, the sign-in's token and its activity were then put back.

**Phase E3: packages in depth** (section 8) was split on starting it (2026-11-14): E3a, a plan's details and where it is offered; E3b, its changes with an effective date and version history. A plan's fixed internal code (its key) was already shown on its admin page.

**Phase E3a: plan details** (section 8) is built, with these defaults:

- **A plan's levels:** a reporting level (Basic, Standard or Advanced) and a support level (E-mail, Priority or Dedicated), either left empty to show none. Migration 000141 gave Billing Starter Basic and E-mail, Standard Standard and E-mail, and Premium Advanced and Priority. They are shown with the plan ("Advanced reports", "Priority support") on the Pricing page and as a business compares plans. Nothing in the app is limited by them yet.
- **Its cycles:** monthly and yearly, each offered or not, one at least. A cycle not offered has no price on the plans, which read "Monthly only" or "Yearly only". Subscribing or moving to it on that cycle is refused. Subscribers already paying on it renew on it.
- **Where it is offered:** the billing countries it names (none: all), by the country in the business's billing details. Before a business gives them, it sees every plan; at subscribing it must give them.
  - A business sees the plans offered where it is billed, and its own.
  - Subscribing or moving to a plan offered elsewhere is refused. One already on it keeps it and renews on it.
  - The Pricing page shows every plan, naming the countries of one offered in some only.
- **Billing Starter**, the plan every business starts on and falls back to, is offered everywhere and has no cycles to set.
- **The admin:** the plan's page has Reports, Support, Paid (Monthly, Yearly) and Offered in; Packages shows "Monthly only" and "In 2 countries" under Offered.
- The rule lives in `PlanAvailability`, which subscribing, the quote and plan changes ask.
- Every change is in the audit log, setting by setting.
- Checked in the browser as the seeded admin and `user@izsuite.io`: Premium's page showed the new fields; Yearly unticked and India chosen saved, and Packages showed "Monthly only" and "In 1 country". The Pricing page then showed Premium "Offered in India", "Monthly only", "Advanced reports" and "Priority support". In the app, Compare Plans on Yearly showed Premium "Monthly only", and with billing details in Bangladesh it left Premium out. Premium, workspace 1's setup, its billing details, the sign-in's token, its activity and the audit entry were then put back.

**Phase E3b: plan versions** (section 8) is built, with these defaults:

- **A plan's terms** are its limits (users, locations, businesses per account, storage), branding, reporting and support levels, trial and grace. Its name, description, currency, cycles, countries and whether it is offered are not versioned: they change at once, as before.
- **A change to its terms is its next version**, from the day chosen beside Save ("Changed terms start", today unless changed):
  - today: it starts at once;
  - a later day: it waits, and starts within the hour after that day begins (UTC), by `plans:advance`, run hourly.

  One version waits at a time: the terms cannot change again until it starts or is removed. Migration 000142 made each plan's terms as they stood its version 1.
- **As a version starts:**
  - the plan takes its terms: the Pricing page, new subscribers and the plans compared show them;
  - a business subscribed to the plan (on a trial, paid or in grace) stays on the version it was on. Existing subscribers are not moved to new limits silently;
  - every other business on the plan, Billing Starter's among them, has its terms as they stand.
- **A business held to a version** keeps its limits, storage, branding and grace (`Workspace::terms()`). They count everywhere: adding users and locations, storage, documents' "Powered by izSuite", the usage notices, Settings' Usage and Limits, its own plan among the plans compared, and Admin > Businesses, Usage and the Overview. Moving to another plan gives it that plan's terms as they stand; renewing, or a cycle change on the same plan, keeps its version.
- **Admin > Packages > a plan > Versions** lists its versions, newest first: Waiting, Current or Earlier, the day each started and who made it, its terms with what changed from the one before in bold, and how many businesses are on it. Earlier versions stay listed for the businesses on them. A waiting version can be removed; a started one cannot.
- A business's admin page shows "Plan terms: Version 1, from …" while it is held to an earlier version.
- **Roles** (migration 000142): Billing Administrators remove waiting versions.
- Left for later: moving a plan's subscribers to its current version, with notice, as Apply to Subscribers does for prices; and versions of which apps a plan includes (Package Access changes count at once, as Doc 13 set).
- Checked in the browser as the seeded admin: Standard's users set to 10 from 20 Nov made version 2 Waiting, with "10 users" in bold, and the plan unchanged; the date field then said it waits. Removing it through its dialog took it away, and both were audited. Set from today, version 2 started at once: Current, version 1 Earlier, the plan at 10 users, the audit entry with the change. The check's version, Standard's users and the audit entries were then put back.

**Phase F: communications, announcements and countries** (sections 19, 20 and 21) was split on starting it (2026-11-14), the self-contained part first: F1, announcements; F2, e-mail templates; F3, countries, itself split on starting it into F3a, the countries and their defaults, and F3b, payment methods by country.

**Phase F1: announcements** (section 21) is built, with these defaults:

- **An announcement** has a title, a message (up to 1,000 characters), a priority (Information, Important or Emergency), a link (a web address, or a page of the app such as `/settings?tab=subscription`, with its words: "Learn more" when none are given), whether it can be dismissed, and its days, from and until (UTC, either open).
- **Its audience**, one of: all businesses; those on chosen plans; in chosen countries (from the business's profile); with one of the chosen apps on (either edition); the businesses named by BW- ID; or izSuite's admins only.
- **Businesses see it** as a banner above every page of their workspace (not the setup wizard), loaded as the business opens and when another is picked: blue, amber or red by priority, the most urgent first, then the newest. A suspended or restricted business still gets it.
- **Admins-only ones** show above every page of the admin panel.
- **Dismissing** puts it away for that person only; nothing goes in the business's activity log. An emergency or legal notice is left not dismissible: it stays until it ends or is deleted.
- **Admin > Announcements**, under Management after Contact Messages, lists them with their priority, state (Scheduled, Live or Ended), audience, days and how many people dismissed them. Add, Edit and Delete; each in the audit log.
- **Roles** (migration 000143): Product and Support Administrators run announcements; Read-Only Administrators see the list. Every admin may dismiss one for themselves.
- Left for later: e-mailing an announcement, and showing it on the public website.
- Checked in the browser as the seeded admin and `user@izsuite.io`: Add Announcement made an Important one for all businesses, linking to Support; an admins-only note showed above the admin pages and went when dismissed. The app's dashboard showed the amber banner with its Contact Support link, and not the admins' note; dismissing it took it away and recorded it. The check found the list's delete dialog broken by quotes in its message, now fixed. The announcements, dismissals, sign-in token, activity, audit entries and workspace 1's setup were then put back.

**Phase F2: e-mail templates** (section 20) is built, with these defaults:

- **An e-mail keeps the words built into the code** until an active template is saved for it. A template is per e-mail and language: a subject (also the e-mail's title), a message (a blank line starts a paragraph), the button's words for an e-mail with a link, and a status: Active (sent in these words) or Draft (kept, previewed and tested, not sent).
- **Variables** are written in braces, such as `{plan}`; each e-mail fills in its own, and the page lists them with a sample each.
- **The language** is the e-mail's own (the mailable's locale, else the app's), falling back to English's template, then to the built-in words. Today every e-mail goes out in English, so English templates are the ones sent.
- **The e-mails written so far** (11): verifying an e-mail address, the account active, the password reset code, the password changed, an invitation to a business, and the subscription notices: trial ending, grace, plan ended, failed payment, refund and price change. Security and billing ones are marked "Always sent: nobody in a business can turn it off" (none can be turned off by a template either).
- **Admin > E-mail Templates**, under System after Languages, lists them by group (Account, Security, Billing) with each language's state: Built-in, or Active or Draft with its version. Each page has the form, the variables, Preview (the saved version with the samples, in a new tab), Send Me a Test (to the admin's own address) and the version history, where an earlier version can be restored as the newest. Every save, restore and test is in the audit log.
- **Roles** (migration 000144): Product Administrators write them; Support Administrators see and preview them; Read-Only Administrators see the list.
- Left for later: the e-mails whose words are built by the code that sends them (business status notices, usage alerts, billing documents, the admin's new sign-in), other channels than e-mail, and e-mailing announcements.
- Checked in the browser as the seeded admin: the list showed the three groups and four languages, all Built-in; the Trial ending page took a subject and message with variables and saved them as Draft version 1, with its history; Preview showed "Only 3 days left of your Standard trial" with the samples and the See your subscription button. The test send was left to the tests. The check's template and audit entry were then removed.

**Phase F3a: countries** (section 19) is built, with these defaults:

- **The countries are a table now** (migration 000145), started from the list that was in the code: 250 countries and territories (ISO 3166, with Kosovo), each with its name, calling code and the defaults a new business there takes. Each country's version 1 is what the code had, so nothing changed for anyone on the day.
- **A country's defaults** are:
  - its currency: the base currency the setup offers, while it is on under Currencies;
  - its time zone;
  - its regional settings (date, time and number formats, first day of the week, paper, start of the fiscal year): what Settings recommends;
  - what its addresses call the region and the postal code;
  - its address format.
- **The address format** has a line for each line of the address, naming its parts: `{line1}`, `{line2}`, `{city}`, `{region}`, `{postal}`, `{country}`. A part left empty takes its comma with it, and a line with no parts filled in goes. From now, documents print the business's address and the customer's in each address's own country's format. Every country starts with each part on its own line, as before. The exceptions are the United States ("Springfield, IL 62704"), Canada and Australia ("Ottawa ON K1A 0B1").
- **The time zone:** in the setup, a business not saved yet that picks its country takes the country's time zone. It keeps the one already set (the owner's or the browser's) when that is one of the country's own. A country with several zones starts with its capital's (New York for the United States). Bouvet Island and Heard Island have none.
- **Effective dates:**
  - A change to a country's defaults is its next version, from today or a later day. One version waits at a time, and it can be removed. `countries:advance` starts waiting versions hourly.
  - Businesses already set up keep their own settings, and documents already issued keep what they printed.
  - The name and calling code change at once.
- **Admin > Countries**, under System before Languages, lists the countries with their currency, time zone, date and number formats, and how many businesses are based in each. It searches by name or code. A country's page has its form, a sample address in its format, and its versions with what changed in bold. Every change is in the audit log.
- **Roles** (migration 000145): Product Administrators edit countries; Read-Only Administrators see the list.
- **Left for later:**
  - regions within a country, tax templates, document terminology and mandatory legal rules, which wait for country localization;
  - adding or removing a country (the list is ISO's);
  - turning a country off for new businesses.
- **Checked in the browser** as the seeded admin and `user@izsuite.io`:
  - The list found Bangladesh with BDT, Asia/Dhaka and the lakh. Its page showed its defaults, the sample address and version 1.
  - A new date format from today made version 2 Current and version 1 Earlier.
  - Letter paper from 20 Nov waited as version 3 and locked the date field with a note. Removing it through its dialog worked.
  - In the setup, picking United Kingdom changed the time zone from Asia/Dhaka to Europe/London and the address labels to County and Postcode. United States gave America/New_York. Nothing was saved there.
  - The check found the sample address pushing the format field down; it now sits beside it.
  - Bangladesh's defaults, the check's versions, sign-ins, token, activity and audit entries were then put back.

**Phase F3b: payment methods by country** (sections 15 and 19) is built, with these defaults:

- **Each of izSuite's ways to pay** (card through Stripe, Bank Transfer and other offline methods) is offered in chosen billing countries, as apps and plans are. With none chosen it is offered in every country, as each was before (migration 000146).
- **A business billed elsewhere:**
  - An offline method's instructions stay off its new invoices. Invoices already issued keep the instructions they printed.
  - Card payments are not offered: no Pay by Card, and no saving or paying with a card ("Card payments are not offered where your business is billed").
  - Its saved card is not charged.
- **A business without billing details yet** is offered every method.
- **Admin > Payment Methods** has "Offered in" on an offline method's form and on Stripe's page, and the list shows each method's countries. A country's page says which ways to pay a business billed there is offered.
- **Checked in the browser** as the seeded admin: Stripe's page took India and Bangladesh while switched off. The list then showed "Offered in Bangladesh, India", and Bangladesh's page listed only Bank Transfer (Stripe being off). Stripe's row and the audit entry were then put back. Paying and invoices were left to the tests.

**Phase G: privacy and compliance** (section 24) was split on starting it (2026-11-14): G1, privacy requests with the data export; G2, legal holds; G3, consent records and retention rules.

**Phase G1: privacy requests** (section 24) is built, with these defaults:

- **A privacy request** is a data export, an account closure or a data correction. It is logged as it came: who asked and their e-mail, how it came (e-mail, a support case, the contact form, phone, other), the day, what they asked, and the account (by e-mail) and business (by BW- ID) it is about. A data export needs the business, with the account its owner or a member; a closure needs the account; a correction needs either. It is due 30 days after it came, and shows Overdue after that.
- **Nothing is done until the person asking is checked:** the admin writes how (Received, then Identity checked). Then it is carried out (Completed, with what was done) or refused (Rejected, with why). Every step is in the audit log, with how the person was checked.
- **A data export** is every record of the business as CSV files in a ZIP (33 files: members, contacts, items, sales, purchases, expenses, payments, accounts, taxes, comments, the list of its files, its izSuite invoices, its activity log and more), with a README. Passwords, keys and links are left out, and so are the stored files themselves, which `files.csv` lists. It is built in the background and its link e-mailed to the person asking; the link works for 7 days (the `privacy_export_days` setting), then `privacy:purge-exports` deletes the file. Each download by the link is counted, and each by an admin is in the audit log.
- **An account closure** closes the account (it can no longer sign in, and its sessions end) and every business it owns. Their records are kept: closing never deletes. A business still subscribed has its subscription cancelled first, in Admin > Subscriptions. A closed account cannot be locked, and the users list shows and filters it as Closed.
- **A data correction** records what was corrected; ticked, that goes to the person asking. So does a rejection's reason.
- **Admin > Privacy Requests**, under a new Compliance section, lists them (the open ones first, by due day) with search by name, e-mail or PR- number, and filters by type and status.
- **Roles** (migration 000147): Compliance and Security Administrators carry them out, the export included; Support Administrators log them and check who is asking; Read-Only Administrators see the list.
- **Left for later:** a person's own data across businesses (an export is per business), the stored files in an export, and security investigations, which incidents and the audit log cover for now.
- **Checked in the browser** as the seeded admin:
  - Logged a data export for `user@izsuite.io` and BW-000001, recorded the identity check, and prepared the export.
  - The dev queue worker built it: 33 CSV files and the README, `members.csv` without secrets. The e-mailed link downloaded the ZIP; a tampered link was refused, and the download was counted.
  - The check found the request marked Completed, though its e-mail had failed: the worker had started before the download link's route existed. Now the request says it was sent only once the e-mail is queued, and a failure removes the file and puts it back to Identity checked. A test covers it.
  - The request, its file and the audit entries were then removed.

**Phase G2: legal holds** (section 24) is built, with these defaults:

- **A legal hold** is placed on a business by its BW- ID, with a reference (a case number, say) and the reason, which only izSuite sees. A business may have several; it is held while any is in force.
- **While held**, the owner cannot delete the business, and nobody in it can delete its records: contacts, items and their images and files, sales and purchase documents, expenses, their payments' and their own attachments and comments, payment accounts, transfers, locations and exchange rates. The app answers "izSuite is keeping this business's records for now, so they cannot be deleted. Contact izSuite support to know more." without saying why.
- **What a hold leaves alone:** changing records, voiding or cancelling documents (which keep them), closing the business or its owner's account (which keep everything), and setup and access (members, roles, taxes, terms, categories), so a departing member can still be removed.
- **Releasing a hold** lets deletions happen again, so it is a critical action: the admin's password and a reason. A released hold stays listed, with who released it and why, even after the business is deleted.
- **Admin > Legal Holds**, under Compliance, lists them (those in force first) with search by business, BW- ID or reference. A business's admin page says when it is held.
- **Roles** (migration 000148): Compliance and Security Administrators place and release holds; Read-Only Administrators see the list.
- **Left for later:** holds on one account or on chosen records, and a hold stopping a retention rule, as none deletes yet.
- **Checked in the browser** as the seeded admin: a hold on BW-000001 with a reference showed In force, and the business's page said so. Releasing it with the password and a reason marked it Released, with the reason. The hold and its audit entries were then removed. Refused deletions in the app were left to the tests.

**Phase G3: consent records and retention rules** (section 24) is built, with these defaults:

- **Consent records** are kept for each account: accepting the terms (at sign-up, or signing up from an invitation), and giving or withdrawing izSuite's product news by e-mail. Each holds when, how (at sign-up, from an invitation, in their profile), the address and browser it came from, and the account's e-mail then. They are written once and never changed. Accounts that had accepted the terms before have that recorded, on its day (migration 000149).
- **Product news by e-mail** is off unless ticked: a checkbox at sign-up, "Send me izSuite's product news by e-mail (optional)", and one in My Profile. Only a change is recorded. izSuite sends no product news yet: this is the consent for when it does.
- **An account's admin page** shows its consent records, newest first, and whether product news is given.
- **Admin > Data Retention**, under Compliance, lists what izSuite keeps, for how long and what removes it, as the code does it today. Each is marked Enforced or Not yet: a business's records and activity log, closed accounts, privacy request exports, privacy, hold and consent records, the admin audit log, service events, imports, the keys that stop a request running twice, admin sessions and failed jobs. Only the enforced ones delete anything. The days an export's link lasts (1 to 90, 7 by default) are set there.
- **Roles** (migration 000149): Compliance and Security Administrators see the rules and set the export's days; Read-Only Administrators see them.
- **Left for later:**
  - retention periods for the rest, which wait for izSuite's retention policy;
  - consent for other purposes (cookies, other e-mails);
  - a product news checkbox on the invitation sign-up form: it records the terms, and product news stays off.
- **Checked in the browser** as the seeded admin and `user@izsuite.io`:
  - Data Retention showed the rules marked Enforced and Not yet.
  - The sign-up page showed the optional checkbox.
  - Ticking product news in My Profile and saving recorded it.
  - The account's admin page listed the record but said "not given": its query left the new column out. It now reads it, and the test checks both states.
  - The record, the choice, the sign-in and its activity were then put back.

**Suggested to leave, unless you say otherwise:**

- A second admin's approval for critical actions, which section 25 asks for "where configured". The password and a reason come first; approval can follow once there are several admins.
- Configuring how Lite and full apps relate (section 11) in the admin. It stays in the code, since a wrong change could corrupt data, which the section itself forbids.
- API, automation, AI and SMS usage, their charges, setup fees and included usage. As in Doc 13, they wait for those services.
- Health checks for SMS delivery and exchange-rate providers, which don't exist yet: businesses enter their own rates.
- Prices by customer type, since there are no customer types yet.
- IP allow-listing and a separate domain for the admin panel. These are server decisions.
- Deleting a business for good. Closed keeps its data.

### 4. Doc 16: Sales (done 2026-11-15)

The gap check was done on 2026-11-14. Doc 16 is the full Sales app, which replaces Sales Lite (Doc 08) when a business turns it on, keeping and extending its records.

**Already built (Sales Lite), which Sales keeps:**

- Quotations, proforma invoices, invoices and credit notes in one `sales_documents` table, each with its own numbering (QUO-, PRO-, INV-, CN-) and statuses.
  - A sent quotation or proforma keeps its earlier versions as revisions.
  - Each has a PDF, e-mail, reminders, a share link (view and PDF, revocable, no expiry), attachments, comments and e-mail history.
- Conversions:
  - A quotation becomes a proforma or an invoice, and a proforma an invoice; `source_id` links them. Lines are copied without a link to the line they came from.
  - A business user marks a quotation accepted or rejected; proformas have no acceptance.
- Credit notes come from an issued invoice, within each line's credited limits, and are applied to their own invoice only. Refunds are recorded against a credit note.
- Payments received are recorded only from an issued invoice:
  - partial and several, with withholding and other currencies;
  - reversed with a reason, never deleted;
  - receipts (RCT-) with a PDF and e-mail.
  - Paying more than is owed is refused.
- Line and whole-document discounts (switches in Settings → Sales), taxes, payment terms, and customer and sales references.
- The dashboard shows net sales, amount received, outstanding and overdue invoices, a chart and the latest documents. Reports: Sales Summary, Sales Trend, Top-Selling Items, Top Customers, Outstanding Invoices, Payments Received, Credit Note Details, the tax reports and the contact statement.
- Permissions per document kind (view, create, edit, issue, send, cancel, void, delete), for payments (view, record, reverse), refunds, values and settings.
- The Apps Center already lists the full Sales edition as available, from Standard up. Turning it on switches the family to Full; today that only adds a Sales Orders menu item, which opens a placeholder page.

**Missing (what Doc 16 adds):**

- 16.2 to 16.6:
  - New Sale offering each document;
  - the Sales Overview;
  - billing and shipping addresses on documents, salesperson, sales team, expected delivery date, shipping method and terms, and price list;
  - advanced records kept read-only after a downgrade to Lite.
- 16.7 to 16.8: customers accepting or rejecting through the link, with how recorded; converting to sales orders.
- 16.9 to 16.11: sales orders, which do not exist:
  - order, delivery and invoice statuses;
  - each line's ordered, delivered, invoiced, returned and credited quantities;
  - confirming locks the terms, with amendments needing a reason;
  - invoice policies and partial invoicing.
- 16.10 and 16.13: deliveries and backorders. Stock is not kept anywhere yet.
- 16.14 to 16.16:
  - deposit invoices and their deductions;
  - customer advances and their allocation;
  - an overpayment kept as an advance;
  - online payment.
- 16.17 to 16.18: returns with their resolutions; a credit note's remaining credit applied to the customer's other invoices.
- 16.19 to 16.21: price lists and their rules, discount limits by role with overrides, and customer credit limits.
- 16.22: what a customer does through the link (accept, reject, comment, pay), and links that expire.
- 16.23 to 16.27: the new settings, the dashboard's sales figures, about 20 more reports and the new permissions.

**Waiting for other apps**, as Doc 16 itself says:

- Inventory (not written): stock reserved, moved on delivery and return, warehouses, batches and serials, and the direct invoice's stock method.
- Accounting: postings, and an advance's liability.
- CRM: opportunities. CRM extends the sales teams Phase A makes.
- POS and eCommerce: their orders flowing in.
- Customer Subscriptions: recurring invoices.
- Projects and Field Service.
- Approvals and eSignatures.
- A payment gateway for a business's own customers: paying an invoice online.

**Phases, with suggested defaults.** Saying "Start Phase A" accepts them.

- **Phase A: Sales takes over from Sales Lite.**
  - The sidebar in Doc 16's order, each item added by the phase that builds it, with a Customers link (Contacts filtered to customers).
  - New Sale as a menu: Quotation, Proforma Invoice, Invoice, and Sales Order once it exists.
  - The Sales Overview, its figures and filters. Quotation and order values are labelled as not sales.
  - On documents: billing and shipping address (from the customer, changeable), salesperson (a member of the business), sales team, expected delivery date, shipping method and shipping terms. The PDF prints them where set.
  - Sales teams kept simple in Settings → Sales: name, members, active or not.
  - Back on Lite after a downgrade, the full-only pages hide and their records stay, read-only.
- **Phase B: sales orders.**
  - A new kind in `sales_documents` (SO-), converted from quotations and proformas or made directly.
  - Draft, Confirmed, On Hold, Completed or Cancelled, with delivery and invoice statuses beside.
  - Each line linked to the line it came from (`source_line_id` on every conversion), keeping its ordered, delivered, invoiced, returned and credited quantities.
  - Confirming locks the terms; an amendment needs the permission and a reason.
  - Invoices from an order: ordered or delivered quantities (Settings → Sales, and per item), several invoices, never past what is left without the permission and a reason. Direct invoices stay.
  - Cancelling an order cancels nothing after it.
- **Phase C: deliveries.**
  - A delivery (DN-) from a confirmed order, in its own table: date, location, lines and quantities, address, carrier, tracking number, vehicle or driver, recipient, notes, the day it arrived, and proof as an attachment.
  - Draft, Ready, Delivered or Cancelled. A partial delivery leaves a backorder.
  - Services are marked fulfilled, and digital products delivered by hand.
  - No stock moves until Inventory exists. Each has a delivery note PDF.
- **Phase D: deposits, advances and credit.**
  - A deposit invoice from an order, as an amount or a percentage, deducted from the final invoice.
  - Customer advances recorded from the customer or an order, then allocated to issued invoices, with their history.
  - An overpayment becomes an advance once confirmed (a setting).
  - A credit note's remaining credit applied to the customer's other outstanding invoices.
- **Phase E: returns.**
  - A return (RET-) from a delivery, order or invoice, in its own table, never above what was delivered.
  - A reason and condition, and a resolution: refund, credit note, replacement, repair or none. Draft, Submitted, Authorised, Received, Rejected, Completed or Cancelled.
  - A credit note made from it, with or without goods coming back. The item's warranty is kept.
- **Phase F: price lists and discount limits.**
  - Price lists:
    - their currency, days, customers (all or chosen: there are no customer groups yet), locations and priority;
    - rules by item, category, brand and minimum quantity: a fixed price, a percentage off, or a markup or markdown.
  - One price is chosen per line, and the list and rule are recorded. Issued and confirmed documents keep their prices. Changing a price by hand needs a permission.
  - A largest discount per role, past which a permission and a reason are needed.
- **Phase G: credit control and the customer's link.**
  - On a customer: a credit limit, credit sales allowed or not, warn or block, and a note.
  - Exposure counts outstanding and overdue invoices, unapplied credit, and confirmed orders not yet invoiced (a setting), in the base currency. Going over warns or blocks, and an override needs the permission and a reason.
  - Through the link, a customer views a quotation, proforma, order or invoice, accepts or rejects a quotation (recorded with when and from where) and leaves a comment.
  - Links can expire after the days set. Paying online waits for a gateway.
- **Phase H: settings, dashboard and reports.**
  - The rest of Settings → Sales: default workflow, invoice policy, deposits, backorders, and the discount, price and credit rules. Numbering and templates for the new documents go in the shared settings.
  - The dashboard's sales figures from 16.25.
  - The reports in 16.26: by item, category, brand, location, salesperson and team; quotation conversion; order backlog; deliveries; returns; discounts; price list use; credit exposure; and estimated margin from the items' costs.
  - Each phase adds the permissions it needs.

**Phase A: Sales takes over from Sales Lite** (sections 16.2 to 16.6) is built, with your defaults:

- **The sidebar** follows Doc 16's order once Sales is on: Overview, Quotations, Proforma Invoices, Sales Orders, Invoices, Credit Notes, Payments Received, then Customers, which opens Contacts filtered to customers and is the entry highlighted there. Deliveries, Returns and Price Lists take their places as their phases add them. Sales Lite keeps Doc 08's order, Invoices first.
- **New Sale** is one menu on the sales pages and the Overview: Quotation, Proforma Invoice, Invoice on Sales, and Invoice first on Sales Lite. Each shows only to those who may create it.
- **The Sales Overview** (`/sales/overview`, with View Sales Values):
  - first, apart and labelled "Not sales: what was offered", the quotations sent in the period and those accepted or converted;
  - then sales from issued invoices without tax, the amount received (payments less refunds) and credit notes;
  - and outstanding and overdue invoices as of the period's end, or today.
  - It filters by period, location (with more than one), customer, salesperson, team and currency (with more than one). Amounts are in the base currency unless a currency is chosen.
- **A document's Sales and Delivery** (the full app's form only):
  - a salesperson (the owner or an active member), a sales team (one switched on), the expected delivery, the shipping method and the shipping terms (Incoterms offered);
  - billing and shipping addresses: the customer's, unless "Bill to another address" or "Ship to another address" is ticked, which gives the document its own.
  - They show on the document's page, carry over when it is converted, and a credit note keeps its invoice's salesperson and team.
  - The PDF lists the salesperson and delivery details. Once a document says how or when it ships, the PDF also prints Ship To under the customer: its own shipping address, else the customer's. The sales team stays off the PDF.
- **Sales teams** are in Settings → Sales on the full app: a name, members (the owner and active members) and switched on or off. A team a document names is switched off rather than deleted.
- **Back on Sales Lite**, the Overview, Customers and the teams card go, and the form leaves the full app's fields as they are. A document keeps them and still shows them. Opening the Overview offers the full app in the Apps Center.
- **Left for later:** price lists on documents (Phase F), Sales Order in New Sale (Phase B), and the dashboard's and a contact's New Sale, which still open an invoice as Docs 02 and 03 say.
- **Checked in the browser** as `user@izsuite.io`, with the business switched to full Sales by hand (its plan is Billing Starter), and as a throwaway member who may only view, create and edit quotations:
  - The sidebar, New Sale and the Overview showed as above. A team was added, switched off and on.
  - A quotation with a salesperson, team, delivery details and its own shipping address saved, edited and printed Ship To on its PDF.
  - Sent and accepted, it counted as sent but not as accepted on the Overview: the status is cast to its enum, so comparing it with plain strings matched nothing. It now compares enums, and the test accepts a quotation too.
  - The Overview's filters sat too far from its figures: their margins and the page's spacing added up. Fixed.
  - The member saw only Quotations and Sales Orders, New Sale with Quotation alone, the salesperson list, and no Overview.
  - On Sales Lite again, the menu, New Sale and the form went back to Doc 08's, and the quotation kept its details.
  - The business, its Sales row, the member, the team, the customer, the quotation, the sign-ins and the activity were then put back.

**Phase B: sales orders** was split on starting it (2026-11-14): B1, sales orders themselves; B2, invoicing from them. Both are built, with these defaults.

**Phase B1: sales orders** (sections 16.5 and 16.9) is built:

- **A sales order** is a new kind of sales document (SO-), with its own numbering and template in Settings on full Sales only. It is made from New Sale (Quotation, Proforma Invoice, Sales Order, Invoice), or from a quotation or proforma ("Convert to Sales Order"), which is then marked converted.
- **Every conversion now links each new line to the line it came from** (`source_line_id`), and an edit keeps those links.
- **Its form** is a quotation's, with payment terms for its invoices (the customer's to start) and a "Customer PO Reference"; it has no expiry.
- **Its statuses:** Draft; Confirmed, numbered then with the business and customer kept as they are; On Hold and back; Completed; or Cancelled with a reason. A draft is deleted instead. Beside it, a delivery status (No Delivery Needed when every line is a service, else Not Delivered until deliveries come in Phase C) and an invoice status.
- **Confirming locks it.** A confirmed or held order changes only by Amend, with "Edit Confirmed Orders" and a reason: the version before is kept as a revision with that reason, which Earlier Versions shows. Its customer and currency stay, and a line keeps at least what was delivered or invoiced of it.
- **Also:** Duplicate (a new draft dated today), Create Proforma Invoice (a linked draft; the order stays as it is), the PDF (Bill To, its payment terms), e-mail and the secure link. Its page shows each line's ordered, delivered, invoiced, returned and credited quantities, and its History what was made from it.
- **Permissions**, on full Sales only: View, Create Draft, Edit Draft, Confirm (also Hold, Resume and Complete), Edit Confirmed Orders, Send or Share, Cancel, Delete Draft, and (B2) Invoice Beyond the Order. Its amounts need View Sales Values. The Sales User template includes all but Edit Confirmed Orders and Invoice Beyond the Order, for a business set up with full Sales on.
- **The Overview** adds Confirmed Order Value, Orders to Deliver and Orders to Invoice, with the quotations under "Quotations and Orders - Not sales".
- **Back on Sales Lite**, the orders list, its menu entry and its settings hide, and an order opened from a link (its invoice, the Documents page) is read-only.

**Phase B2: invoicing from orders** (sections 16.11 and 16.12) is built:

- **Settings → Sales** says whether orders invoice their ordered quantities (the default) or their delivered ones; an item may have its own. A service always invoices what was ordered.
- **A confirmed order's Create Invoice** lists each line with what is left: ordered (or delivered), less what issued invoices billed and what draft invoices hold. It makes a draft invoice of what is chosen, linked to the order and its lines, with its customer, currency, terms, addresses and people. An order can have several invoices; the invoice's PDF names the order.
- **Invoicing beyond what is left** needs "Invoice Beyond the Order" and a reason, which the invoice keeps and its form shows. Issuing checks again: an invoice raised in its form beyond the order, without a reason, is refused.
- **The order's lines** count what issued invoices billed and their issued credit notes credited, worked out again whenever one is issued or voided, and its invoice status follows: Not Invoiced, Partially Invoiced or Invoiced.
- **An order with draft or issued invoices is not cancelled**, as cancelling leaves them as they are: they are voided or deleted first. Direct invoices stay. An order is never converted to an invoice whole.
- **Left for later:** completing an order by itself once delivered and invoiced (Complete is by hand), and the delivered policy's quantities, which wait for deliveries (Phase C).
- **Checked in the browser** as `user@izsuite.io` with full Sales switched on by hand, and as a throwaway member who handles orders and makes invoices:
  - A draft order made through the API without a location was refused on confirming, "Choose the location first", and kept as a draft. With one, Confirm Order numbered it SO-00001: Confirmed, Not Delivered, Not Invoiced, with Create Invoice as its main button.
  - Create Invoice of 2 of 4 chairs made a draft invoice; issued as INV-00001 ("Made From Sales Order SO-00001"), the order read Partially Invoiced, 2 left.
  - 3 more asked for a reason and then made a draft; its form showed the reason and kept the lines' links on saving.
  - Amend to 5 chairs, with a reason, made revision 1, and Earlier Versions showed the reason. Hold, Resume, the Overview's order figures, the list's statuses, Settings → Sales and the item form's policy all showed as above.
  - Cancel was refused while the order had invoices. The order's History did not list them: it now does ("Made From It").
  - The member saw Sales Orders and Invoices, and on the order Create Invoice, Hold, Complete and Duplicate only; invoicing beyond was refused for want of the permission.
  - Back on Sales Lite, the order opened with Preview only, and the orders list sent to the Apps Center.
  - The business, its Sales row, the member, the customer, the items, the documents, the sign-ins and the activity were then put back.

**Phase C: deliveries** (section 16.10) is built, with your defaults:

- **A delivery note** (DN-, its own numbering in Settings on full Sales; drawn with its order's template) belongs to one confirmed sales order, in its own table (`sales_deliveries`, `sales_delivery_lines`).
- **Create Delivery** on a confirmed order lists each line with what is left: ordered, less delivered, less what other open deliveries hold. It makes a draft of what is chosen - never more, and no permission lets it go beyond.
- **A draft or ready delivery changes:** its date, location, its own address (else the order's), carrier, tracking number, vehicle or driver, recipient, notes and quantities.
- **Its statuses:** Draft; Ready, numbered then (a draft delivered straight away is numbered on delivering); Delivered, with the day it arrived and who received it; or Cancelled with a reason from Ready. A draft is deleted instead. Delivered and cancelled are final: what comes back is a return (Phase E).
- **Marking it delivered** counts its quantities on the order: each line's delivered quantity and the order's delivery status (Not Delivered, Partially Delivered, Delivered; a service needs none) follow, worked out again from the delivered deliveries. When the order still has something left, Mark Delivered offers a backorder, ticked: a new draft of the rest.
- **Services and digital products** go on deliveries like goods, marked fulfilled. No stock moves: there is no Inventory.
- **The order** shows each line's To Deliver and its deliveries, and the invoice policy's delivered quantities now have something to count. An order with deliveries not cancelled is not cancelled - one delivered is completed instead - and keeps the lines on them.
- **The delivery note PDF** shows the customer and Ship To, the order, carrier, tracking number, vehicle or driver and the day it arrived, the lines without prices, and who received it. A delivery has its proof of delivery as files.
- **Deliveries** is a page of its own (full Sales, after Sales Orders): searched by number, order, customer or tracking number, and filtered by status.
- **Permissions**, on full Sales only: View, Create Draft, Edit Draft, Mark Ready or Delivered, Cancel and Delete Draft. A delivery shows no values.
- **Left for later:** backorder settings, warehouses and stock (Inventory), and a delivery's comments and e-mail.
- **Checked in the browser** as `user@izsuite.io` with full Sales switched on by hand, and as a throwaway member who may make and edit deliveries:
  - Create Delivery of 3 of 4 chairs made a draft; its form came filled in and saved the carrier, tracking number and vehicle.
  - Its Proof of Delivery failed: the delivery model lacked the files trait. It has it now, and a test lists a delivery's files.
  - Mark Ready numbered it DN-00001; Mark Delivered, with the backorder ticked, recorded who received it and made a draft of the rest. The order read Partially Delivered and listed both; the delivery note's text read as above.
  - The member saw Sales Orders and Deliveries, and on a draft only Preview and its form.
  - On Sales Lite, Deliveries sent to the Apps Center and left the menu.
  - The business, its Sales row, the member, the customer, the items, the order, the deliveries, the sign-ins and the activity were then put back.

**Phase D: deposits, advances and credit** was split on starting it (2026-11-14): D1, customer advances and applying credit; D2, deposit invoices.

**Phase D1: customer advances and credit** (sections 16.15, 16.16 and 16.18) is built, with your defaults:

- **A customer advance** (ADV-, its own numbering in Settings on full Sales) is money a customer paid ahead: from their contact page (Record Advance, in a currency of the business) or from a sales order still open (in its currency, at its location). It needs the customer, date, an active account that takes customer payments, the currency and the amount; in another currency than the account's, what reached the account too. It goes into the account's history as a Customer Advance, but is no sale.
- **Settings → Sales** has Customer Advances (on to start, full Sales only). Off, none is recorded and no overpayment is kept.
- **An overpayment** is refused as before, unless the payment dialog's "Keep the extra as an advance" is ticked (full Sales, advances on, Record Advance permission): the payment then takes what was due, and the rest becomes the customer's advance, named on the invoice.
- **Apply Credit** on an issued invoice with something to pay applies one of the customer's advances or other credit notes with credit left, in the invoice's currency, never more than either has. A credit note's own invoice takes its credit by itself, as before; its credit left can now go to the customer's other invoices too (Apply to Invoice on the credit note), and an advance's from its own page.
- **Credit applied** shows on the invoice's sheet (Credit Applied, between the total and the balance), in its Credit Applied card, and on the credit note or advance. Take Off asks why and puts it back; the row stays in the history, marked taken off. The invoice's payment status counts it like a payment.
- **An advance** is reversed (with a reason) only while nothing of it is applied. An invoice with credit applied is not voided, nor a credit note with credit applied elsewhere.
- **Payments Received** has Payments and Advances: the advances with what is left of each, searched and filtered by Open, Applied or Reversed. An advance has its own page.
- **The customer's statement** lists each advance as money in on its day; applying it moves nothing there. What an invoice owed on a day (the statement's Outstanding, Outstanding Invoices and its ageing) takes off the credit applied to it by then, and a credit note's credit applied elsewhere; Outstanding Invoices' Credit Notes column counts both.
- **Permissions**, on full Sales only: Customer Advances' View, Record Advance, Apply Advances and Credit, and Reverse Advance. Sales users get the first three, Finance users all four.
- **Left for later:** an advance's receipt PDF and e-mail, refunding an advance as money out (it is reversed instead), applying credit across currencies, and the liability an advance is in Accounting.
- **Checked in the browser** as `user@izsuite.io` with full Sales switched on by hand, and as a throwaway member who may only view invoices and advances:
  - Record Advance on the customer's page recorded ADV-00001 of USD 120, listed as Open.
  - Apply Credit on INV-00001 (USD 300) offered it with USD 120 left; 100 of it left USD 200 to pay, Partially Paid, with Credit Applied on the sheet.
  - A payment of 250 was refused with how to keep the extra; ticked, it recorded RCT-00001 of 200 and ADV-00002 of 50, and the invoice read Paid.
  - ADV-00002 went to INV-00002 from its own page; Take Off on that invoice, with a reason, put it back, the row kept as taken off; Reverse then reversed it.
  - Payments Received's Advances listed both; Settings → Sales showed the switch, on.
  - The member saw the advances and the credit applied, but no Record Advance, Apply Credit or Take Off; the API refused them too.
  - On Sales Lite, an advance's page sent to the Apps Center.
  - The business, its Sales row, the member, the customer, the item, the account, the invoices, the payment, the advances, the sign-ins and the activity were then put back.

**Phase D2: deposit invoices** (section 16.14) is built, with your defaults. Phase D is done.

- **Create Deposit Invoice** on a confirmed order makes a draft invoice of a fixed amount or a percentage of the order's total, tax included: one line, "Deposit for Sales Order SO-00001" (and "40% of the order"), without tax and without the order's discount - the final invoice charges the order's tax, until country localization says otherwise. It is linked to the order and marked a deposit; its page, list title and PDF say Deposit Invoice.
- **What the order bills** never passes its total without Invoice Beyond the Order and a reason: a deposit is checked when it is made, and once an order has deposits, issuing any of its invoices checks its deposits and invoices together (a few cents of rounding aside).
- **Payments** are recorded from the issued deposit like any invoice's, and an overpayment can become an advance (Phase D1).
- **The order's next invoices** deduct its issued deposits: Create Invoice adds a negative line per deposit with something left, "Less deposit INV-00001", with the deposit's tax, up to that invoice's total. A deduction takes no share of the document discount. The draft's form may change it, and saving and issuing refuse more than the deposit has left - what drafts hold counts. Only a later invoice of the same order deducts; anywhere else a negative price is refused, as before.
- **A deposit** deducted on an invoice not void is not voided. The order's progress lists its deposits, with what is deducted of each; the deduction lines stay on the invoices.
- **Left for later:** a deposit invoice made from a proforma ("converted to an Invoice or Deposit Invoice", section 16.8), and taxing deposits where localization asks for it.
- **Checked in the browser** as `user@izsuite.io` with full Sales switched on by hand:
  - Create Deposit Invoice on SO-00001 (USD 625) at 40% showed USD 250 and made a draft Deposit Invoice of one line; issued as INV-00001, it was paid in full.
  - The order then listed it under Deposits. Its Create Invoice made a draft of 600 + 25 less "Less deposit INV-00001" 250: USD 375.
  - In the draft's form the deduction showed as -250.00; lowered to -200 the live totals read USD 425, and it saved. Issuing it was refused (the order would bill 675 of 625) - a check added during the browser check; set back to -250, Save and Issue issued INV-00002.
  - The order read Invoiced, its deposit "USD 250 deducted".
  - The business, its Sales row, the customer, the item, the account, the order, the invoices, the payment, the sign-ins and the activity were then put back.

**Phase E: returns** (section 16.17) is built, with your defaults:

- **A return** (RET-, its own numbering in Settings on full Sales) is of a sales order's delivered goods, in its own table (`sales_returns`, `sales_return_lines`): Create Return on a delivered delivery (its lines) or on an order with something delivered (its delivered lines). It never takes more of a line than was delivered, less what other returns hold (rejected and cancelled ones aside); from a delivery, no more than that delivery took.
- **It needs** the date (today to start), the location (the order's), the reason and the lines; the condition (unopened, opened, damaged, defective) and the resolution (refund, credit note, replacement, repair, no financial adjustment) are optional until it is completed. Each line keeps the item's warranty as the order kept it.
- **Its statuses:** Draft (changed or deleted); Submitted (numbered, still changed); Authorised or Rejected (with a reason); Received, when the goods came back; Completed, with its resolution. Submitted and authorised ones can be cancelled with a reason.
- **Received** goods count as the order lines' returned quantities, and the order's delivery status follows (Partially Returned, Returned). A return refunds nothing by itself: a refund or credit note is made from the invoice, as before.
- **Returns** is a page of its own (full Sales, after Invoices), searched by number, order, delivery or customer, and filtered by status. A return has its page, its form while draft or submitted, and its photos and papers as files. The order lists its returns under its progress.
- **Permissions**, on full Sales only: View, Create Draft, Edit Draft (and Submit), Authorise or Reject, Mark Received or Completed, Cancel and Delete Draft. A return shows no values.
- **Left for later:** a return from an invoice - a direct invoice has no deliveries - and a credit note made from the return itself, linked to it; stock back into a warehouse (Inventory); approval stages (Approvals).
- **Checked in the browser** as `user@izsuite.io` with full Sales switched on by hand:
  - On SO-00001 (4 chairs, 3 delivered on DN-00001), the delivery offered Create Return; 4 chairs were refused ("only 3 delivered and not returned yet"), 1 made a draft from DN-00001 with its form.
  - Submit numbered it RET-00001; Authorise, Mark Received and Complete (Credit Note) took it through, each with the buttons its status allows.
  - The order then read Partially Returned, 1 returned, with RET-00001 listed; the Returns page listed it, and Returns sat after Invoices in the menu.
  - The business, its Sales row, the customer, the item, the account, the order, the delivery, the return, the sign-ins and the activity were then put back.

**Phase F: price lists and discount limits** was split on starting it (2026-11-14): F1, price lists and pricing; F2, discount limits by role and price overrides.

**Phase F1: price lists** (section 16.19) is built, with your defaults:

- **A price list** has a name, a currency, a status, the days it holds (open-ended either way), its customers (all, or those chosen - there are no customer groups yet), its locations (all, or those ticked) and a priority (lower first). It is a page of its own (full Sales, after Payments Received), with its form and rules.
- **A rule** prices an item, an item category, a brand or all items, from a minimum quantity, between optional days: a fixed price in the list's currency, a percentage off the item's sale price, or a percentage over its cost. Off and over are worked in the base currency and turned at the document's rate in another.
- **The sales form prices its lines**: a moment after an item is added, or its quantity, the customer, location, currency or date changes, each line of an item takes one price - from the first list that holds, by priority, and in it the most specific rule (item, category, brand, all), then the highest quantity reached. The list's name shows under the price; a line no rule prices keeps its item's price.
- **The line keeps the list and rule** that priced it, through conversions, and later list changes reprice nothing made before; a draft loaded again is not repriced either. A price typed by hand stays, and the line no longer names a list.
- **A list** that priced a line is switched off, not deleted.
- **Permissions**, on full Sales only: Price Lists' View, and Add or Change. Whoever fills in a sales form gets its prices.
- **Left for later:** customer groups, and permission for a price typed by hand (Phase F2).
- **Checked in the browser** as `user@izsuite.io` with full Sales switched on by hand:
  - New Price List made "Browser Wholesale" with one rule, the chair at a fixed USD 120; Price Lists sat after Payments Received in the menu.
  - In a new invoice for the customer, the chair (USD 150 on its own) came in at 120.00 with "Browser Wholesale" under its price, and the saved draft's line kept the list and rule.
  - In its form, 115 typed by hand took the list's name away, and the saved line named no list.
  - The business, its Sales row, the customer, the item, the account, the list, the invoice, the sign-ins and the activity were then put back.

**Phase F2: discount limits and price overrides** (section 16.20) is built, with your defaults. Phase F is done.

- **A role's largest discount** (a percentage; empty for no limit) is set on its page in Settings → Users & Roles, with the full Sales app. The owner and full-access roles have none.
- **Past it** - a line's discount, or the whole document's, worked out as a percentage of what it comes off - saving needs "Discount Beyond the Limit" and a reason, which the document keeps; the form shows the reason field once it is asked for.
- **An item's line costs** its item's price, or what its price list's rule makes it. Another price needs "Change Prices by Hand"; without it the price field of an item's line is read-only. A line that already had its price, one made from another document, and a deposit's deduction are left as they are.
- **Permissions**, on full Sales only: Prices and Discounts' Change Prices by Hand (Sales users get it) and Discount Beyond the Limit. Sales Lite prices and discounts as before.
- **Left for later:** an approval workflow instead of the override (the Approvals app), and promotions' source references (Marketing, Loyalty, POS, eCommerce).
- **Checked in the browser** as a throwaway member whose role gives up to 10% and may not change prices: in a new invoice the chair's price was read-only at 150.00, and a 15% line discount was refused with "Your role gives discounts up to 10.00%. A larger one needs "Discount Beyond the Limit"", under the new reason field. The member, the customer, the item, the account, the sign-in and the activity were then put back.

**Phase G: credit control and the customer's link** was split on starting it (2026-11-15): G1, credit control; G2, the customer's link.

**Phase G1: credit control** (sections 16.21 and 16.27) is built, with these defaults:

- **On a customer** (full Sales; a Credit Control card on their contact page):
  - a credit limit in the base currency (empty for none);
  - whether they may buy on credit at all;
  - what going over does, Warn or Block;
  - a warning note, shown when a sale is made for them.

  Changing them needs "Set Credit Limits".
- **Their exposure**, in the base currency:
  - what their issued invoices still owe, with the overdue ones shown apart;
  - with Settings → Sales's "Count confirmed orders" (off to start), what confirmed and held orders have not invoiced yet;
  - less their unapplied credit: advances, and credit notes with credit left.
- **Checked** when an invoice is issued and, while orders count, when an order is confirmed - only where that adds to the exposure. An invoice that its deposits or a counted order already cover passes, as does a sale for a customer who may not buy on credit but has paid ahead (16.21: "A credit limit does not affect fully paid cash sales").
- **Over the limit**, or any credit for a customer who may not buy on credit:
  - Warn lets it through, and its message says so.
  - Block refuses it, with the exposure against the limit.
  - "Override Credit Limits" goes on with a reason, which the document keeps and the Activity Log records.
- **The sales form** shows a picked customer's warning note, and their limit, exposure and what is left.
- **Permissions**, on full Sales only: Customer Credit's Set Credit Limits and Override Credit Limits. Finance users get both.
- **Left for later:** credit holds on deliveries (Inventory), and approving an override (Approvals). The Customer Credit Exposure report came with Phase H3c.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand:
  - Bose Electronics' Credit Control card showed no limit; Edit set USD 100.00, Block and the note "Cash on delivery only", and the card showed USD 100.00 left.
  - A new invoice for them showed the note, and "Credit limit $100.00 · balance $0.00 · $100.00 left", under the customer.
  - Issuing a draft of USD 149.99 opened Over the Credit Limit: "would take their balance to USD 149.99. To go on, give a reason." With a reason, it was issued as INV-00004, saying it took them over, and its page showed the reason under Over the Credit Limit.
  - The card then warned "Over the limit: new sales on credit are blocked", with the balance "$149.99 · $49.99 over" (it said "-$49.99 left" until fixed), and Settings → Sales showed the Customer Credit switch, off.
  - A member without Override Credit Limits is covered by the tests, not the browser.
  - The invoices, the customer's credit, its Sales row, the sequence, the keys, the sign-in and the activity were then put back.

**Phase G2: the customer's link** (section 16.22) is built, with these defaults. Phase G is done.

- **On the full Sales app**, the page a secure link opens lets the customer:
  - accept or decline a sent quotation that has not expired, giving their name and, to decline, why. The quotation becomes Accepted or Rejected, as when the business marks it.
  - send a comment on any document it opens (quotation, proforma, sales order or invoice), while Settings → Sales allows it (on to start). The page lists the comments sent through it.
- **Each answer and comment** is kept with when and where from (the address and browser), and shows on the document's page under Customer Responses. The business's users who see that kind of document are told, in the bell and by e-mail, under a new notification kind, Customer Responses.
- **Links expire** after the days Settings → Sales sets (empty, to start, for never), counted from when each link is made. An expired link opens the same "no longer available" page, Share Link makes a new one, and its dialog says when the link stops working.
- **Nothing internal shows:** the page has the document as its PDF shows it, the customer's own comments, and the forms.
- Sales Lite's links stay as they were: view and download only, never expiring. Paying online waits for a payment gateway.
- **Also fixed:** on Sales Lite, saving Settings → Sales sent the full app's settings at their defaults, which reset any chosen on full Sales. The form now leaves them out there.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand, at `127.0.0.1:3000` (another project's dev server held `localhost:3000`):
  - QUO-00002 for Bose Electronics, sent and shared, opened at its link with Your Answer and Comments under it.
  - Accepting as "Rahim Ahmed" thanked the customer and showed "Accepted by Rahim Ahmed on 15 Nov 2026, 11:25"; a comment then showed under Comments.
  - In the app the quotation read Accepted, its Customer Responses listed both with time, address and "Chrome on Windows", and the bell had "Bose Electronics accepted quotation QUO-00002" and the comment.
  - Settings → Sales took 7 days; a new link's Share Link dialog said "It works until 22/11/2026, 11:29 AM, then a new one is needed."
  - The quotation, its links and responses, the notifications, the settings, its Sales row, the sequence, the keys, the sign-in and the activity were then put back.

**Phase H: settings, dashboard and reports** was split on starting it (2026-11-15): H1, the rest of Settings → Sales and the Dashboard's sales pipeline; H2, the sales analysis reports; H3, the pipeline and operations reports.

**Phase H1: settings and the Dashboard** (sections 16.23 and 16.25) is built, with these defaults:

- **Settings → Sales**, on the full Sales app, gains:
  - **New sales start as** an invoice (as now), a quotation or a sales order. The Dashboard's and a contact's New Sale open that, and New Sale lists it first.
  - **A default price list**, tried after the others, for any customer and location, when no other list prices a line.
  - **Deposits:** the percentage Create Deposit Invoice starts with (none to start).
  - **Backorders** after a partial delivery: ask each time (as now), always make one, or never.
  - **Shipping terms** new documents start with (none to start).
  - **Salesperson:** new documents name whoever makes them, when they may be one (off to start).
- **The Dashboard**, on the full Sales app, adds a Sales Pipeline card for those who see sales values, each figure with its count and value and a link to its list:
  - quotations awaiting an answer (sent, not expired);
  - orders confirmed in the period;
  - orders to deliver, and orders to invoice.
- Stock reservation and the direct invoice's stock method wait for Inventory. E-mail templates for orders and delivery notes wait with their e-mailing.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand:
  - Settings → Sales showed the new settings under the full app's part, and saved Quotation, 25%, always make a backorder, DAP and the salesperson.
  - The Dashboard's New Sale then opened a new quotation, and its Sales Pipeline card showed the four figures (none on dev).
  - The new quotation started with Shipping Terms DAP and izSuite User as salesperson.
  - The deposit's percentage, the backorder rule and the default price list are covered by the tests only.
  - The settings, its Sales row, the sign-in and the activity were then put back.

**Phase H2: sales analysis reports** was split on starting it (2026-11-15): H2a, the "Sales by" reports; H2b, the discount, price list and margin reports.

**Phase H2a: the "Sales by" reports** (section 16.26) is built, with these defaults:

- Eight reports under Sales, on the full Sales app: Sales by Customer, Item, Category, Brand, Location, Salesperson, Sales Team and Source (the app that made each document).
- Each is Sales Summary grouped one way, with the period, location and currency filters: issued invoices less credit notes, before tax, in the base currency, biggest first.
- Category and brand are the items' as they are now; lines without an item fall under "No category" or "No brand". Documents without a salesperson or team, and those made before their source was kept, have rows of their own.
- Sales Summary can group the same five new ways on the full app. Sales Lite is refused them.
- An app's page in the Apps Center lists the eight as "(Sales only)", as it does the full app's pages.
- **Also fixed:** Sales Summary asked for another report's grouping (a supplier, a payee) failed. It now groups by month, as Purchase Summary does.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand:
  - The Reports page listed the eight after Credit Note Details.
  - Sales by Salesperson opened under its own name, with no Group By. For this year it showed the dev invoices as "No salesperson": 3 invoices, $618.99 net.
  - Sales Summary's Group By offered Item Category, Brand, Salesperson, Sales Team and Source. By Source the invoices showed as Sales Lite, and by Item Category and Brand the new joins ran on MySQL.
  - The Sales app's page listed them as "(Sales only)".
  - Its Sales row, the sign-in and the activity were then put back.

**Phase H2b: discounts, price lists and margin** (section 16.26) is built, with these defaults. Phase H2 is done.

- Three more reports under Sales, on the full Sales app, after the "Sales by" ones.
- **The Discount Report:** each issued invoice of the period with a discount, oldest first. It shows what the invoice came to before discounts, its line discounts and its invoice discount, in the base currency, with the total and its percentage. It also shows the customer, the salesperson and any reason for going past a role's limit. Filters: period, location, customer and currency.
- **Price List Usage:** by list and rule, the issued invoice lines each priced, as the line kept them. It shows how many lines, on how many invoices, their quantity and their sales before tax. A rule removed since shows as one; credit notes are not taken off. Only for roles that may see price lists. Filters: period, location and currency.
- **Estimated Sales Margin:** by item, sales before tax less credit notes, less the item's purchase price now times the quantity, with the margin and its percentage. Lines without an item, like delivery, are left out. An item without a purchase price shows no cost or margin, and the totals leave it out. Only for roles that may see costs, and labelled as an estimate, not a Profit and Loss Statement. Filters: period, location and item category.
- Percentages show to one place with their sign, on the page and in the PDF.
- **Also fixed:** saving a price list deleted its rules and made them again, so every sales line it had priced lost its rule. A rule now keeps its id when the list is saved: the form sends it back, and only rules taken out of the list go.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand (after naas, which also uses port 8000, was stopped):
  - The Reports page listed the three after Sales by Source.
  - The Discount Report for this year showed INV-00001 for Bose Electronics: $640.00 before discounts, $64.00 off its lines, 10.0%, right-aligned.
  - Estimated Sales Margin listed Home Office Set and Design Course with no cost, and the Standing Desk at nothing (sold twice and credited twice), under "Total of items with a cost". Given a purchase price of $400.00 for a moment, Home Office Set showed $144.99 and 26.6%.
  - Price List Usage opened empty (no priced lines on dev).
  - A price list added for the check, saved from its form with a new priority, kept its rule's id.
  - The list, the purchase price, its Sales row, the sign-in and the activity were then put back.

**Phase H3: pipeline and operations reports** was split on starting it (2026-11-15): H3a, the quotation reports; H3b, orders and deliveries; H3c, returns, money and credit.

**Phase H3a: the quotation reports** (section 16.26) is built, with these defaults:

- Three more reports under Sales, on the full Sales app, for roles that see sales and quotations.
- **Quotation Performance:** the quotations of the period that went out (not drafts or cancelled ones), grouped by month, customer, salesperson or sales team. For each group: how many, their value, how many were accepted, converted, rejected or expired or are still open, and the value won (accepted or converted). Values are grand totals in the base currency, as the Sales Overview counts them.
- **Quotation Conversion Rate:** the same quotations and groupings: how many went out and were won, the share won, and the same by value.
- **Expired Quotations:** each draft or sent quotation whose expiry date had passed by the as-of day (today to start), the latest to expire first. It shows the customer, the salesperson, how many days ago it expired and its total. Each opens the quotation, which can still be revised or converted.
- Filters: period, grouping, location and currency for the first two; as of, location and customer for Expired Quotations.
- A quotation's history is not kept, only where it stands now: one converted after it expired counts as converted.
- By month, every month of the range shows, a quiet one too; the other groupings put the most valuable first. Expired Quotations shows each total in its own currency, beside its currency, and in the base currency.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand:
  - The Reports page listed the three after Sales by Source, before the Discount Report.
  - Quotation Performance offered Month, Customer, Salesperson and Sales Team. For this year it showed every month, and October's QUO-00001 ($569.99) as converted and won; by salesperson, as "No salesperson".
  - Quotation Conversion Rate showed October at 100.0% by number and by value, and no rate for quiet months.
  - With QUO-00001 made sent and expired on 10 November for a moment, Expired Quotations showed it 5 days expired for Rahman Traders, and Quotation Performance counted it as expired. Its own-currency total first showed without its currency, until a Currency column was added.
  - The quotation, its Sales row, the sign-in and the activity were then put back.

**Phase H3b: orders and deliveries** (section 16.26) is built, with these defaults:

- Four more reports under Sales, on the full Sales app, after the quotation reports.
- **Open orders** are those confirmed or on hold, as they stand now, as the Dashboard counts them. Values are before tax, in the base currency. Left to deliver is each goods line's share for what is not delivered yet (services need no delivery). Left to invoice is each line's share for what its ordered quantity has not been invoiced yet. The order reports are for roles that see sales and orders.
- **Sales Order Backlog:** the open orders grouped by the month ordered, customer, salesperson or sales team: how many, their value, and what is left to deliver and to invoice. Filters: grouping, location and currency.
- **Orders to Deliver:** each open order with goods still to deliver, the earliest expected day first and those without one last. It shows the date, expected day, days late, customer, salesperson, status, delivery status and what is left to deliver. Filters: location and customer.
- **Orders to Invoice:** each open order not invoiced in full, oldest first, with its value, what was invoiced and what is left. Filters: location, customer and currency.
- **Delivery Performance:** the deliveries that arrived in the period, by month, customer or carrier. It shows how many, how many came on time (by their order's expected day) or late or had no expected day, the on-time rate and the average days late. It shows no values, so it is for roles that see deliveries. Filters: period, grouping and the delivery's location.
- The three order reports read "Open on" today and take no dates: an order's quantities are where they stand now.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand. Dev had no orders, so one was made through the API for the check: SO-00001 for Bose Electronics, two Standing Desks and a Design Course ($689.00), expected on 12 November, with one desk delivered today by DHL as DN-00001.
  - The Reports page listed the four after Expired Quotations, before the Discount Report.
  - Sales Order Backlog showed November: 1 order, $689.00, $369.00 left to deliver, $689.00 left to invoice; its groupings were Month, Customer, Salesperson and Sales Team.
  - Orders to Deliver showed SO-00001 3 days late, Partially Delivered, with $369.00 left, opening the order.
  - Orders to Invoice showed it Not Invoiced, $689.00 left.
  - Delivery Performance showed the delivery late, 0.0% on time and 3 days late on average, by month and under DHL by carrier.
  - The order, its delivery, their number sequences, its Sales row, the sign-in and the activity were then put back.

**Phase H3c: returns, money and credit** (section 16.26) is built, with these defaults. Phase H, and with it Doc 16, is done.

- Four more reports under Sales, on the full Sales app, each where Doc 16 lists it.
- **The Returns Report:** the returns of the period that were submitted (not drafts or cancelled ones), grouped by month, item, customer, condition or resolution. It shows how many, the quantity, and how many are open (submitted or authorised), received (received or completed) or rejected. It shows no values, so it is for roles that see returns. Filters: period, grouping and the return's location.
- **Credit Notes and Refunds:** the credit notes issued in the period, grouped by month, customer, salesperson or sales team (a credit note keeps its invoice's). It shows how many, what they credited, and what became of it as it stands now: applied to invoices, refunded, or still available, in the base currency. Filters: period, grouping, location and currency.
- **Invoice and Payment Report:** each invoice issued in the period, oldest first, as it stands today. It shows its total, the payments, the advances applied (apart), the credit applied, the tax withheld and what is still owed, in the base currency, and Paid, Partially Paid or Unpaid. Filters: period, location, customer and currency.
- **Customer Credit Exposure:** each customer who owes, holds credit or has orders that count (or who has a credit limit), the most exposed first, as their Credit Control card works it out (Phase G1). It shows the limit, outstanding, overdue, orders not invoiced (while Settings > Sales counts them), unapplied credit, exposure, available, and whether they are over the limit or may not buy on credit. As of today, with no filters.
- The three with values are for roles that see sales.
- The Credit Control card and the report work a customer's exposure out the same way: the card for one customer, the report for all at once.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with full Sales switched on by hand:
  - The Reports page listed the four where Doc 16 puts them: the Invoice and Payment Report, Returns Report and Credit Notes and Refunds after Delivery Performance, and Customer Credit Exposure after Price List Usage.
  - Dev had no returns, so an order of two Standing Desks was made and delivered through the API, and one returned as damaged and submitted (RET-00001). The Returns Report showed it open for November, and under Damaged by condition; its groupings were Month, Item, Customer, Condition and Resolution.
  - Credit Notes and Refunds showed October's two: $576.00 credited, $288.00 applied and $288.00 refunded.
  - The Invoice and Payment Report for this year showed INV-00001 settled by $288.00 paid and $288.00 credit, INV-00002 paid with $28.50 withheld, and INV-00003 unpaid with $49.00 owed.
  - With a $40.00 limit given to Chowdhury & Sons for a moment, Customer Credit Exposure showed them $49.00 exposed (all of it overdue), $9.00 over, as their Credit Control card did.
  - The order, its delivery and return, their number sequences, the limit, its Sales row, the sign-in and the activity were then put back.

**Fixed on 2026-11-16: Doc 16's records at their own locations.** Found while building Doc 17 Phase B2b.

- **The bug:** every `api.sales.*` route with an `{id}` was checked at the location of the sales document with that id.
  - The routes: those of a delivery, return, customer advance, credit taken off an invoice, price list and customer credit.
  - A member limited to some locations could be refused a record of their own location.
  - Worse, deliveries had no location check of their own, so another location's delivery could open.
- **Now:**
  - Deliveries, returns and advances are each checked at their own location, and credit taken off at its invoice's. A new delivery or return is still checked at its order's.
  - Price lists and a customer's credit are checked at none: a price list names its own locations, and a customer is the whole business's.
  - An advance taken at no location is anyone's, as its own page and list already had it.
  - The delivery service now refuses another location's delivery itself, as returns already did.
- Delivery and return files open only for whoever may view deliveries or returns at their location.
- A legal hold stops deleting draft deliveries and returns, their files, and price lists.
- `SalesRecordLocationsTest`. No migration.

### 5. Doc 17: CRM (done 2026-11-19)

The gap check was done on 2026-11-15. Doc 17 is CRM: leads, opportunities and the activities around them, before and alongside Sales. It shares Contacts, and nothing in it counts as sales.

**Already built, which CRM uses:**

- The Apps Center lists CRM as available from Standard, in one edition. Turning it on adds Leads and Deals to the sidebar; both open placeholder pages.
- The shared Contacts: customers and suppliers in one list, with tags, import and export, and a duplicate check by name, e-mail, phone and tax number when one is saved. There is no merge.
- Sales teams (Doc 16 Phase A): a name, members and active or not, in Settings → Sales on the full Sales app only. A sales document keeps its salesperson and team.
- Sales documents and their conversions, linked by `source_id`, on Sales Lite and full Sales.
- Comments with mentions, and an activity timeline read from the Activity Log, on sales documents, purchases and expenses (Doc 14 Phase D). Attachments on documents.
- Notifications in the bell and by e-mail, with preferences per kind (Doc 14 Phase B). Documents sent by e-mail, with their history (Docs 11 and 14).
- Roles, permissions and location access (Doc 12), the Reports page, the Dashboard and Settings.

**Missing (what Doc 17 adds):**

- 17.3 to 17.5: the CRM menu (Overview, Leads, Opportunities, Activities, Forecast and Customers), the Overview with its figures and filters, and the two operating modes.
- 17.6 to 17.11: leads, which do not exist:
  - their fields, statuses and rules, and their sources;
  - assignment by hand, round-robin, or by location, team, source, product, territory or workload, with a queue for unassigned leads;
  - qualification, and conversion into a contact and an opportunity after a duplicate review.
- 17.12 to 17.16: opportunities:
  - pipelines whose stages have a probability, category, stale period and required fields;
  - kanban, list and calendar views, with each stage move and the time in each stage kept;
  - probability and the weighted forecast in the base currency;
  - the actions, won and lost with reasons, reopening, and an automatic win from a confirmed order.
- 17.17 to 17.19: activities (types, statuses, overdue, reminders, follow-ups); the communication timeline (calls with direction and outcome, meetings, notes, stage and owner changes, sales documents); e-mail from CRM records.
- 17.20: the CRM part of a contact's profile.
- 17.21 to 17.23: duplicate review among leads and contacts, with ignore, link and merge; rule-based lead scoring; response times and follow-up warnings.
- 17.24: a sales team's manager, locations, pipeline, territories and assignment method, and teams outside full Sales.
- 17.25: marketing consent on contacts, apart from permission to follow up a sale.
- 17.26: Settings → CRM. Custom fields exist nowhere yet.
- 17.27 to 17.31: opportunities linked to sales documents both ways, the Dashboard's CRM figures, 20 reports, the CRM permissions and its data rules.

**Waiting for other apps**, as Doc 17 itself says:

- Marketing: campaigns that make leads, bulk promotional e-mail, and its consent rules.
- Digital Business Profile and eCommerce: enquiries flowing in.
- Helpdesk: support history on the contact.
- Projects, Appointments and Field Service: activities and service work linked to them.
- Integrations and API: website forms, e-mail synchronisation and replies, telephony, SMS and WhatsApp.
- Automation and AI: advanced assignment and reminders, AI scoring and summaries.

**Phases, with suggested defaults.** Saying "Start Phase A" accepts them.

- **Phase A: CRM comes on.**
  - The sidebar in Doc 17's order: Overview, Leads, Opportunities, Activities, Forecast, and Customers (Contacts filtered to customers), each item added by the phase that builds it. Deals becomes Opportunities.
  - Settings → CRM:
    - the operating mode: Leads and Opportunities, or Opportunities Only;
    - lead sources, lost reasons and activity types, each a list starting with Doc 17's examples, which a business can rename, add to and switch off;
    - one pipeline with stages New, Qualified, Proposal, Negotiation, Won and Lost, and their probabilities.
  - Sales teams shared by Sales and CRM: in Settings → CRM as well as Settings → Sales, gaining a manager.
  - CRM works with Sales Lite, full Sales or no Sales. Turned off, its pages hide and its records stay.
  - The CRM permissions of 17.30, each phase adding its own.
- **Phase B: leads.**
  - A lead in its own table:
    - name or contact person, phone or e-mail, source and its details, owner, location (when there are several) and status;
    - 17.6's optional fields: business, job title, address, country, language, interested items, estimated value and currency, expected purchase date, team, priority, tags, notes and attachments.
    - Campaigns wait for Marketing, and custom fields for Phase I.
  - Statuses New, Assigned, Contacted, Qualified, Unqualified (with a reason), Converted and Archived. Reopening an unqualified or archived lead needs the permission.
  - Assignment by hand to an active member who may see the lead's location, with a notification and the change in the Activity Log. Unassigned leads wait in a queue.
  - Qualification: need, interested items, budget, purchase period, decision maker, business size, next action and notes. Settings → CRM says which are needed to qualify.
  - A lead's page with its comments, attachments and timeline (Doc 14's).
- **Phase C: converting leads, and duplicates.**
  - Conversion:
    - contacts matching by e-mail, phone, tax number and business name are offered to choose from, or a new contact is made;
    - the customer role is given and an opportunity made;
    - the source, owner, activities, notes, attachments and consent are carried over;
    - the lead is marked Converted, its history kept.
  - Possible duplicates among leads, by the same checks, to review, ignore with a reason, or link.
  - Merging contacts, with the permission, keeps every record and never moves a financial document silently.
- **Phase D: opportunities and pipelines.**
  - An opportunity: name, contact, pipeline, stage, owner, location, expected value before tax, currency, expected closing date, and 17.12's optional fields.
  - Pipelines in Settings → CRM: stages with order, default probability, category (open, won or lost), stale-after days, required fields and status. A pipeline can be for chosen teams or locations.
  - Kanban, list and calendar views. Each stage move is kept with the time spent, and required fields are checked on entering a stage.
  - Probability comes from the stage unless changed with the permission; won is 100% and lost 0%. The weighted value is in the base currency at today's rate, as an estimate.
  - Won, with a date, value, reason and sales document. Lost, with a date, reason, competitor, notes and a follow-up date. Reopening needs the permission.
- **Phase E: activities and the timeline.**
  - Activities:
    - call, meeting, task, e-mail, follow-up, demonstration, site visit or other, for a lead, opportunity or contact;
    - an assigned member, a due date and time, and 17.17's optional fields;
    - Planned, Completed or Cancelled, with overdue worked out; an outcome on completing where it is required, and a follow-up straight after.
  - Reminders in the bell and by e-mail, under a new notification kind.
  - The Activities page: today's, overdue and planned, and each member's own.
  - The timeline of a lead, opportunity and contact: calls (direction and outcome), meetings, notes, activities, stage and owner changes, sales documents, won and lost, and attachments. Internal notes never show to the customer.
- **Phase F: Sales and the contact's profile.**
  - Create Quotation, Proforma Invoice, Sales Order (on full Sales) and Invoice from an opportunity, each linked to it (`opportunity_id`). Several quotations add no value twice. Their confirmation or issue shows on its timeline, and without Sales the actions offer turning it on.
  - An opportunity marked won when its linked order is confirmed, if Settings → CRM says so.
  - E-mail from a lead, opportunity or contact through the business's e-mail, with templates and attached documents, kept on the timeline. Replies and synchronisation wait for an e-mail integration.
  - A contact's profile shows its lead source, open, won and lost opportunities, planned activities and the timeline beside its sales documents and balance.
- **Phase G: Overview, Forecast and follow-up.**
  - The CRM Overview of 17.4 with its filters, and the Forecast page: the weighted value by expected closing month, owner, team or stage.
  - The Dashboard's CRM figures of 17.28, labelled as not sales.
  - Response tracking (17.23): first assignment and response times, last contact, next activity, days without activity and time in stage.
  - Warnings for unassigned and uncontacted leads, overdue activities, opportunities without a next action, stages held too long, and opportunities closing soon without activity. The periods are in Settings → CRM.
- **Phase H: reports.** The 20 reports of 17.29, in parts.
- **Phase I: rules and records.**
  - Assignment rules beyond the hand: round-robin, and by location, team, source, product interest, country and workload.
  - Rule-based lead scoring that shows its factors, and never qualifies or rejects a lead by itself.
  - Marketing consent on contacts and leads, apart from sales follow-up: status, purpose, source, when, who, expiry, withdrawal and evidence.
  - Custom fields for leads and opportunities.

**Phase A: CRM comes on** was split on starting it (2026-11-15): A1, CRM's menu, settings and lists; A2, the pipeline and shared sales teams.

**Phase A1: menu, settings and lists** (sections 17.3, 17.5, 17.8, 17.16, 17.17 and 17.26) is built, with these defaults:

- **The CRM sidebar** in Doc 17's order: Overview, Leads, Opportunities, Activities, Forecast, then Customers (Contacts filtered to customers). Each page comes with the phase that builds it. For now that is Leads and Opportunities (still placeholders) and Customers; Deals becomes Opportunities.
- **Settings → CRM**, for roles with Manage CRM Settings:
  - The operating mode: Leads and Opportunities (to start), or Opportunities Only, which hides Leads. Changing it deletes nothing.
  - Lead Sources, Lost Reasons and Activity Types. Each list starts with Doc 17's examples, and the business can rename them, add its own and switch any off. One izSuite itself uses (a starting one) is never deleted, only switched off; one the business added can be deleted while nothing uses it.
- A business's lists start the first time its CRM settings are read, so a business with CRM on already gets them too.
- CRM works with Sales Lite, full Sales or no Sales. Turned off, its pages hide and its settings stay.
- The CRM permissions start with CRM Settings; each later phase adds its own (17.30).
- **Checked in the browser** on the first PC, as `user@izsuite.io`, with CRM switched on by hand (dev had it turned off):
  - The sidebar's CRM showed Leads, Opportunities and Customers, in that order, and Opportunities opened its placeholder page.
  - Settings → CRM came after Expenses, with the operating mode and the three lists: 12 lead sources, 9 lost reasons and 8 activity types, each with Rename and Switch Off but no Delete.
  - A lead source added as Trade Show was renamed Trade Fair, switched off and deleted. Its first answer called it switched off though it was saved on, until the model was given the column's default.
  - Saving Opportunities Only took Leads out of the sidebar at once; switching back brought it again.
  - The lists, the settings, the app's state, the sign-in and the activity were then put back.

**Phase A2: the pipeline and shared sales teams** (sections 17.13, 17.14 and 17.24) is built, with these defaults. Phase A is done.

- One pipeline with stages New, Qualified, Proposal, Negotiation, Won and Lost, each with its order, default probability (10%, 25%, 50%, 75%, 100% and 0%) and category (open, won or lost), edited in Settings → CRM. More pipelines come with opportunities (Phase D).
  - The pipeline is saved whole: its name and its stages in order, added, renamed, moved up or down, switched off or removed. A stage keeps its id when saved, so the opportunities in it will stay there.
  - A won stage is always 100% and a lost one 0%, and a pipeline always keeps an open, a won and a lost stage switched on.
  - A business's pipeline starts the first time its CRM settings are read, as its lists do.
- Sales teams shared by Sales and CRM: in Settings → CRM as well as Settings → Sales, gaining a manager. With CRM on they are there without full Sales.
  - The manager is one of the business's active people, in the team or not. Either app's settings permission changes the teams.
  - The team form takes its people from Settings' own list, since CRM can be on without Sales.
- **Checked in the browser** on the first PC, as `user@izsuite.io`, with CRM switched on by hand on a business with Sales Lite only:
  - Settings → CRM showed the Pipeline with its six stages (Won fixed at 100% and Lost at 0%) and the Sales Teams card, after a restart of the frontend container, which had not picked up the new components.
  - A stage Demo at 60% was added and moved above Negotiation, New renamed Enquiry, and the pipeline saved; every stage kept its id and Demo became the seventh.
  - With Won switched off, saving said a pipeline needs an open, a won and a lost stage switched on.
  - A team Dhaka North was added with izSuite User as manager and member, and switching it off kept its manager.
  - The pipeline, the team, the lists, the app's state, the sign-in and the activity were then put back.

**Phase B: leads** was split on starting it (2026-11-15): B1, leads themselves; B2, qualification and a lead's records.

**Phase B1: leads themselves** (sections 17.6 to 17.9 and 17.30) is built, with these defaults:

- **A lead** in its own table:
  - Required: its name (the person, or the business when nobody is named), a phone or an e-mail (at least one), a source from Settings → CRM's Lead Sources, and its location when the business has more than one (else its only one).
  - The owner is whoever adds it, unless someone who may assign leads gives it to another member or leaves it in the queue.
  - Optional: business name, job title, the source's details (one line: a referral's name, a form, a platform or a reference), address and country, preferred language, estimated value and currency (the business's), expected purchase date, sales team, priority (Low, Medium or High), tags and notes.
  - Interested items and attachments come with B2, campaigns with Marketing, custom fields with Phase I.
  - A lead is never deleted: Archive keeps it in history and reports (17.7).
- **Statuses** (17.7), fixed, as its rules hang on them:
  - New: no owner yet, waiting in the queue. Assigned: an owner, not contacted yet; a lead added with an owner starts here.
  - Contacted (Mark Contacted, from Assigned), Qualified (Qualify, from Assigned or Contacted; B2 adds what must be filled in first), Unqualified (with a reason, from any open status) and Archived (an optional reason, from any status but Converted).
  - Reopen takes an unqualified or archived lead back to Assigned (New without an owner), with the Reopen permission. Converted comes with Phase C.
  - An open lead (New to Qualified) can be edited; an unqualified or archived one is reopened first.
- **Assignment by hand** (17.9), with Assign Leads: to an active member who may view leads and use the lead's location.
  - A New lead becomes Assigned; any other keeps its status.
  - The new owner is notified, in the bell and by e-mail, under a new Leads kind (unless they gave it to themselves). The Activity Log keeps the change with the previous owner.
  - Unassigned leads are the queue: the Leads page's New filter, seen by whoever may assign leads.
- **Who sees which leads** (17.30), always only at the locations they may use (Doc 12):
  - Everyone with View Leads sees the leads they own.
  - View Team Records adds those owned by the people of the sales teams they belong to or manage, and those given to those teams.
  - View All CRM Records shows every lead; Assign Leads adds the queue.
- **Permissions:** Leads (View, Create and Edit, Assign, Reopen) and CRM Records (View Team Records, View All CRM Records). The Sales User template gets View and Create and Edit.
- **Pages:** the Leads page (search by name, business, e-mail or phone; open leads first, filtered by status, owner and source), Add Lead, a lead's page with its details and steps, and Edit Lead.
- In Opportunities Only mode the leads stay and can still be read (a notification's link opens), but none is added or changed, and a lead's page offers no steps.
- A lead source or a sales team that a lead uses is switched off, not deleted, and so is a location with leads.
- A business's data export (Doc 15's privacy requests) includes its leads, as `leads.csv`. With them it now holds what Doc 16 added too, which it had missed: deliveries and returns with their lines, customer advances, credit applied to invoices, customers' answers through a document's link, price lists with their rules, customers and locations, and sales teams with their members. CRM's lists and pipelines with their stages are in as well, 50 CSV files in all. The apps' settings (Sales, Purchases, Expenses, CRM) stay out, as they hold no records.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand, and as a throwaway member whose role sees and works their own leads only:
  - A lead added from the form (source Event, value $2,500, priority High) was Assigned to its maker. Marked contacted; unqualified, first without a reason (refused) then with one, shown on its page; reopened back to Assigned; edited (the phone-or-e-mail rule showed when both were empty); archived without a reason, leaving only Reopen.
  - The list showed open leads to start, and its status and source filters worked.
  - A second lead left in the queue was New, offering Assign but not Mark Contacted. Assigned to the member, it became theirs: their bell said who gave them the lead, its link opened it, their list showed only that lead with no owner filter, and the owner's lead answered "Lead not found."
  - The owner list first came back without names: the members' list loads each user without their name. It now loads first and last names.
  - With Opportunities Only, Leads left the sidebar and a lead's page showed no steps.
  - The leads, the lists, the member, the notification, the app's state, the sign-in and the activity were then put back.

**Phase B2: qualification and a lead's records** was split on starting it (2026-11-16): B2a, qualification and interested items; B2b, a lead's attachments, comments and timeline.

**Phase B2a: qualification and interested items** (sections 17.6 and 17.10) is built, with these defaults:

- **A lead's qualification**, in its form and on its page:
  - customer need, estimated budget (in the lead's currency) and expected purchase period (this month, within 3, 6 or 12 months, or later);
  - decision maker, business size or type, next action and qualification notes.
- **Interested items or services:** items from the business's list (for whoever may view items), or names typed in, each with an optional quantity. A lead keeps an item's name if the item is deleted later.
- **Settings → CRM's Qualification card** says which of these must be filled in before a lead is qualified. To start, that is the customer need. Qualify refuses, naming what is missing.
- Qualifying is no sale and makes no document (17.10).
- A lead's page lists what qualifying still needs, while the lead can be qualified.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - Settings → CRM's Qualification card started with Customer Need ticked; Interested Items and Next Action were added and saved, and the Activity Log kept the change.
  - Add Lead starred those three. A lead was added with an item found in the list (Office Chair, then no longer offered), a typed one (Delivery and assembly × 2), a need, a budget, a period and a decision maker.
  - Its page showed the qualification and items, and "Before it is qualified, fill in: next action." Qualify refused with "Fill in the next action before qualifying this lead."
  - Edit Lead loaded it all; with the next action filled in and the chair removed, it saved, and Qualify went through.
  - The lead, the CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase B2b: a lead's attachments, comments and timeline** is built, with these defaults. Phase B is done.

- Attachments on a lead's page, added and removed by whoever may edit leads (Create and Edit), at any status. They open only for whoever sees the lead.
- Comments with mentions (Doc 14's), mentioning only people who can see the lead.
- The lead's timeline, read from its Activity Log entries: added, changed, assigned (with the previous owner), contacted, qualified, unqualified, archived, reopened, its comments and its files.
- A mention reaches the person in the bell and by e-mail, under Comments and mentions, linking to the lead's comments.
- A legal hold (Doc 15) stops a lead's files and comments being deleted, as it does other records'.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - A lead's page showed Attachments and the Comments and Activity card.
  - A PDF was uploaded and its download link opened. A comment was posted.
  - Activity listed the comment, the attachment and the lead being added, newest first.
  - The file was then removed through the API, and the lead, its comment, the CRM lists, the app's state, the sign-in and the activity were put back.

**Phase C: converting leads, and duplicates** was split on starting it (2026-11-16): C1, converting a lead; C2, possible duplicate leads; C3, merging contacts.

**Phase C1: converting a lead** (sections 17.7 and 17.11) is built, with these defaults:

- **Convert**, on a Qualified lead's page, with a new Convert Leads permission. A converted lead is final: it is never converted again.
- **The contact:**
  - The business's contacts matching the lead's e-mail, phone, business name or name are listed first, with what matched.
  - Any other contact can be chosen. Or a new one is made from the lead, for whoever may create contacts: its business (or name), person, job title, e-mail, phone, address, language and tags.
  - The contact becomes a customer: a supplier becomes both.
- **The opportunity**, made in the business's pipeline:
  - at an open stage (the first, to start) with that stage's probability;
  - named after the lead's business or name;
  - with an expected value and currency and an expected closing date: the lead's estimate and expected purchase date, or typed in;
  - carrying over the lead's owner, location, sales team, priority, source, customer need, next action and notes.
- **The lead's attachments, comments and timeline stay with it.** The opportunity keeps its lead, so Phase D's opportunity page can show them; nothing is copied. Activities and consent come with Phases E and I.
- **The lead becomes Converted**, its page showing its contact and opportunity. The Leads page can filter on Converted.
- Opportunities' own pages and changes come with Phase D. Until then:
  - a stage with opportunities in it cannot be removed from the pipeline, only switched off;
  - a contact or location with opportunities cannot be deleted;
  - the data export includes opportunities.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - A qualified lead's page offered Convert.
  - Its dialog listed Rahim Uddin (C-00001, "the same name and e-mail") first, with Another contact and A new contact as the other choices, and the opportunity started from the lead's name, value and date at New (10%).
  - Converted to Rahim Uddin at Qualified (25%) as "Rahim Uddin - branch stock": the lead became Converted with no buttons left, and its page showed the contact (a link), the opportunity, its stage, value and closing date.
  - A second lead, matching no one, started on A new contact. The browser held the conversion until a closing date was given. It then made Haque Garments (C-00021, business, customer, its person, job title, e-mail, phone and city) and an opportunity at New.
  - The leads, opportunities, the new contact and its number, the CRM lists, the app's state, the sign-in and the activity were then put back.

**Phase C2: possible duplicate leads** (section 17.21) is built, with these defaults:

- **What makes a possible duplicate:**
  - another lead with the same e-mail, phone (its last nine digits, as contacts compare them) or business name;
  - a contact matching as conversion does: the same e-mail, phone, or business name (the person's name, for a lead with no business).
  - Only leads the user sees count, and contacts only for a role that may view them.
- **Where it shows:**
  - An open lead's page has a Possible Duplicates card, each match with what matched.
  - The Leads page marks an open lead with "Possible duplicate" while one is unreviewed.
- **Each can be reviewed** (its lead or contact opens), and with Create and Edit:
  - ignored with a reason, so it is no longer flagged for that match;
  - or linked: a linked contact comes first when the lead is converted, and two linked leads show each other.
  - Either can be taken back. Between two leads it counts on both.
- The Activity Log keeps each decision. Merging leads is not offered; merging contacts is Phase C3.
- The data export includes the decisions (`lead-duplicate-reviews.csv`).
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - Two leads of "Chowdhury & Sons" (written in different case) and one other were added. The Leads page marked the two "Possible duplicate", not the third.
  - The first one's Possible Duplicates card listed the other lead ("The same business name", its status and owner) and the contact Chowdhury & Sons (C-00004, "The same name").
  - Not a Duplicate would not save without a reason. With one, the other lead showed it, with who decided, on both leads.
  - Link marked the contact Linked. The first lead was then no longer flagged, while the second still was for its contact. Take Back flagged the contact again.
  - The leads, the decisions, the CRM lists, the app's state, the sign-in and the activity were then put back.

**Phase C3: merging contacts** (section 17.21) is built, with these defaults. Phase C is done.

- **Merge Contacts**, a new permission in the Contacts group (Contacts: Merge), needing Edit. Contacts are shared by every app, so it works with or without CRM. Only the owner and administrators have it to start; no other template gets it.
- **Starting a merge:** More → Merge Contacts on a contact's page.
  - The contact's possible copies come first: Doc 03's check by name, phone, e-mail or tax ID, with what matched. Any other contact can be chosen too.
  - The user says which of the two is kept.
  - A preview follows, then the merge. A merge cannot be undone.
- **The kept contact gains only what it lacks:**
  - each detail it has none of: the trading name, person and job title, e-mail, phone, website, language, default currencies, payment terms and tax information;
  - an alternative phone, if it has none: the other's number it does not already have;
  - the other's address, billing and shipping together, only when it has no address (a country alone does not count). Two addresses are never mixed.
  - Its role widens to both when one is a customer and the other a supplier.
  - Its tags gain the other's, up to 20. Its notes gain the other's under "Merged from C-000nn Name:", while they fit in 5,000 characters; otherwise they stay on the other only.
  - Its type, name and credit control stay its own.
- **What moves to the kept contact:**
  - the other's leads and opportunities;
  - its attachments;
  - its places on price lists, except a list the kept one is already on;
  - the decisions about it as a lead's possible duplicate, except where that lead already decided about the kept one.
  - Contacts have no comments or timeline of their own, so nothing else moves. The Activity Log is never rewritten.
- **What stays with the other contact:** its financial records: sales documents and customer payments, advances, purchases and supplier payments, expenses and their payments. The preview lists how many of each, so nothing financial moves silently.
- **The other contact is archived, merged into the kept one:**
  - Its page says into which, when and by whom, and the Contacts list marks it "Merged".
  - It no longer changes: it cannot be edited, archived or restored, deleted, given attachments, or merged again.
  - It is no longer offered as a possible duplicate, in lead matches, for lead conversion, or for linking.
  - The kept contact's page has a Merged Contacts card. A contact others were merged into cannot be deleted.
- **The Activity Log** has the merge on both contacts: 'Merged contact "B" (C-00022) into "A" (C-00021)'.
- **Checked in the browser** on the first PC, as `user@izsuite.io`:
  - Rahman Stores (C-00021, a customer) and Rahman Store (C-00022, a supplier with an attachment) were added. Merge Contacts on the first offered the second ("the same e-mail").
  - The preview listed the person, website, supplier terms, the other phone as Alternative Phone, Role Both and the tag importer, the notes added, 1 attachment moving and nothing financial staying.
  - After the merge, the kept page said "Contacts merged.", showed each of those, the Merged Contacts card, both notes and the moved file. It no longer offered Delete.
  - The merged page showed the Merged pill and the "Merged into Rahman Stores (C-00021) on 16/11/2026" banner. It had no Edit, Archive, Merge, Delete or Upload, and its Edit address showed the banner instead of the form. The list marked it Merged without Edit or Delete. The contact picker for merging and converting left it out.
  - Keeping the other contact (Nusrat Jahan into Nusrat Jahan Mitu) opened the kept one's page with "Contacts merged.".
  - The contacts, the file, the numbers, the sign-in and the activity were then put back.

**Phase D: opportunities and pipelines** was split on starting it (2026-11-16):
- D1, opportunities themselves: the Opportunities page, an opportunity's page, adding and editing one, its owner, its stage moves and probability, and the forecast.
- D2, won, lost, reopening and archiving, with an opportunity's files, comments and timeline.
- D3, pipelines: several of them, each stage's stale-after period and required fields, a pipeline for chosen teams or locations.
- D4, the kanban and calendar views.

**Phase D1: opportunities themselves** (sections 17.12 to 17.14) is built, with these defaults:

- **An opportunity's fields:**
  - required: name, contact, stage (an open stage of the business's pipeline), owner, location, expected value before tax, currency and expected closing date;
  - optional: the person to deal with there, interested items, sales team, probability, priority, lead source, competitors (up to ten names), next action, customer requirements, tags and notes.
  - Campaigns wait for Marketing, custom fields for Phase I, the lost reason for D2.
  - Its contact is any active contact not merged into another. A supplier becomes a customer as well, as on converting a lead.
- **Permissions**, a new Opportunities group: View, Create and Edit, Change Stages, Change Probability. The Sales User template gets the first three.
- **Who sees which:** as with leads: their own; their teams' with View Team Records; every one with View All CRM Records; only at their locations.
  - Creating and editing include giving it to someone else: an active member who may view opportunities at its location. They hear of it under a new Opportunities notification kind.
- **The Opportunities page:**
  - open ones by expected closing date, soonest first, an overdue one marked;
  - searched by name or contact, and filtered by status, stage and owner;
  - showing the stage with its probability, the expected and weighted values, and the closing date.
- **An opportunity's page:**
  - its details, the lead it came from, and its interested items;
  - its value: the expected value, the probability, and the weighted value (expected × probability), also in the base currency at today's rate as an estimate;
  - its stages: where it is now, and each stage it has been at, with when, by whom and how long.
  - Actions: Edit, Change Stage.
- **Changing its stage:** to another open stage, with Change Stages. Each move is kept with the time spent at the stage before, and logged. The probability becomes the new stage's.
- **Probability:** the stage's by default, and changed by hand only with Change Probability, on adding, editing or moving.
- **Converting a lead** now gives its opportunity the lead's interested items and its first stage.
- **Kept as they are:** an opportunity is never deleted. Its contact, location and stage stay protected, and the data export includes its stages and items.
  - A stage an opportunity is at, or has been at, is switched off rather than removed from the pipeline.
  - A lead source or sales team an opportunity names is not deleted.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - The Opportunities page started empty. Add Opportunity started at New (10%) with the user as owner.
  - "Bose Electronics - shop fit-out" was added for Bose Electronics at $1,500, with a person to deal with, a next action and a competitor. Its page said "Opportunity added." and showed $150.00 weighted, the six stages, and one stage row: New, now, by the user.
  - Change Stage offered Qualified, Proposal and Negotiation, with the probability box saying "50, the stage's" for Proposal. After the move: "Moved to Proposal.", 50%, $750.00, and the history Proposal (now) then New.
  - Edit showed the stage as text, with every field filled in. A probability of 60 and a tag saved: "Opportunity saved.", 60%, $900.00.
  - The list showed Proposal 60% and $900.00 in the Weighted (USD) column. Stage, search, "mine" and status filters answered as expected.
  - The opportunity, its stages, the CRM lists, the app's state, the sign-in and the activity were then put back.

**Phase D2** was split on starting it (2026-11-16): D2a, won, lost, reopening and archiving; D2b, an opportunity's files, comments and timeline.

**Phase D2a: won, lost, reopening and archiving** (sections 17.15 and 17.16) is built, with these defaults:

- **Mark Won**, on an open opportunity, with a new Mark Won or Lost permission:
  - the won date (today to start, never a later day);
  - the final value, the expected value to start; it becomes its value;
  - a won reason, optional.
  - It moves to the pipeline's won stage at 100%, kept in its stage history. It is not a sale: sales come from invoices.
  - Its sales document, and winning one from a confirmed order, come with Phase F's links to sales documents.
- **Mark Lost**, with the same permission:
  - the lost date;
  - a lost reason from Settings → CRM's list, needed;
  - the competitor, one of its own or typed in, optional;
  - notes, and a possible follow-up date.
  - It moves to the lost stage at 0%.
- **Reopen**, with a new Reopen Won or Lost permission: back to an open stage - the last one it was at, or another - at that stage's probability. Its won or lost details are cleared; the Activity Log and the stage history keep them.
- **Archive**, with Create and Edit, in any status: it leaves the lists and no longer changes until it is restored. The list's Archived filter finds it, and reports keep it.
- **Its page** shows how it ended: the date, who marked it, the value and reason, or the lost reason, competitor, notes and follow-up date.
- Each step is logged. The Sales User template gets Mark Won or Lost, not Reopen.
- A lost reason an opportunity was lost for stays in Settings → CRM: it is switched off, not deleted.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - "Elahi Foods - cold room" ($4,200 at Proposal, competitor Frost Tech) was added through the API. Its page offered More (Archive), Edit, Change Stage, Mark Lost and Mark Won.
  - Mark Won started at today (no later day allowed) and $4,200.00. At $3,900 with a reason: "Marked won.", a Won card with the date, who marked it, $3,900.00 and the reason, 100%, and the stage history Won then Proposal. Only Reopen and Archive were left.
  - Reopen started at Proposal (50%): "Reopened at Proposal.", the Won card gone, the history Proposal, Won, Proposal.
  - Mark Lost listed the nine lost reasons and offered Frost Tech as the competitor. Saved with notes and a follow-up day: "Marked lost.", a Lost card with each, 0%, $0.00.
  - Archive from More: "Opportunity archived.", the Lost and Archived pills, the archived banner, and only Restore. The list showed it only under Archived (open, lost and all left it out). Restore brought it back, still lost.
  - The opportunity, its stages, the CRM lists, the app's state, the sign-in and the activity were then put back.

**Phase D2b: an opportunity's files, comments and timeline** (sections 17.12 and 17.13) is built, with these defaults - Doc 14's, as leads have them (Phase B2b). Phase D2 is done.

- **Attachments:** a proposal, requirements, photos.
  - Seen by whoever sees the opportunity.
  - Added and removed with Create and Edit, in any status but archived.
  - Counted toward storage. Not in Documents (section 17.31).
- **Comments with mentions:** whoever sees it comments, in any status. A mention offers only the people who see the opportunity, and tells them under Comments and mentions.
- **Its timeline**, from the Activity Log, newest first: adding it, its changes, owners, stage moves, won and lost, reopening, archiving, its files and comments.
- **The lead it came from** keeps its own files, comments and timeline, on its page: the opportunity links to it.
- **A legal hold** stops its files and comments being removed, as for leads.
- **Found while checking:** the timeline listed what a stage move's save touched ("Stage ID: 1 → 2", "Stage entered at" in UTC).
  - A move now shows the stages by name and the probability.
  - Won, lost and reopened show the status, stage, probability, and the value or lost reason, competitor and follow-up day.
  - Archiving, restoring and giving it to someone show no list: their words say it.
  - An edit's list stays as for every record: the fields it changed.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - "Chowdhury & Sons - office fit-out" was added through the API. Its page had the Attachments panel and the Comments and Activity panel.
  - A PDF dropped into Upload showed "Attachment added." and its row. A comment posted showed with Comments 1.
  - Change Stage to Qualified, then Proposal. Activity listed the moves, the comment, the attachment and the adding, newest first. The second move read "Stage: Qualified → Proposal, Probability: 25 → 50".
  - Archived, the panel kept only Download, with no Upload or Remove, and the comment box stayed.
  - The opportunity, its file (and the stored copy), the comment, the CRM lists, the app's state, the sign-in and the activity were then put back.

**Phase D3** was split on starting it (2026-11-16): D3a, each stage's stale-after period and the fields it needs; D3b, several pipelines, each for chosen teams or locations.

**Phase D3a: stale stages and the fields a stage needs** (section 17.13) is built, with these defaults:

- **Stale after:** each open stage can have a number of days, 1 to 365, or none (never stale). An open opportunity at its stage for longer is stale:
  - the Opportunities page marks it Stale with its days at the stage, and a Stale filter lists only those;
  - its page says how long it has been at the stage, against the period.
  - Warnings on the Dashboard, and follow-ups, come with Phase G.
- **Needed to enter:** each stage can need some of the opportunity's details filled in first, from Person to Deal With, Sales Team, Lead Source, Priority, Next Action, Customer Requirements, Competitors and Interested Items. Its contact, value, closing date and owner are always needed.
  - Checked when it is added at the stage, moved there, reopened there or converted from a lead there - and for a won or lost stage, when it is marked won or lost.
  - The refusal names what is missing: "Proposal needs Customer Requirements and Competitors filled in first."
  - Change Stage, Mark Won, Mark Lost and Reopen show what the stage needs, each ticked or marked missing. Their button waits until nothing is missing.
  - Emptying a detail later never moves an opportunity back.
- **Settings → CRM** sets both for each stage, in the pipeline's Stale After and Needs columns: Needs opens a list to tick, saved with the pipeline. A won or lost stage has no stale period.
- **Found while checking:** saving an opportunity that had no tags, with an empty list of them, listed Tags as changed ("— → —"). For every record now, an empty list counts as none, as empty text does; emptying a list still shows.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - Settings → CRM: Qualified stale after 5 days, Proposal needing Customer Requirements and Competitors, Won needing Interested Items. "Pipeline saved.", and all three were there after reloading. The won and lost rows could take no stale period.
  - Adding "Rahman Traders - warehouse racking" at Proposal through the API was refused with "Proposal needs Customer Requirements and Competitors filled in first." At Qualified it was added, and its page read "At Qualified for under a day, stale after 5 days."
  - Change Stage to Proposal marked both missing, said "Fill in Customer Requirements and Competitors with Edit first." and kept Move greyed out. Negotiation showed no list. Once both were filled in, the list ticked them and the move gave "Moved to Proposal."
  - Mark Won marked Interested Items missing and stayed greyed out. The API refused it with "Won needs Interested Items filled in first."
  - Back at Qualified, with its arrival put 6 days back in the database, the Opportunities page marked it "Stale, 6 days". The Stale filter listed only it, not a second one added that day. Its page read "At Qualified for 6 days, stale after 5 days." with Stale.
  - The opportunities, the CRM lists and pipeline, the app's state, the sign-in and the activity were then put back.

**Phase D3b: several pipelines, each for chosen teams or locations** (sections 17.13 and 17.24) is built, with these defaults. Phase D3 is done.

- **Settings → CRM** lists the pipelines under a Pipelines header, which says what decides where an opportunity goes:
  - **Add Pipeline** asks for a name, used once in the business, and whom it is for. It starts switched on, last in the list, with the starter stages (New, Qualified, Proposal, Negotiation, Won and Lost) to change in its own card.
  - Each pipeline's card has **Switched On**, and **Sales Teams** and **Locations**: all of them, or only those ticked. Locations show once the business has more than one. "Only those ticked" with none ticked is refused in the form ("Tick a team, or choose All.").
  - All sales teams includes an opportunity with no team. A pipeline for chosen teams takes only opportunities of those teams.
  - A switched-off pipeline shows Off by its name. New opportunities cannot go in it; those in it stay, and still move between its stages.
  - **Delete Pipeline** shows while no opportunity is or ever was in it, a lead converted into it included. Otherwise it is switched off instead.
  - **One pipeline switched on stays for every team and location,** so every opportunity has somewhere to go. Switching it off, narrowing it or deleting it is refused: "One pipeline switched on must stay for every sales team and location, so every opportunity has somewhere to go." Its card has no Delete while it is the only one.
- **Where an opportunity goes:** a pipeline switched on that is for its sales team and location.
  - Adding one, the form offers those that fit, in Settings → CRM's order, and starts at the first. The Pipeline field shows once the business has several switched on. Changing its team or location to ones the chosen pipeline is not for moves the form to the first that fits, at its first open stage.
  - Through the API, a pipeline left out is the first that fits. One switched off, or not for its team and location, is refused: "Choose a pipeline switched on that is for its sales team and location." A stage of another pipeline is refused as before.
  - Converting a lead offers the pipelines for the lead's team and location, the first to start.
  - An opportunity stays in its pipeline when edited to another team or location. Moving it to another pipeline is not built (see [Small things left](#small-things-left)).
- **The Opportunities page,** while there are several pipelines switched on, has a Pipeline filter. The Stage filter names each stage with its pipeline ("Sales Pipeline · New") until one pipeline is chosen, then lists only that pipeline's. Each row names its pipeline under its stage, and an opportunity's page says which pipeline it stands in.
- **Found while checking:** the list's foot read "1–3 of 3 opportunitys", and Deliveries' "deliverys". The pager now takes a noun's own plural. And with several cards, each pipeline's Name field had the same id, so every Name label led to the first card's; each card has its own now.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand, a sales team (Wholesale Desk) and a second location (Chattogram Branch), and as a throwaway member who adds opportunities at Chattogram Branch only:
  - Settings → CRM: the starter pipeline narrowed to Wholesale Desk was refused under Switched On with the message above. With no team ticked, the form said "Tick a team, or choose All."
  - Add Pipeline "Wholesale" for Wholesale Desk gave "Pipeline "Wholesale" added." and its card: six stages, Wholesale Desk ticked, and Delete. The starter pipeline had no Delete.
  - "Projects" for Chattogram Branch and "Spare" for everyone were added through the API. Every card then had Delete, the starter's too. Spare was deleted from its card, and the starter lost Delete again.
  - Adding an opportunity at Chattogram Branch with no team offered Sales Pipeline and Projects; with Wholesale Desk, Wholesale too. Moving the location to Main Location took the form from Projects back to Sales Pipeline. Added in Wholesale at Qualified, its page read "Where it stands in Wholesale".
  - Converting a qualified lead of Wholesale Desk offered all three pipelines. It converted into Wholesale at Qualified.
  - The Opportunities page named each row's pipeline. "Sales Pipeline · Proposal" listed one; choosing Wholesale then cleared that stage, listed its two and offered its own stages.
  - Wholesale switched off: "Pipeline saved." and Off by its name, with no Delete, as opportunities were in it. The filter and the form no longer offered it, and its two opportunities stayed listed under it.
  - As the member, the form offered Sales Pipeline and Projects, and an opportunity added in Projects was the only one listed for them. Adding a pipeline was refused (403), and adding into the switched-off Wholesale through the API gave the refusal above.
  - Editing an opportunity showed no Pipeline field.
  - The pipelines, opportunities, lead, contacts, team, location, member, the app's state, the sign-in and the activity were then put back.

#### Fixed on 2026-11-18: the merge with the first PC's own D3b

While the second PC built D3b to F4, the first PC had built an unfinished Phase D3b1 of its own: several pipelines with a default one, a Manage Pipelines and Stages permission, and moving an opportunity to another pipeline. Merging the two (admin `8e07e8f`, frontend `e72c322`) kept the second PC's files where they clashed, but brought the first PC's other parts in beside them:

- `CrmPipelineRepository` declared `delete()` twice, so PHP refused the class. CRM settings, opportunities, converting leads and the test suite all stopped with "Cannot redeclare".
- A second migration numbered 000172, `add_crm_default_pipeline`.
- Change Stage could move an opportunity into any pipeline, skipping the team and location check, and its window offered no stage once loaded.
- Unused classes, components and a permission nothing checked.

The first PC's parts were taken out: both repos are D3b to F4 as the second PC built them. One addition stays: a stage's name in Settings → CRM keeps a minimum width, so a narrow window scrolls the table instead of squeezing the names. The full suite passes (989).

- **On a PC that pulled the merge and migrated before this fix,** `migrate:status` lists `2025_01_01_000172_add_crm_default_pipeline` as Ran. It added `crm_pipelines.is_default` and gave `crm.settings.pipelines` to the roles that manage CRM settings; nothing uses either. To take them out: drop that column, remove that permission from those roles, and delete the migration's row from `migrations`.
- **Checked in the browser** on the first PC after the fix, as `user@izsuite.io` with CRM switched on by hand, and full Sales for F2:
  - D3b: a Wholesale pipeline for a new team, Wholesale Desk, added in Settings → CRM with its card. Add Opportunity offered it only with that team chosen, and a qualified lead of that team converted into it at Qualified.
  - D4: the board showed the opportunity under Proposal at $1,25,000 weighted. Its ⋯ menu's Change Stage moved it to Negotiation ($1,87,500), and the calendar showed it on its closing day in December.
  - E1: a call planned for 08:00 was Overdue. Completed with its outcome and a follow-up, the card listed both, and the Activities page listed what was planned.
  - E2: a call due two minutes later, with "When it is due", reminded on time: in the bell and by e-mail.
  - E3: the opportunity's Timeline, and the customer's with the lead's adding, qualifying and converting.
  - F1: a quotation made from the opportunity, linked both ways. F2: confirming a sales order made from it won it at the order's value, "Sales order SO-00001 confirmed".
  - F3a and F3b: a template filled every placeholder; the e-mail went (to the mail log) and showed Sent.
  - F4: the customer's Opportunities card read 2 open, $1,97,500 weighted, with the lead source.
  - Everything was then put back, the business back on Sales Lite.

**Phase D4: the board and calendar views** (section 17.14) is built, with these defaults. Phase D is done.

- **The Opportunities page** has List, Board and Calendar side by side. The view is kept in the address (`?view=board`), so a reload or a link keeps it. Each shows only the opportunities the user sees, as the list does, and no archived one.
- **The board** is one pipeline's: the first switched on to start, and a Pipeline choice once the business has several - a switched-off one marked "(off)", still showing what is in it. It is searched and filtered by owner as the list is.
  - A column for each stage switched on, in the pipeline's order, and for a switched-off stage while an opportunity is at it, marked Off.
  - An open stage's column holds its open opportunities, the soonest to close first. Won and Lost hold those closed in the last 30 days, the latest first.
  - Each column shows how many it holds and what they are worth in the base currency at today's rates: weighted by probability while open, whole in Won and Lost. Those in a currency without a rate today are left out and counted ("1 without a rate today").
  - A column shows up to 50 cards; the list has the rest.
  - A card shows the name, contact, value and probability, the closing day (or, won or lost, the day it closed), Overdue and Stale, and the owner. Its name opens the opportunity.
- **Moving a card:** an open one is dragged to another open stage's column to move there, at that stage's probability. Dropped on Won or Lost, it opens Mark Won or Mark Lost. Its ⋯ menu offers Change Stage, Mark Won and Mark Lost too, for keyboards and touch screens.
  - Each needs its permission: Change Stages to move, Mark Won or Lost to close. A column the user may not move it to does not take the card. A won or lost card does not move: it is reopened from its page.
  - A refusal is shown above the board, and the card stays: "Proposal needs Customer Requirements filled in first."
- **The calendar** is a month of the list - its search, status, pipeline, stage and owner filters, the open ones to start - each opportunity on its expected closing day. It opens on the business's this month, with Previous, Today and Next, and its weeks start on the day Settings → Regional says.
  - Overdue ones are marked amber, won green and lost grey. A day shows three, and the rest on asking. A month shows up to 500, saying so when there are more.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand, and as a throwaway member who sees their own opportunities and may change their stages but not mark them won or lost:
  - The board's columns held New 2, Qualified 1, Proposal 1 (Overdue), Negotiation 0, Won 1 and Lost 1, with "Weighted $200.00" for New and "Last 30 days $1,500.00" for Won. Open cards could be dragged; won and lost ones could not.
  - Dragging "Uddin Traders - shelving" onto Qualified lit the column up and gave "Moved to Qualified.". With Proposal needing Customer Requirements, a card dropped there gave "Proposal needs Customer Requirements filled in first." and stayed in New.
  - Dropped on Won, "Rahman Stores - racking" opened Mark Won with today and its value; marked, it led the Won column ($4,500.00). Change Stage from a card's menu moved another to Negotiation.
  - Search narrowed the board to the one card. The List view still listed the open ones.
  - The calendar opened on November 2026, Monday first, with today marked and "Karim Mart - counters" amber on the 10th. Next showed December's one, Today came back, and All opportunities added the won (green) and lost (grey) ones on their days.
  - As the member, the board held only their own card, with no Owner filter. Its menu offered only Change Stage; Won and Lost did not take it, and Qualified did. Their calendar held only theirs.
  - At phone width, the board scrolled sideways inside its card and the calendar fitted, with no sideways scroll of the page.
  - The opportunities, the CRM lists and pipeline, the contact, member, the app's state, the sign-in and the activity were then put back.

**Phase E: activities and the timeline** was split on starting it (2026-11-17): E1, activities themselves - their record, completing and cancelling them, a record's Activities card and the Activities page; E2, reminders in the bell and by e-mail, and telling someone given an activity; E3, the timeline of a lead, an opportunity and a contact.

**Phase E1: activities** (section 17.17) is built, with these defaults:

- **An activity** is of one of Settings → CRM's activity types switched on, with a subject, for one lead, opportunity or contact (fixed once planned), given to one member, and due at a day and time in the business's time zone (kept in UTC). Optional: a duration in minutes (5 to 1440), a priority, a place, a description, and for a call (izSuite's Call type) its direction, outgoing or incoming.
  - It can be given to an active member who may view activities - at its record's location, when it has one - or the business's owner. Planning, it is the user's to start.
- **Planned, then completed or cancelled,** both final. A planned one is Overdue once its time has passed.
  - Completing it needs an outcome when its type says so; otherwise the outcome is optional. A follow-up can be planned straight after for the same record: a task for tomorrow at the same time, given to the same person, to start.
  - Only a planned one is changed or cancelled. Cancelling asks first.
- **Settings → CRM's Activity Types** say whether completing one needs an outcome (the Outcome column, Need Outcome / Outcome Optional, and a tick when adding one). izSuite's Call, Meeting, Demonstration and Site Visit need one to start. A type an activity is of is switched off, not deleted.
- **Where they show:**
  - a lead's, an opportunity's and a contact's page has an Activities card: the planned ones, the soonest due first, then those done or cancelled, the latest first, for whoever sees the record and may view activities. Plan Activity there is for that record (not for a converted or archived lead, an archived opportunity or a merged contact);
  - the **Activities page** (CRM's sidebar, after Opportunities): Planned to start - the soonest due first, the overdue marked - or Due today, Overdue, Completed, Cancelled or All; by type; searched by subject or what it is for; My activities to start, or anyone's or a member's for those who see their team's or all records. Its Plan Activity picks what one is for: a lead, an opportunity or a contact the user may see, found by name.
- **Who sees which:** the Activities page shows a member's own - those given to them - and, with View Team Records, their teams' members', or with View All CRM Records every one, at the locations they may use (a contact's, with no location, at any). A record's card shows all of its activities to whoever sees the record.
- **Permissions:** Activities, View and Create and Edit (completing and cancelling included). The Sales User template has both.
- **A lead's activities go with it into its opportunity** on conversion, planned and done, and stay on the lead's card too. A contact with activities is deactivated, not deleted; merging contacts moves them.
- Planning, changing, completing and cancelling one are logged on the record it is for, so they show in its Activity timeline. A change reads in the business's time, with the type and the person by name ("Due at: 2026-11-16 15:00 → 2026-11-19 09:30").
- **Found while checking:** the Activities page's picker said "Search opportunitys"; and a new activity's day and hour came from the browser's clock, not the business's time zone (a business with none set runs on UTC). Both are fixed.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand (the business on UTC, the browser in Dhaka), and as a throwaway member who may view opportunities and plan and complete activities, seeing their own records only:
  - CRM's sidebar listed Leads, Opportunities, Activities and Customers. The Activities page opened on Planned, Any type and My activities, with "Nothing planned".
  - Plan Activity on the Activities page found the opportunity "Uddin Traders - warehouse racking", and planned a call at 10:00, outgoing: "Activity planned.", listed as due "17 Nov 2026, 10:00".
  - On the opportunity's page, Plan Activity had For fixed to it and started at the next full hour in UTC; a meeting planned with a place hid the call-only Direction. Complete on the call waited for an outcome ("A Call needs its outcome once it is done."); with one and a follow-up ticked - a task for tomorrow at 10:00, filled in - it gave "Activity completed, and its follow-up planned." The card then listed the meeting, the task, and the call as Completed with its outcome.
  - Edit moved the meeting to 08:30 ("Activity saved."). The opportunity's Activity timeline listed each step, the edit as "Due at: 2026-11-17 07:00:00 → 2026-11-17 08:30:00" (since read in the business's time, as above).
  - On the Activities page, Cancel Activity asked first, then gave "Activity cancelled."; All activities showed the follow-up, the completed call with its outcome and the cancelled meeting.
  - Settings → CRM's Activity Types showed the Outcome column; Need Outcome on Task gave "Saved." and Needed.
  - A lead's email activity went with it into its opportunity on conversion, showing on both pages; the converted lead's card had no Plan Activity. The contact's page had the card, the width of the page.
  - As the member, the sidebar's CRM showed Opportunities and Activities, and the Activities page only the site visit given to them, Overdue, with no Assigned to filter. They could plan only for opportunities (none of their own to pick), not contacts (403); the owner's opportunity's activities were not found for them; and completing the site visit asked for its outcome first ("A Site Visit needs its outcome once it is done."), then gave "Activity completed."
  - The activities, opportunities, lead, contacts, CRM lists, member, the app's state, the sign-in and the activity were then put back.

**Phase E2: reminders, and telling someone given an activity** (section 17.17) is built, with these defaults:

- **A new notification kind, Activities,** in Settings → Notifications & Email and in each user's own, in the bell and by e-mail to start, each switched off as the other kinds are.
- **Given an activity:** whom one is given to hears of it - "Rahim Uddin gave you an activity: Call "Discuss the quotation"", "Due 18 Nov 2026, 10:00, for Uddin Traders - racking" - when it is planned, given to someone else, or planned as a follow-up. Not when they gave it to themselves, and not when it is saved unchanged.
- **Reminders:** each activity has a Reminder - no reminder, when it is due, or 5, 15 or 30 minutes, 1 hour or 1 day before; 15 minutes before to start, for a follow-up too.
  - Its assignee is reminded once at that moment: "Reminder: Call "Discuss the quotation", due 18 Nov 2026, 10:00", "For Uddin Traders - racking".
  - Moving it to another time, or changing the reminder, reminds again at the new moment. A completed or cancelled one is not reminded, and one planned after its time - a call logged once made - has none.
  - Not to someone who may no longer be given activities, and not while CRM is off.
  - `crm:remind-activities` sends them each minute, from the scheduler; the queue sends the e-mails.
- A notice leads to the activity's record when its person sees it, else to the Activities page. Its day and time are the business's, in its date and time formats.
- The activity's form has Reminder below the due time, and a planned one's line says "Reminder 15 minutes before".
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand (the business on UTC), with a throwaway member who may view and plan activities:
  - Plan Activity on the opportunity's page started with Reminder "15 minutes before". A call at 07:35 with "When it is due" was listed as "Reminder when it is due".
  - A site visit given to the member put "izSuite User gave you an activity: Site Visit "Measure the warehouse"" in their bell, with "Due 18 Nov 2026, 11:00, for Uddin Traders - warehouse racking", leading to the Activities page (they do not see the owner's opportunity).
  - At 07:35:01 the dev scheduler ran `crm:remind-activities`, and the owner's bell showed "Reminder: Call "Call Rahim about the racking", due 17 Nov 2026, 07:35", "For Uddin Traders - warehouse racking", leading to the opportunity.
  - Both notices' e-mails were sent: their deliveries read sent, and the dev mail log held them.
  - Settings → Notifications & Email listed Activities, on in the bell and by e-mail; so did the user's own.
  - The activities, opportunity, contact, CRM lists, member, notifications, the app's state, the sign-in and the activity were then put back.

**Phase E3: the timeline of a lead, an opportunity and a contact** (section 17.18) is built, with these defaults. Phase E is done.

- **One timeline, newest first,** of what happened with the record:
  - **activities done or cancelled:** a call with its direction (incoming or outgoing), how long it took and its outcome; a meeting, task, e-mail or any other type with theirs; and who ended it. What is still planned stays on the Activities card;
  - **notes:** the comments on a lead or an opportunity, with the people they mention;
  - **changes,** from the Activity Log: added, changed, given to someone, moved between stages, won, lost, reopened, archived, converted - each marked by what it was (a stage move, a new owner, won, lost, another status), with the values it changed;
  - **files** attached and removed;
  - and on a contact's, **their sales documents** - quotations, proforma invoices, sales orders, invoices and credit notes - each opening its page, with its status, and its total for those who see Sales' values. Only the kinds the user may view, at the locations they may use.
- **Filters:** All, Activities, Notes, Changes, Files, and on a contact's Documents. Activities shows for those who may view activities, Documents for those who may view a kind of sales document.
- **Where:** a lead's and an opportunity's Comments card has Timeline as its second tab, instead of Activity. A contact's page has a Timeline card, the width of the page, below its Activities card, while CRM is on.
- **A contact's** gathers their own activities and changes, and those of their opportunities and of the leads converted into them - only those the user sees (their own, their teams', or all, at their locations). An activity or a note there names the opportunity or lead it is about, with a link. A contact has no comments of their own.
- **An activity's steps** - planned, changed, completed, cancelled - stay in Settings → Activity Log, now naming the activity. The timeline shows the activity itself instead, as it shows a comment instead of "Commented on …".
- **The newest 100** of each part, and of all together, are shown.
- **The team's own:** the timeline and its notes go on no document, PDF, e-mail or customer link.
- **Who sees it:** whoever sees the record - a lead with View Leads, an opportunity with View Opportunities, each as they see it, and a contact with View Contacts - with its activities only for those who may view activities. With CRM off its records stay readable, as an app's do once turned off, but its pages hide.
- **Found while checking:** an activity read "Call Call Rahim …", a cancelled one said Cancelled twice, and a draft document said Draft twice ("Draft Quotation", "Draft"). The type is now a pill, as on the Activities card; a cancelled one's line says "Cancelled by …"; a draft has no status pill. Links in an entry now look like links.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand, and as a throwaway member who may view contacts and their own opportunities:
  - An opportunity for "Browser Check Traders" had a call done (outgoing, 15 minutes, with its outcome), a meeting cancelled, a comment, a move to Proposal and a PDF attached. Its Timeline tab listed, newest first and each with its icon: the file, the move ("2 changes"), the note, the cancelled meeting, the call ("Outgoing · 15 min", its outcome, "Done by izSuite User"), and the opportunity being added. Activities, Notes, Changes and Files each narrowed it.
  - The contact's page had the Timeline card: a draft quotation opening its page, with $1,800.00; the contact's own e-mail, done; and the opportunity's entries, its note "on" and its activities "for" the opportunity, each linking to it. Its filters included Documents.
  - A lead's Timeline tab listed its task done with its outcome, and the lead being added.
  - The quotation's own Activity tab still listed its steps, an edit with "1 change", through the change list both timelines now share.
  - As the member, the contact's Timeline card offered All, Notes, Changes and Files - no Activities card, no Documents - and said "Nothing has happened here yet."; the owner's opportunity's timeline was not found for them, and a lead's was refused.
  - The opportunity, lead, activities, quotation, comment, file, contact, CRM lists, member, the app's state, the sign-in and the activity were then put back.
**Phase F: Sales and the contact's profile** was split on starting it (2026-11-17): F1, sales documents made from an opportunity; F2, an opportunity won when its order is confirmed; F3, e-mail from a lead, an opportunity or a contact; F4, the CRM part of a contact's profile.

**Phase F1: sales documents made from an opportunity** (section 17.27) is built, with these defaults:

- **Create, on an opportunity's page:** a new Sales Documents card offers Quotation, Proforma Invoice, Sales Order (with the full Sales app) and Invoice - the kinds the user may create - while the opportunity is open or won and not archived. A lost one says to reopen it, an archived one to restore it. Without Sales, the card says to turn it on, linking to Sales in the Apps Center for whoever may open it.
- **The document starts with the opportunity's** contact, currency, location (where the user may use it), owner as the salesperson and sales team (with the full Sales app), and a line for each interested item: the item at its sales price and tax, or the name typed in, unpriced. The form says it is made from the opportunity, with a link, and its back link leads there.
- **Linked to it** (`opportunity_id`): the document, and what is made from it in turn - a quotation's invoice, an order's invoices and deposit invoices. A duplicated order is linked to nothing. The link is set when the document is made; saving it again leaves it.
- **Made only** from an opportunity the user sees, while CRM is on, for its contact ("A document made from an opportunity is for its contact."), and never from a lost or archived one.
- **Its value stays its own:** however many quotations it has, they add nothing to its value, the pipeline or the forecast. The card shows each document's own total.
- **The card** lists the documents made from it, newest first - title, date, status and total - of the kinds the user may view, at the locations they may use, the total for those who see Sales' values.
- **A document's page** names its opportunity under History, for whoever sees the opportunity while CRM is on.
- **The timeline** - an opportunity's now, as a contact's - shows each document when it was made, and again when it was sent (a quotation or proforma), issued (an invoice or credit note) or confirmed (a sales order, by whom). An opportunity's timeline has Documents among its filters too.
- **Found while building:** Phase E3 had given sales documents a second `creator()` relation beside their `HasAuthors` one; it is gone.
- **Found while checking:** the form's note had no style (the shared stylesheet had no `alert-info`), a sent or issued entry on the timeline repeated its status as a pill, and the card's long subtitle pushed Create below it. All three are fixed.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand, and as a throwaway member who sees every opportunity and views invoices only, without Sales' values:
  - An opportunity for "Browser Check Traders", interested in 4 steel racks and "Installation" typed in, had the Sales Documents card: "None made from it yet.", with Create offering Quotation, Proforma Invoice and Invoice (Sales Lite).
  - Create → Quotation opened the form with "Made from the opportunity Browser Check Traders - racking, and linked to it.", the customer, USD, the racks at 450.00 ($1,800.00) and Installation unpriced; its back link said Opportunity. Priced and saved with Save and Mark as Sent: "Quotation QUO-00001 marked as sent.", its History naming the opportunity.
  - Convert to Invoice made a draft invoice naming the quotation and the opportunity; issued as INV-00001, it stayed linked.
  - The card listed Invoice INV-00001 ($2,000.00, Issued) and Quotation QUO-00001 ($2,000.00, Converted). The Timeline's Documents listed the invoice issued and made, and the quotation sent and made, newest first.
  - As the member: the card listed only the invoice, with no total and no Create; the invoice's page named the opportunity; the quotation was refused them; their Documents timeline had the invoice's two entries, without totals.
  - The opportunity, item, documents, contact, CRM lists, member, the app's state, the sign-in, the numbering and the activity were then put back.
**Phase F2: an opportunity won when its order is confirmed** (section 17.27) is built, with these defaults:

- **Settings → CRM** has "Win an opportunity when its sales order is confirmed", below the operating mode: on to start.
- **When a sales order made from an opportunity is confirmed** (the full Sales app's), while CRM is on and the setting is on, the opportunity - if it is still open, and not archived - is won:
  - that day, in the business's time, at the order's value before tax when they share a currency, else at its own value;
  - with the reason "Sales order SO-00001 confirmed", going to its pipeline's won stage at 100%;
  - by whoever confirmed the order, whether or not they see the opportunity or may close one. Its timeline says "Marked opportunity … won: sales order SO-00001 was confirmed".
- **Left as it is:** one already won, lost or archived; one whose won stage needs details it lacks, or whose pipeline has no won stage switched on; one someone else changed at that moment. Nothing about the opportunity stops the order being confirmed.
- **Afterwards:** cancelling, holding or completing the order changes nothing on the opportunity; it is reopened by hand. Sales Lite has no sales orders, so there an opportunity is won by hand.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand and the business's Sales Lite swapped for the full Sales app, and as a throwaway member who may only view and confirm sales orders:
  - Settings → CRM showed the setting ticked. Unticked and saved, then ticked again: "CRM settings saved." each time.
  - On an opportunity worth $2,000.00, Create offered Sales Order too. Its form, made from the opportunity, was priced at 4 racks × 450.00 ($1,800.00); Save and Confirm gave "Sales order SO-00001 confirmed."
  - The opportunity then read Won, 100%, "Won 17 Nov 2026, marked by izSuite User", Final Value $1,800.00, Won Reason "Sales order SO-00001 confirmed". Its timeline put the win - a trophy, in green - above the order's confirmation and making.
  - The member, refused the opportunity's page (403), confirmed SO-00002 of a second one: "Sales order SO-00002 confirmed.", naming no opportunity to them. That opportunity was won at 850.00, marked by them.
  - The opportunities, orders, contact, CRM lists and settings, member, the apps (Sales Lite back), the sign-in, the numbering and the activity were then put back.
**Phase F3: e-mail from a lead, an opportunity or a contact** was split on starting it (2026-11-17): F3a, sending one, with the record's documents and files, kept on its timeline; F3b, e-mail templates.

**Phase F3a: e-mail from a lead, an opportunity or a contact** (section 17.19) is built, with these defaults:

- **Send E-mail** is on the record's timeline - a lead's and an opportunity's Timeline tab, a contact's Timeline card - for whoever may send e-mail (a new permission, CRM → E-mail: Send E-mail, in the Sales User template) and sees the record. Not from a converted or archived lead, an archived opportunity or a merged contact.
- **The form** starts with the record's address - a lead's own, an opportunity's contact's, a contact's - and a message with a greeting to its person (else its name) and the sender's and business's names. The subject and message are the user's; Cc takes up to five addresses.
- **Attach:** the record's sales documents that have gone out - an opportunity's made from it, a contact's - but no draft, void or cancelled one, of the kinds the user may view, as PDFs made as it is sent; and its files. Up to five of each, the files 15 MB together. A lead has no documents.
- **It goes** from the queue as document e-mails do: from the platform's address under the business's sender name, with replies to the business's reply-to address (Settings → Email). Tried three times, it then says Not Sent, and why in a word.
- **Kept on the timeline,** under a new E-mails filter: its subject, Sending, Sent or Not Sent, to and Cc, what it carried, its message folded, and by whom. On a contact's, an opportunity's or a lead's e-mail names it. The log entry naming it stays in Settings → Activity Log, not shown twice.
- **Contacts:** merging moves a contact's e-mails, and one with e-mails is deactivated, not deleted. A lead's stay with it once converted, and show on its contact's timeline through it.
- **Replies and synchronisation** wait for an e-mail integration: replies go to the business's own inbox.
- **A slip:** the dev database was backed up just after migration 000177 ran - it only adds `crm_emails` - not before it, as the rules ask. Nothing was at risk.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand, and as a throwaway member who sees every opportunity but may not send e-mail:
  - An opportunity for "Browser Check Traders" (Rahim Uddin, rahim@browsercheck.test) had a quotation made from it and sent (QUO-00001), and a PDF attached. Its Timeline tab had E-mails among its filters, and Send E-mail.
  - The form started with rahim@browsercheck.test and "Dear Rahim Uddin, … Kind regards, izSuite User, Billing Starter", offering "Quotation QUO-00001 (PDF)" and "floor-plan.pdf (44 bytes)". Sent with a subject, a copy address and both: "E-mail to rahim@browsercheck.test is on its way."
  - The dev queue sent it in 14 seconds, mostly the PDF's making. The dev mail log held it: to Rahim, copied to accounts@browsercheck.test, replies to Billing Starter, carrying Quotation-QUO-00001.pdf and floor-plan.pdf. The timeline went from Sending to Sent, with its recipients, attachments and message; Changes did not list it again.
  - The contact's Timeline card had Send E-mail, and showed the e-mail "for Browser Check Traders - racking", linking to it.
  - As the member: no Send E-mail, and the API refused one (403); they saw the e-mail, Sent.
  - The e-mail, opportunity, quotation, file, contact, CRM lists, member, the app's state, the sign-in, the numbering and the activity were then put back.
**Phase F3b: e-mail templates** (section 17.19) is built, with these defaults. Phase F3 is done.

- **Settings → CRM** has a new E-mail Templates card, below the lists: each template a name - the business's only one by it - a one-line subject and a message, added, edited and deleted by whoever manages CRM settings. A business starts with none.
- **Placeholders:** `{contact_name}` (the person written to - or the business, without one), `{company_name}` (the business written to), `{opportunity_name}` (from an opportunity; nothing from a lead or a contact), `{user_name}` (who sends it) and `{business_name}` (the business's sender name). A chip puts one in the message where the cursor is; one the template does not know stays as written.
- **On Send E-mail,** a Template list - "Write my own" to start - puts the chosen one's subject and message in, filled in for the record and the sender, to change before it goes. Whoever may send e-mail uses them, whether or not they manage settings.
- **An e-mail keeps the text it went with:** changing or deleting a template changes no e-mail sent.
- **Found while checking:** the template form's placeholder chips broke Settings ("500 - Vite Error"): a template literal's `{…}` inside `{{ }}` closed the tag early. The chips are now made by a small function, as Settings → Email's are.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand, and as a throwaway member who may send e-mail but not manage CRM settings:
  - Settings → CRM's E-mail Templates said "None yet. …". Add Template - `{contact_name}` put in by its chip - gave "Template added."; Edit, "Template saved."; another by the same name was refused ("The business has a template by that name already.").
  - On an opportunity for "Browser Check Traders", Send E-mail's Template list offered "Write my own" and "Quotation follow-up". Choosing it gave "Your quotation for Browser Check Traders - racking, Browser Check Traders", and "Dear Rahim Uddin, How did Browser Check Traders find our quotation? … Kind regards, izSuite User, Billing Starter". Sent, the dev queue sent it.
  - As the member: no CRM tab in Settings, and adding a template was refused (403); their form offered the template, signed with their own name.
  - Delete gave "Template deleted.", and the card was empty again.
  - The template, e-mail, opportunity, contact, CRM lists, member, the app's state, the sign-in and the activity were then put back.
**Phase F4: the CRM part of a contact's profile** (section 17.20) is built, with these defaults. Phase F is done.

- **A contact's page** has an Opportunities card, while CRM is on, for whoever may view opportunities or leads - below their sales documents, payments and balance, above the Activities and Timeline cards:
  - **Open, Won and Lost:** how many of each; the open ones' value and weighted value, and the won ones' value, in the base currency at today's rate - an estimate, as the forecast's. One in a currency without a rate today is left out of the totals, and counted.
  - **Lead source:** the source of the first lead converted into them, linking to the lead - else that of their first opportunity with one.
  - **Their opportunities:** the open ones by their expected closing day, then the won and lost, the latest ended first, each with its stage and probability, value and day, opening its page. Archived ones are left out, as the lists leave them; up to 50.
  - Only what the user sees: their own records, their teams' or all, at their locations. Without View Leads, there is no lead source from a lead.
- **Add Opportunity** on the card - for whoever may create opportunities, while the contact is active and not merged - opens the form with them chosen.
- Their planned activities (Phase E1) and timeline (Phase E3) were on the page already, beside their sales documents and balance.
- **Found while checking:** the card's long subtitle pushed Add Opportunity below it; it is shorter now.
- **Checked in the browser** on the second PC, as `user@izsuite.io` with CRM switched on by hand, and as a throwaway member who views contacts and the opportunities they own:
  - "Browser Check Traders" had a lead, Karim Ahmed from Website, converted into them, and opportunities open, won, lost and archived. The Opportunities card, between Payments Received and Activities, read Open 2 ($2,900.00, weighted $290.00), Won 1 ($1,500.00), Lost 1, and "Lead source: Website · converted from the lead Karim Ahmed". It listed racking (closing 20 Dec 2026), shelving (31 Dec), lockers (lost 17 Nov) and chairs (won 17 Nov) - not the archived one.
  - Add Opportunity opened the form with Browser Check Traders chosen; saved, the opportunity was theirs.
  - As the member, the card showed only the one given to them (Open 1, $600.00, weighted $60.00), with no lead source and no Add Opportunity.
  - The opportunities, lead, contact, CRM lists, member, notifications, the app's state, the sign-in and the activity were then put back.

**Phase G** was split on starting it (2026-11-18): G1, the CRM Overview; G2, the Forecast page; G3, response tracking and the follow-up warnings; G4, the Dashboard's CRM figures.

**Phase G1: the CRM Overview** (sections 17.3 and 17.4) is built, with these defaults:

- **Overview comes first in CRM's sidebar,** for whoever may view opportunities. Its lead figures show to those who may view leads too, while the business uses leads.
- **The figures,** each counting only what the user sees - their own records, their teams' or all, at their locations:
  - New Leads: added in the period.
  - Leads Awaiting Follow-up: open leads (New or Assigned) not contacted yet.
  - Qualified Leads: qualified, not yet converted.
  - Conversion Rate: of the leads added in the period, the share converted so far.
  - Open Opportunities, with the Pipeline Value (their expected value) and the Weighted Forecast (expected value × probability).
  - Opportunities Closing Soon: open, expected to close within the closing-soon period.
  - Won Opportunities and Lost Opportunities: ended in the period, how many and their value.
  - Overdue Activities: planned ones past their due time.
- **The period** (This Month to start) counts the new leads, the conversion rate and the won and lost; the others are as they stand now. Archived opportunities are left out.
- **The filters:** the period, location, sales team, owner, lead source, pipeline, stage and currency.
  - Location, team, owner and source narrow the leads and opportunities. The owner and location narrow the activities too, an activity's owner being whom it is given to.
  - Pipeline and stage narrow the opportunities only.
- **Values** are in the base currency at today's rates, or in the currency chosen, counting only those in it. Those in a currency without a rate today are left out of the totals, and counted.
- **Labelled as estimates:** pipeline value, weighted forecast and won value are not sales and are never counted as invoiced Sales (17.4).
- **Settings → CRM:** the closing-soon period, 30 days to start (1 to 365). Phase G3 adds the follow-up periods beside it.
- **Overdue Activities** opens the Activities page on its overdue ones, for the owner chosen or anyone the user sees.
- **Found while checking:** the Activities page always opened on Planned and My activities, whatever the link said. It now takes its status and whose from the address, and opens as before without them. The Overview's cards share the Sales Overview's styles, which moved to `main.css`.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - CRM's sidebar began with Overview. With three leads added that day (one qualified, one converted), opportunities open at New, Qualified and Proposal, one won and one lost that day, and a call overdue since yesterday, it read:
    - New Leads 3, Awaiting Follow-up 1 (amber), Qualified 1, Conversion Rate 33.3% ("1 of the 3 leads added converted");
    - Open 3, Pipeline Value $3,90,000.00, Weighted Forecast $1,48,000.00, Closing Soon $2,50,000.00 (1 within 30 days), Won $40,000.00, Lost $15,000.00, Overdue Activities 1.
  - With one location, team, pipeline and currency, only the period, Owner, Source and Stage showed. Stage Proposal gave 1 open, $1,25,000.00 weighted, and nothing won, leaving the leads as they were.
  - October, as a custom range: no new lead ("—", "No lead added in the period"), nothing won or lost, while the open ones and the overdue call stayed.
  - Overdue Activities opened the Activities page on Overdue, Anyone, with the call. Opened from the sidebar, the page still started on Planned, My activities.
  - Settings → CRM's Closing Soon, days, saved as 120 ("CRM settings saved."), and Closing Soon then counted all three ($3,90,000.00).
  - The leads, opportunities, activity, CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase G2: the Forecast page** (sections 17.3 and 17.14) is built, with these defaults:

- **Forecast comes after Activities in CRM's sidebar,** for whoever may view opportunities.
- **The open opportunities' weighted value** (expected value × probability) with their count and expected value, by expected closing month - this month and the next 11, those already past under Overdue - or by owner, sales team or stage. The won value of each month stands beside it.
  - Every month of the 12 shows, empty or not; Overdue, and Later (closing after the 12 months, so the total is every open one), only while they hold something.
  - Won This Month is what was won this month: this month's row, the owner's or team's, or the won stage's.
- In the base currency at today's rates, an estimate (those without a rate today are left out and counted), or in the currency chosen. The Overview's filters, but the period, apply.
- It is no cash-flow or financial forecast (17.14).
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - CRM's sidebar read Overview, Leads, Opportunities, Activities, Forecast, Customers.
  - With opportunities closing on 5 and 28 November, in January 2027 and in February 2028, and one won that day, by closing month it read:
    - Overdue 1, $60,000.00, weighted $15,000.00 (amber);
    - November 2026 1, $2,50,000.00, $1,25,000.00, won $40,000.00;
    - January 2027 1, $80,000.00, $8,000.00;
    - Later 1, $1,20,000.00, $90,000.00, the empty months quiet between them;
    - total 4, $5,10,000.00, $2,38,000.00, $40,000.00.
  - By stage: New, Qualified, Proposal, Negotiation, then Won holding the $40,000.00. By owner: one row, as one person owned them all. Stage Proposal, by month: November alone.
  - The Overview's open opportunities, pipeline value, weighted forecast and won value matched the total.
  - The opportunities, the app's state, the sign-in and the activity were then put back.

**Phase G3: response tracking and the follow-up warnings** (section 17.23) is split in two: G3a tracks it on each record's page, G3b warns on the Overview.

**Phase G3a: response tracking on a lead's and an opportunity's page** is built, with these defaults:

- **A lead's page gains a Response and Follow-up card,** after its details:
  - when it was added, and by whom;
  - when it was first given an owner - and, once given to someone else, to whom and when;
  - when it was first contacted, with its response time from when it was added;
  - its last contact, its next activity, and its days without activity.
  - Added, Assigned and Contacted left the card before it, now called Source and Owner.
- **First contacted** is the first time anyone answered the lead: marked it contacted, completed an activity for it, or sent it an e-mail from izSuite. Marking it contacted again, or a later activity or e-mail, does not move it.
- **The last contact** is the latest of the activities done, the e-mails sent - from the moment each was sent, whatever the mail server did with it - and, for a lead, its latest marking as contacted. The card says which it was.
- **The next activity** is the soonest planned one, overdue ones first, with an Overdue pill. Its type and subject show to whoever may view activities; to anyone else, only when it is due.
- **Days without activity** are full days since the last contact, or since the record was added. Only while it is open: not for a converted, unqualified or archived lead, nor a won, lost or archived opportunity.
- **An opportunity's page gains a Follow-up card,** after its stages: its last contact, next activity and days without activity. Its time at its stage stays on its Stages card (Phase D3a).
- The card reads the record again when an activity is planned, changed, completed or cancelled on the page, or an e-mail is sent from its timeline. Nothing on the record's form changes, so someone editing it still saves.
- Leads that exist get their first assignment and first contact from what they kept: the Activity Log's assignments and markings, their activities done and their e-mails.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - A lead added to the queue showed Added with its time and adder, "Not yet: the lead is in the queue", First Contacted "Not yet" and Next Activity "None planned" (amber), Last Contact "None yet", and under a day since it was added.
  - Planning a call from its Activities card put the call and its time under Next Activity without reloading the page.
  - Completing it with an outcome gave First Contacted with "Response time: 2 minutes", Last Contact "An activity done", and Next Activity "None planned".
  - Assigning it filled First Assigned. An e-mail from its timeline moved Last Contact to "An e-mail sent", and First Contacted stayed.
  - An opportunity with a call due that morning showed the call with an Overdue pill, Last Contact "None yet", and under a day since it was added.
  - The lead, the opportunity, the activities, the e-mail, the app's state, the sign-in and the activity were then put back.

**Phase G3b: the follow-up warnings on the Overview** is built, with these defaults:

- **The Overview's Activities group is now Follow-up,** six cards counting what stands now, each amber while it counts any and opening its list:
  - **Unassigned Leads:** those in the queue (New), on the Leads page's New (the queue);
  - **Leads Not Contacted:** open leads never contacted (as Phase G3a counts it) and added longer ago than the lead response period, 2 days to start - on a new Leads status, "Not contacted within 2 days";
  - **Overdue Activities**, as in Phase G1;
  - **No Next Activity:** open opportunities with no activity planned (an overdue one is still planned) - a new Opportunities status;
  - **Stale Opportunities:** at their stage longer than its stale period (Phase D3a), on the existing Stale status;
  - **Closing Soon, Gone Quiet:** open ones expected to close from today to the end of the closing-soon period that have gone without activity for the recent-activity period, 14 days to start. That means added before it, with no activity done and no e-mail sent since, as Phase G3a's days without activity count. A new Opportunities status, "Closing soon, no activity in 14 days".
- The lead cards show while the business uses leads and the user may view them. Each counts only what the user sees, and the Overview's filters narrow them.
- **A link keeps the Overview's owner, source, pipeline and stage,** the filters the lists have. Their location and team filters the lists do not have.
- **Settings → CRM:** Contact New Leads Within, days (while the business uses leads) and Recent Activity, days, beside Closing Soon, days; each from 1 to 365.
- The new statuses work on the Opportunities calendar too, whose toolbar is the list's.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - The check data: two leads added four days ago (one in the queue) and one that day; an opportunity closing in ten days and one at New with New stale after 7 days, both added 20 days ago; and one with a call planned. The dates were set back in the database.
  - The Overview's Follow-up group read Unassigned Leads 1, Leads Not Contacted 2 ("Not contacted within 2 days of being added"), Overdue Activities 0, No Next Activity 2, Stale Opportunities 1, and Closing Soon, Gone Quiet 1 ("Closing within 30 days, no activity in 14 days"). The five non-zero cards were amber.
  - Leads Not Contacted opened the Leads page on "Not contacted within 2 days" with the two leads, newest first.
  - Closing Soon, Gone Quiet opened the Opportunities list on "Closing soon, no activity in 14 days" with the quiet one. No next activity listed the quiet and the stale ones (with its "Stale, 20 days" pill), not the one with a call.
  - Settings → CRM showed Closing Soon 30, Contact New Leads Within 2 and Recent Activity 14, with their hints. Saving 5 for the leads ("CRM settings saved.") put Leads Not Contacted at 0, "within 5 days".
  - The leads, opportunities, activity, CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase G4: the Dashboard's CRM figures** (section 17.28) is built, with these defaults. Phase G, the Overview, Forecast and follow-up, is done.

- **The Dashboard gains a CRM card,** after the summary cards and the Sales Pipeline, while CRM is on, for whoever may view opportunities. It shows with or without Sales, Purchases and Expenses on. Its subtitle says it is not sales: operational CRM information, never in the sales totals.
- **Its eight figures** are counted by the Overview's own code, only what the user sees, each opening the list it counts:
  - **New Leads:** added in the Dashboard's period (This Month to start), on the Leads page's All leads, newest first;
  - **Leads Awaiting Follow-up:** New or Assigned, not contacted yet, on a new Leads status, "Awaiting follow-up";
  - **Open Opportunities**, on the Opportunities list as it opens;
  - **Activities Due Today:** planned for the business's today, one overdue since this morning among them, on the Activities page's Due today, Anyone;
  - **Overdue Activities**, on its Overdue, Anyone;
  - **Opportunities Closing Soon:** expected to close within the closing-soon period (30 days to start), on a new Opportunities status, "Closing within 30 days", which the calendar takes too;
  - **Pipeline Value** and **Weighted Forecast**, in the base currency at today's rates (those without a rate today left out and counted), on the Forecast page, whose total shows both.
  - Awaiting follow-up and overdue activities are amber while they count any.
- The lead figures show while the business uses leads, to whoever may view leads; the activity figures to whoever may view activities. New leads count the period; the rest stand as they are now.
- **The Dashboard's location narrows them all.** The lists have no location filter, so with a location chosen a list can show more than its figure.
- **Found while building:**
  - The Overview's Overdue Activities showed to roles that may not view activities, opening a page they could not load. It now shows only to those who may, as on the Dashboard.
  - The Activities page was missing from the pages a role must be allowed: a member without View Activities who typed its address got an error. They are now taken to their first page, as on the other CRM pages.
  - The Sales Pipeline's figure styles moved to `main.css`, shared with the CRM card, at most four across.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - The check data: three leads added that day (one in the queue, one assigned, one contacted since); opportunities at Proposal ($1,00,000.00, closing in ten days) and Qualified ($2,40,000.00, closing in February); a call due that evening and one overdue since yesterday.
  - The Dashboard's CRM card, after the summary cards, read New Leads 3, Leads Awaiting Follow-up 2 (amber), Open Opportunities 2, Activities Due Today 1, Overdue Activities 1 (amber), Opportunities Closing Soon 1 ("Expected to close within 30 days"), Pipeline Value $3,40,000.00 and Weighted Forecast $1,10,000.00. At 1440 pixels wide it showed two rows of four.
  - Leads Awaiting Follow-up opened the Leads page on "Awaiting follow-up" with the two. The Opportunities list on "Closing within 30 days" held the one, and the Activities page on Due today, Anyone, the evening call. The Forecast's total read 2, $3,40,000.00 and $1,10,000.00.
  - The CRM Overview's figures matched, Overdue Activities 1 among them.
  - The leads, opportunities, activities, CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase H: the CRM reports** (section 17.29) was split on starting it (2026-11-18): H1, the lead reports; H2, the opportunity reports; H3, people and activity. In all three:

- **A CRM group on the shared Reports page,** while CRM is on, each report counting only what the user sees.
- **A new permission, CRM Reports: View CRM Reports** (17.30, as the gap check planned), which brings Reports' View with it. A report needs it and the view of what it counts. Roles made from the Manager template get it; give it to custom roles in Settings → Users & Roles.
- Each report has the Reports page's period and exports, and the Overview's filters that apply: location, sales team, owner, lead source, pipeline and stage.
- Values are in the base currency at today's rates, labelled estimates, never sales: financial sales come only from issued invoices (17.29).
- **Campaign-to-Opportunity Results** waits for campaigns (Doc 21, Marketing). Until then, Leads by Source shows where leads came from.

**Phase H1: the lead reports** is built, with these defaults:

- **Six reports,** while the business uses leads, for whoever may view CRM reports and leads. Each counts the leads added in the period (This Month to start), narrowed by location, sales team, owner and lead source:
  - **Leads by Source**, by the source each came with, which stays when a lead is reassigned or converted (17.8);
  - **Leads by Owner**, by whom each is given to now, No owner being the queue;
  - both split the leads into Being Worked (new, assigned or contacted), Ready to Convert (qualified), Converted and Closed (unqualified or archived), with the Conversion Rate;
  - **Leads by Status:** every status in order, with its share;
  - **Lead Response Time:** contacted, contacted within the lead response period (Settings → CRM, 2 days to start), not contacted yet, the average response in hours (from added to first contacted, Phase G3a) and the share answered in time;
  - **Lead Qualification Rate:** qualified (qualified now, or converted since), unqualified, being worked and archived, and the share qualified;
  - **Lead Conversion Rate:** qualified, converted, the share converted, and the share of the qualified that were converted.
  - The last three group by month (every month of the period, quiet ones too), lead source, owner or sales team.
- The Owner filter shows to whoever sees more than their own leads; Sales Team while the business has teams.
- Their rows open nothing: the Leads list has no date filter that could show the same leads.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - The check data: four leads added in November and one unqualified in October, their dates set back in the database. Website had one in the queue and one contacted 4 hours after being added; Referral one qualified, contacted after 4 days, and one converted, contacted after 2 hours.
  - The Reports page ended with a CRM group of the six.
  - Leads by Source read Referral 2 (1 ready to convert, 1 converted, 50.0%) and Website 2 (2 being worked, 0.0%), total 4 and 25.0%. Its filters were Range and Owner: dev has no teams and one location.
  - Lead Response Time read November: 4 leads, 3 contacted, 2 within 2 days, 1 not contacted, 34 hours on average and 50.0% in time. Grouped by Lead Source from its Group By: Referral 49 hours, Website 4.
  - For this year, Lead Qualification Rate had a row for each month: October 1 lead and 0.0%; November 4, 2 qualified and 50.0%; total 40.0%. Lead Conversion Rate read November 25.0%, 50.0% of the qualified, and 20.0% in total.
  - Leads by Status and Leads by Owner matched, and the CSV and PDF downloaded. The role editor listed CRM Reports: View CRM Reports.
  - The leads, CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase H2: the opportunity reports** is built, with these defaults:

- **Eight reports,** while CRM is on, for whoever may view CRM reports and opportunities. Each counts the opportunities the user sees, archived ones left out as on the Overview.
  - Where it applies, each is narrowed by location, sales team, owner, lead source, pipeline (while there are several) and stage.
  - Values are in the base currency at today's rates; those without a rate today are left out and counted.
- **Opportunities by Stage**, as they stand now: every open stage in order, quiet ones too. For each: how many, their expected and weighted value, their average days at the stage, and how many are stale.
- **Pipeline Value** and **Weighted Forecast**, as the Forecast page works them out: the open ones by stage (Pipeline Value's to start), closing month (Weighted Forecast's), owner or sales team.
  - Pipeline Value has each group's share.
  - Weighted Forecast has the weighted value and what was won this month.
- **Stage Duration:** at each open stage:
  - the visits that ended in the period (moved on, won or lost), with their average and longest days;
  - those there now, with their days so far.
- **Opportunities Closing Soon:** the list of those expected to close within the closing-soon period, the soonest first. Each has its days left, probability, and values in its own currency; the sums in the base currency are above.
- **Won and Lost Opportunities:** the list of those closed in the period, the latest first, with a lost one's reason and competitor. What was won and lost is above.
- **Win Rate:** of those closed in the period, by month (every month of the period), owner, sales team or lead source: won, lost, and the share won by number and by value.
- **Lost Reasons:** those lost in the period by reason, with each reason's share, value and most-named competitor.
- The lists' rows open their opportunity.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - The check data, four opportunities at Bose Electronics:
    - Bose racking at Proposal ($1,00,000.00, closing in ten days), moved from New after four days, its dates set back in the database;
    - Bose shelving at Qualified ($2,40,000.00, closing in February);
    - Bose cabling, won on 10 November ($30,000.00);
    - Bose wiring, lost on 12 November for Price to Rival Co ($20,000.00).
  - Activities: a call for Bose racking overdue since yesterday and one next week, and a call for Bose shelving done after its due time.
  - The Reports page's CRM group listed the 19 reports in 17.29's order.
  - Opportunities by Stage read Qualified 1 ($2,40,000.00, weighted $60,000.00) and Proposal 1 ($1,00,000.00, $50,000.00, 4.2 days at it). New and Negotiation were empty.
  - Pipeline Value opened by stage, with its chart: Qualified 70.6%, Proposal 29.4%, total $3,40,000.00. Its filters were Owner, Lead source and Stage: there is one pipeline, so no Pipeline filter.
  - Weighted Forecast read November $1,00,000.00, weighted $50,000.00, with $30,000.00 won this month; February 2027 $2,40,000.00, weighted $60,000.00.
  - Stage Duration read New 1 moved on, after 4.0 days. Qualified and Proposal had 1 moved on each (won or lost the same day) and 1 there now each.
  - Closing Soon listed Bose racking, 10 days left. Won and Lost listed the two, with Won (1) $30,000.00 and Lost (1) $20,000.00 above.
  - Win Rate read 50.0%, and 60.0% by value. Lost Reasons read Price: 1, $20,000.00, Rival Co.

**Phase H3: people and activity** is built, with these defaults:

- **Five reports,** while CRM is on, each counting what the user sees.
- **Salesperson Performance** and **Sales Team Performance**, for whoever may view CRM reports and opportunities. For each owner or sales team over the period:
  - the leads added and converted (while the business uses leads, for whoever may view them);
  - the opportunities won and lost, with the win rate and the value won;
  - the open pipeline now;
  - the activities done (for whoever may view activities).
  - An activity counts for whom it is given to, or for its record's sales team. The most won show first, and commission is not worked out (17.24).
- **Activity Completion**, for whoever may view CRM reports and activities: those due in the period, by month, whom they are given to, or type.
  - Columns: completed, on time, cancelled, still planned and overdue.
  - The completion rate counts those not cancelled; the on-time rate those completed.
- **Overdue Activities:** the list of planned ones past their due time now, the longest overdue first, with what each is for and its days overdue.
- **Opportunities Without Follow-up:** the list of open ones with no activity planned, the longest without activity first. Their last contact and days without activity are counted as on an opportunity's page.
- On an activity report, the Owner filter is whom an activity is given to, labelled "Assigned to".
- **Campaign-to-Opportunity Results** waits for campaigns (Doc 21). It is the one report of 17.29 Phase H leaves out.
- **Checked in the browser** with the same data:
  - Salesperson Performance read izSuite User: won 1, lost 1, 50.0%, $30,000.00 won, $3,40,000.00 open pipeline, 1 activity done. Sales Team Performance read the same under No sales team.
  - Activity Completion read November: 3 due, 1 completed, none on time, 2 still planned, 1 overdue, 33.3% completed. Its owner filter read Assigned to. Grouped by Assigned To from its Group By, izSuite User read the same.
  - Overdue Activities listed Call Mr Bose, due 17 November, for Bose racking, 1 day overdue.
  - Opportunities Without Follow-up listed Bose shelving, with that day as its last contact.
  - The opportunities, activities, CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase I: rules and records** (sections 17.9, 17.22, 17.25 and 17.30) was split on starting it (2026-11-18), in four parts: I1, assignment rules; I2, lead scoring; I3, marketing consent; I4, custom fields.

**Phase I1: assignment rules** (section 17.9) is built, with these defaults:

- **Settings → CRM gains an Assignment Rules card** while the business uses leads, the rules in order. Each can be moved up or down, edited, switched off and deleted.
- **A rule asks for what a lead has:** its lead source, location (while there are several), the sales team it names, its country and an item it is interested in. Each left on Any; with all on Any, it fits every lead.
- **A rule gives the lead to:**
  - a person;
  - or a sales team's members, either by turns (the next member after the last one given a lead, in name order) or to the member with the fewest open leads (by turns between equals).
- **When rules apply:** a lead added without an owner goes to the first rule that fits.
  - Only an active member who may view leads at the lead's location is chosen. A rule with nobody who may take the lead passes it on to the next rule, as does a team switched off.
  - When none fits, the lead waits in the queue.
  - A lead with an owner chosen is not asked about.
- **A lead in the queue** can be sent through the rules from its Assign dialog, with "Use the Assignment Rules". With none fitting, the dialog says so.
- **The lead form's owner list** reads "Nobody yet: the assignment rules, else the queue".
- **The Activity Log** names the rule that gave a lead its owner, and logs the rules' changes.
- **A new permission, Assignment and Scoring Rules: Manage Assignment and Scoring Rules** (17.30, for I1 and I2), brings Settings' View with it.
  - The CRM tab shows to it or to Manage CRM Settings, each seeing only its own part.
  - Roles made from the Manager template get it; give it to custom roles in Settings → Users & Roles.
- Assigning by hand stays, and the new owner is told as before.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - Dev has one member, so the sales team Wholesale Desk held izSuite User alone. Turns and workload between several people are covered by the tests.
  - Settings → CRM showed Assignment Rules after the CRM card. Its New Assignment Rule dialog showed Comes from, Names the sales team, Is in and Is interested in; there is one location, so no Is at.
  - "Referrals to the desk" (Comes from Referral, Wholesale Desk by turns) was added through it: "Rule added.", the row reading "comes from Referral" and "Wholesale Desk, by turns".
  - A second rule, "Bangladesh to me" (Is in Bangladesh, izSuite User), was added switched off, and showed an Off pill. Its up arrow put it first: "Order saved."
  - A Referral lead added without an owner went to izSuite User by the desk's rule.
  - An Email lead in Bangladesh waited in the queue. Its Assign dialog's "Use the Assignment Rules" answered "No assignment rule fits this lead. Assign it by hand."
  - With the Bangladesh rule switched on, the same button gave "The lead is now izSuite User's, by the assignment rules."
  - The Activity Log named each rule. The Add Lead form's owner list offered "Nobody yet: the assignment rules, else the queue".
  - The rules, the team, the leads, the CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase I2: lead scoring** (section 17.22) is built. Its suggested defaults named source, budget, purchase period, decision maker, business size, interested items and being contacted. Section 17.22 also names location, contact details, estimated value, engagement and time since last activity, so it has them all:

- **Settings → CRM gains a Scoring Rules card** after Assignment Rules, while the business uses leads, for whoever may manage assignment and scoring rules. A rule is added, edited, switched off and deleted, with a confirmation.
- **A rule gives a lead points, from -100 to 100 (never 0), when it:**
  - comes from a lead source, or is at a location (offered while there are several);
  - has a detail filled in: e-mail, phone, business name, job title, address, decision maker, customer need or interested items;
  - is interested in an item;
  - is worth, or has a budget of, at least an amount in the base currency. Another currency counts at today's rate; with no rate today, it gets no points;
  - expects to buy in a purchase period, or has words in its business size or type;
  - has at least so many activities done and e-mails sent;
  - was contacted, or was not contacted within Settings → CRM's lead response days;
  - has gone at least so many days without activity, as its page counts them.
- **An open lead's score** is its rules' points added up, kept from 0 to 100: hot from 70, warm from 40, cold below.
- **When it is scored:**
  - when it is added or edited, marked contacted, qualified or reopened;
  - when an activity is done or an e-mail sent for it;
  - every hour (`crm:score-leads`), as time without activity and late first contacts change by themselves;
  - and every open lead, by the queue, when the rules change.
- A score is not a change to the lead: someone editing it saves as before.
- A closed lead keeps the score it had. With no rule switched on, no lead has a score.
- **The lead's page** shows its score and band by its status, and a Lead Score card with the rules that gave it and their points. Over 100, the card says what the points add up to.
- **The Leads page** gains a Score column and a Score filter (hot, warm, cold) while the business scores its leads.
- A score never qualifies, unqualifies or archives a lead. AI scoring waits for the AI application.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - Settings → CRM showed Scoring Rules after Assignment Rules: "None yet: leads are not scored."
  - "Website leads" (Comes from a lead source, Website, 20) was added through its dialog: "Rule added. The open leads are being scored again.", its row reading "comes from Website" and +20.
  - "Gone quiet" (Has gone days without activity, 7, -15) read "has gone 7 days or more without activity" and -15 in red.
  - Six more were added through the API: an e-mail, a phone, worth at least $50,000.00, expects to buy this month, "wholesale" in its business size or type, was contacted. Each row read as it should.
  - Three leads were added. Karim Ahmed (website, e-mail and phone, $60,000, this month, a wholesale trader) scored 95 · Hot, Salma Begum 40 · Warm, and Rahim Uddin (a referral with a phone) 10 · Cold.
  - The Leads page showed them in its Score column, and its Hot filter showed Karim alone.
  - Karim's page showed "Score 95 · Hot" by its status. Its Lead Score card listed its six rules, Big value's +25 first.
  - Marked contacted, it read 100, with Contacted's +15 and "Its points add up to 110; a score stays from 0 to 100."
  - Big value, edited and switched off, showed an Off pill; Wholesale was deleted through its confirmation. The queue scored Karim again: 75.
  - The hourly `crm:score-leads` changed nothing. Each rule change was in the Activity Log.
  - The rules, the leads, the CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase I3: marketing consent** (section 17.25) is built, on leads and contacts, apart from sales follow-up:

- **A Marketing Consent card** on a lead's page, and on a contact's while CRM is on. For each purpose and channel, it shows the answer that stands - the latest: given, refused, withdrawn, or expired once a consent given passed its day - since when, its expiry and its source. Under it, the History lists every answer.
- **Record Consent** takes:
  - its purpose, from Settings → CRM's new Consent Purposes list (Promotions and Offers, Newsletters, Product Updates, Events and Invitations, Feedback and Surveys to start);
  - its channel: E-mail, SMS, Messaging Apps or Phone Calls. The channel goes beyond the suggested defaults, to match Doc 21's consent by purpose and channel;
  - consent given or refused, where it came from (Website Form, In Person, Phone Call, E-mail, Paper Form, Event, Import, Other), and when (left empty, now);
  - a consent given's expiry date (it holds through that day), and evidence or a reference.
- **Withdraw**, on a consent given that stands, takes when and how. The answer stays, with its withdrawal: nothing is deleted, so the history is whole.
- A later answer replaces the one standing for the same purpose and channel. An earlier day can be recorded, as from a paper form; then it goes into the history.
- **A converted lead's answers go to its contact** (section 17.8, step 5). The lead's card keeps showing them, read-only: changes are made on the contact.
- **A merged contact's go to the one kept.** The merge preview counts them.
- An unqualified or archived lead, or an archived contact, still takes an answer: a refusal or a withdrawal holds at once.
- A contact with consent records is deactivated rather than deleted, as its history stays. A consent purpose answered about is switched off rather than deleted.
- Each answer and withdrawal is logged on the lead or contact, so its timeline shows it.
- **Permissions:** a lead's consent follows the lead (View Leads, then Create and Edit); a contact's follows the contact (View, then Edit Contacts).
- **It does not stop sales follow-up.** Activities and CRM e-mails go on whatever it says. Sending to the opted-out is Doc 21's to refuse, as there is no promotional sending yet.
- The business's data export has a marketing-consents file.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - Settings → CRM showed Consent Purposes after Activity Types, with its five purposes.
  - A lead's page showed Marketing Consent after Qualification: "None recorded: they have not been asked yet."
  - Record Consent recorded Newsletters by e-mail, given on the Website Form on 15 November at 10:30, expiring 15 November 2027, with "Web form entry #A12": "Consent recorded."
  - A refusal of Promotions and Offers by SMS, from a phone call, hid the expiry field and showed Refused.
  - Withdraw on the newsletter consent, with "Replied STOP to the newsletter": "Consent withdrawn." The History listed both answers, with when, the source, who recorded it, the expiry, the reference and the withdrawal.
  - The lead, qualified and converted, gave its new contact both answers. The contact's timeline showed the lead's consent entries.
  - On the contact, a new newsletter consent stood in place of the withdrawn one, and the History counted 3 answers.
  - The converted lead's card read "Its consent went with it to its contact: record any change there.", with no Record or Withdraw.
  - The dialog's date and time first overflowed it: it is wider now. A withdrawn answer showed its old expiry: it shows none now.
  - The lead, its opportunity and contact, the CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**Phase I4: custom fields** (sections 17.6, 17.12 and 17.26) is built, for leads and opportunities, set up in Settings → CRM:

- **Settings → CRM gains a Custom Fields card** after E-mail Templates, for whoever may manage CRM settings: the leads' fields, then the opportunities', each in their order.
  - Each field can be moved up or down among its own, edited, switched off and deleted, with a confirmation.
  - A field is for leads or for opportunities, chosen when it is added.
  - Its type is Text, Number, Date, Choice (its options, one to a line) or Yes or No. It can be required on the form.
- **The lead and opportunity forms** ask their fields switched on in a More Details card, after Qualification on a lead's and after Interested Items on an opportunity's.
  - Each is checked to be of its type, and a required one must be filled: "Fill in Industry.", or for yes or no, "Answer …".
  - A yes or no field takes Not answered, Yes or No.
  - A choice whose option was taken away later keeps a record's own value, marked "no longer offered"; another record cannot pick it.
- **Their pages** show the fields switched on in a More Details card: a date in the business's format, yes or no in words, a dash for none.
- A field switched off leaves the forms and pages, and its values stay, to come back when it is switched on again.
- A field that records have values for keeps its type and is not deleted: "Leads have values for it, so it stays. Switch it off instead."
- Changing a record without its custom fields leaves them as they were. Given, they hold every field: one left out is emptied.
- Converting a lead does not carry its values to the opportunity: their fields are each record's own.
- **Checked in the browser** on the first PC, as `user@izsuite.io` with CRM switched on by hand:
  - Settings → CRM showed Custom Fields after E-mail Templates: "None yet: the forms ask only izSuite's own fields."
  - Industry (Leads, Text, required) and Company Size (Leads, Choice of three sizes) were added through the dialog: "Field added."
  - Met At (Date), Has a Budget (Yes or No), Seats (Opportunities, Number, required) and Signed NDA (Opportunities, Yes or No) were added through the API. The card listed all six with what each is for, its type and options, and whether it is required.
  - Industry's down arrow put Company Size first: "Order saved."
  - Add Lead showed More Details after Qualification, with Company Size, Industry*, Met At and Has a Budget. Saved without Industry, it said "Fill in Industry." under it.
  - Filled in, the lead's page showed Medium (10 to 50), Retail, 12/11/2026 and Yes. Its edit form had them filled in; Has a Budget changed to No was saved and shown.
  - Add Opportunity showed only Seats* and Signed NDA. An opportunity added without Seats was refused; with 12 seats and the NDA signed, its page showed 12 and Yes.
  - Deleting Industry, which a lead had a value for, was refused in its dialog: "Leads have values for it, so it stays. Switch it off instead."
  - The fields, the lead and opportunity, the CRM settings and lists, the app's state, the sign-in and the activity were then put back.

**With Phase I4, Doc 17 is done** (2026-11-19). Campaign-to-Opportunity Results waits for Doc 21, and AI scoring for the AI application.

### 6. Doc 18: Point of Sale (done 2026-11-20)

The gap check was done on 2026-11-19. Doc 18 is Point of Sale: fast selling over the counter, from a register, in a cashier's session, with receipts, cash and closing counts. It sells through the shared Sales records, Contacts, Items, Taxes and Payment Accounts: a completed POS order counts as a sale once.

**Already built, which POS uses:**

- The Apps Center lists Point of Sale as coming soon, from Standard, needing Sales Lite.
- Sales documents (Sales Lite): invoices with lines, item components, line and document discounts, taxes by rate, prices with or without tax, tax rounding, the business and customer snapshots, and numbering per type, optionally with the location's code.
- Customer payments into payment accounts, by method (cash, bank transfer, card, mobile money, cheque, other), each with its receipt number, and reversed rather than deleted. Credit notes and their refunds.
- The full Sales app's price lists (with quantity breaks and customers of their own), customer credit limits and holds, customer advances, and returns.
- Items: products (physical and digital), services and combos, with SKU, barcode, category, brand, unit, sales price and tax default. The business's own units of measure.
- Payment accounts (cash, bank, mobile wallet…), their transactions and transfers between them. Expenses, for cash spent from a drawer.
- Documents as PDFs (Gotenberg), sent by e-mail; the Documents page; Reports; the Dashboard; notifications; idempotent saving; locations and location access; roles and permissions.

**Missing (what Doc 18 adds):**

- 18.1 to 18.5: the POS menu (Overview, Open POS, Registers, Sessions, Orders, Returns and Refunds, Cash Movements), Settings → Point of Sale, and registers: name, code, location, currency, price list, receipt sequence, cashiers, payment methods, cash account, receipt template, status.
- 18.6, 18.27 and 18.28: sessions - opening with a float, one open session per register, closing with counted cash and its variance, X and Z reports, a supervisor's forced close, reopening with permission.
- 18.7 to 18.14: the selling screen - search by name, SKU, barcode, brand or category, category tiles, frequent items, the cart, the customer (a walk-in one by default), pricing and its source, discounts within limits, price overrides with a reason, taxes and cash rounding.
- 18.15 to 18.18: POS payment methods mapped to payment accounts; split payments, tendered and change, pending electronic payments confirmed by hand; credit sales; the completed sale as one sales record, and a formal invoice from it.
- 18.19, 18.20 and 18.22: receipts (print, PDF, e-mail, reprint marked as such), receipt numbering per register, and holding and resuming orders.
- 18.23 to 18.26: returns from the original receipt, refunds, exchanges, and cash movements (cash in and out, safe drop, bank deposit).
- 18.31: cashier PINs, locking after inactivity, manager approvals and re-authentication.
- 18.32 to 18.38: POS settings, the Documents page's POS documents, the Dashboard's POS figures, 21 reports, 20 permissions and the data rules.

**Waiting for other apps or services**, as Doc 18 itself says:

- Inventory (18.8 and 18.21): stock reduced and returned, warehouses, serial and batch numbers, negative-stock rules and return conditions that move stock. Until then POS sells without stock, as 18.21 allows.
- Accounting (postings), Loyalty and Marketing (points, coupons, promotions), eCommerce (a shared catalogue), and Tax and Compliance (fiscal devices, electronic receipts, country reporting).
- Integrations: payment gateways and card terminals (until then a card or mobile payment is confirmed by hand), messaging apps for receipts, customer displays, weighing scales, cash drawers and fiscal printers.
- Offline mode (18.29) needs the app to work without the server: a later phase of its own, once the rest stands.

**Phases, with suggested defaults.** Saying "Start Phase A" accepts them.

- **Phase A: POS comes on, with its settings and registers** - split on starting it (2026-11-19): A1, POS on with Settings → Point of Sale; A2, registers.
  - **Phase A1 is built:**
    - The Apps Center offers Point of Sale from Standard, needing Sales Lite, with its own page's words. Until A2 adds Registers, the sidebar shows its name alone.
    - Settings → Point of Sale, under a new permission, POS Settings: Manage POS Settings (with Settings' View and Contacts' View):
      - the walk-in customer: one of the business's active customers, or none;
      - the receipt's return policy and footer;
      - payment methods, in their order: name, type (cash, card, mobile money, bank transfer, other), the payment account it takes money into - an active one of the business taking customer payments - whether the cashier enters a reference, whether it gives change (cash only), and on or off.
    - The first time the settings open, they start with a Cash method on the business's cash account that takes customer payments, the base currency's and the receiving default first - none, without a cash account.
    - A method whose account is switched off, or takes no customer payments, says it cannot be used. The method form starts a card, mobile money or bank transfer one needing a reference.
    - Turned off, POS's settings can be read but not changed, and its records stay.
    - **Checked in the browser** on the first PC, as `user@izsuite.io` with POS switched on by hand (its plan, Billing Starter, has POS only from Standard):
      - The Apps Center showed Point of Sale on, with Sales Lite "Needed by Point of Sale"; the sidebar showed its name.
      - Settings → Point of Sale showed its card. The dev business's three accounts are all banks, so no Cash method was started: "None yet: add how the counter takes money - cash to start."
      - Rahman Traders as the walk-in customer, a return policy and a footer: "POS settings saved."
      - Cash on Petty Cash (giving change), and Card on City Bank Current - its form ticking the reference and hiding change - were added: "Payment method added."; Card was then deleted through its confirmation.
      - The Activity Log named each change. The settings, the methods, POS's state, the sign-in and the activity were then put back.
  - **Phase A2 is built:**
    - A Registers page in the Point of Sale sidebar, under a new permission, Registers: View, and Manage Registers (with View). The POS permissions of 18.37 keep coming with their phases. A register is seen by those who may use its location; to anyone else it is not found.
    - A register has:
      - a name and a code, each its own in the business (the code in capitals: letters, digits and dashes);
      - its location, an active one the user may use, and its currency, one of the business's (the base one to start);
      - a price list, with full Sales: active, in its currency, for its location;
      - the account its cash goes into: taking customer payments, in its currency, of its location or the whole business. Without one, each cash method's own account;
      - the payment methods it takes: switched on, their money in its currency (a cash method's goes into the register's cash account, when it has one);
      - its cashiers: active members and the owner who may use its location;
      - a receipt number prefix (its code and a dash, unless given), and Active, Inactive or Maintenance.
    - A new register starts at the location picked in the top bar, in the base currency, taking the methods in it. Changing the location, the currency or the cash account takes off what no longer fits. A register's own choice that can no longer be used shows, saying so, and is refused until it is changed.
    - The register form starts the POS settings, with their Cash method, when Settings → Point of Sale has not been opened yet.
    - A payment method a register takes is not deleted, nor a location with registers. Each register added, changed or deleted is logged. Until Phase B brings sessions, a register is deleted outright.
    - **Checked in the browser** on the first PC, as `user@izsuite.io` with POS on by hand and Cash (Petty Cash) and Card (City Bank Current) added by hand:
      - The sidebar showed Point of Sale → Registers, and the page "No registers yet." The dev business has Sales Lite, so the form had no Price List field.
      - New Register started at Main Location, in USD, taking Cash and Card, and showed the prefix "R1-" as the code r1 was typed. Front Counter with izSuite User as its cashier: "Register added.", code R1, prefix R1-, Active.
      - Edited to take its cash into Petty Cash, with prefix FC/ and Maintenance: "Register saved." The row said "Cash into Petty Cash".
      - A second register with code R1: "Another register has this code." under the code.
      - Deleting Card in Settings → Point of Sale was refused in its dialog: "Registers take this method (Front Counter). Take it off them, or switch it off instead."
      - The register was deleted through its confirmation: "Register deleted." The Activity Log named the addition, the change and the deletion. The registers, the methods, POS's state, the sign-in and the activity were then put back.
- **Phase B: sessions** - split on starting it (2026-11-19): B1, opening and closing your own session, with its Z report; B2, the supervisor's side, itself split on starting it into B2a (closing someone else's session, the variance limit, reopening) and B2b (counting by notes and coins).
  - **Phase B1 is built:**
    - A Sessions page in the Point of Sale sidebar, under a new permission, Sessions: View, and Open and Close Sessions (with View). A session is seen by those who may use its register's location; to anyone else it is not found. The list goes by status and register, the latest opened first.
    - Opening a session takes one of the user's registers (active, with the user as one of its cashiers, at a location they may use), the opening cash (a float, not income) and notes:
      - one session open per register, and per cashier;
      - the registers that cannot open one say why;
      - sessions are numbered S-00001 in the business, in the register's currency.
    - A session's page shows its register, location and cashier, and the opening cash and notes. While it is open, it also shows what it has taken so far and the cash expected. Until Phases C to F bring sales, payments, refunds and cash movements, the cash expected is the opening cash.
    - Its cashier closes it with the cash counted:
      - the form sets the count against the cash expected as it is typed: over, short or matching;
      - a variance needs a reason;
      - the closing is kept as the session's Z report, which never changes: its takings, the opening cash, the cash expected and counted, the variance and its reason, and the notes.
    - While a session is open, its register stays active, at its location, in its currency. A register that has held sessions is kept: it is switched off instead, and the Registers page offers no Delete for it.
    - **Checked in the browser** on the first PC, as `user@izsuite.io`, with POS on, and Cash, Card and Front Counter (R1) added by hand:
      - The sidebar showed Point of Sale → Sessions, and the page "No sessions yet." with Open Session.
      - Open Session chose Front Counter (R1), the only register. With $150 and a note: "Session S-00001 opened." on its page, Open, with $150.00 expected so far.
      - Close Session with $145 counted said "$5.00 short of the cash expected." and asked why; closing without a reason was refused under it. With one: "Session S-00001 closed." The Z report showed $150.00 expected, $145.00 counted, $5.00 short, the reason and the notes.
      - The list showed S-00001 with $145.00 counted and the variance in red, and offered Open Session again. The Registers page offered Front Counter no Delete.
      - The Activity Log named the opening and the closing with its variance. The session, its Z report, its number counter, the register, the methods, POS's state, the sign-in and the activity were then put back.
  - **Phase B2a is built:**
    - Sessions gains two permissions, each with View: Approve Variances and Close Others' Sessions, and Reopen Closed Sessions.
    - A supervisor (one who approves variances) closes someone else's open session, an abandoned one: they count its cash and say why. Its Z report keeps the reason.
    - Settings → Point of Sale gains Variance Needing Approval: an amount, in each session's currency, or empty for none.
      - A closing whose variance goes beyond it leaves the session Closing, with a warning on its page, until someone who approves variances approves it.
      - One who may approve variances closes beyond it at once, the Z report naming them.
      - A session that is Closing holds neither its register nor its cashier: the next session may open.
    - Reopen Closed Sessions opens a counted session again, with a reason:
      - only while its register is active, and neither the register nor its cashier has another session open;
      - its Z report is kept as it was, marked reopened, under Earlier Closings, and its next closing makes a new one.
    - A Z report's figures never change: it is only marked approved, and marked reopened, once each.
    - Suspended waits for Phase I's cashier switching.
    - **Checked in the browser** on the first PC, as `user@izsuite.io` (the owner, who may approve and reopen), with POS on, and Cash, Card and Front Counter (R1) added by hand:
      - Settings → Point of Sale saved a Variance Needing Approval of 5.00: "POS settings saved."
      - S-00001 opened with $100 and closed at $90. The owner may approve, so it closed at once, its Z report reading "Approved by izSuite User".
      - Reopen Session without a reason was refused under its field: "Say why the session opens again." With one: "Session S-00001 reopened.", open with $100.00 expected, and the earlier closing under Earlier Closings with its reason. Closed again at $100, it had a new Z report and kept the earlier one.
      - The dev business has the owner alone, so two sessions were put in by hand: a cashier's count $12 short, beyond the limit (S-00002), and another person's open session (S-00003).
      - S-00002 showed Closing, the warning, Approve Variance and "Waiting for someone who approves variances". Approved: "Variance approved: session S-00002 is closed."
      - Closing S-00003 asked "Why You Close It", naming its cashier. Closed, its Z report showed "Closed Because" and the reason.
      - The Activity Log named the settings and each opening, closing, approval and reopening. The sessions, the settings, the register, the methods, POS's state, the sign-in and the activity were then put back.
  - **Phase B2b is built:**
    - Settings → Point of Sale has a Closing Sessions card, saved apart. It holds the Variance Needing Approval (moved there from the main card), whether cash is counted by notes and coins, and each of the business's currencies' notes and coins, typed as a list with commas.
    - Each currency starts with its usual notes and coins where izSuite knows them (AED, AUD, BDT, CAD, EUR, GBP, INR, JPY, MYR, PKR, SAR, SGD and USD); any other starts with none. A list is kept to the cent, once each, the largest first. A currency left empty is counted as one amount.
    - Counting that way, the close form lists the session currency's notes and coins: how many of each, each line's amount, and their total as the cash counted, set against the cash expected as it is typed. The API adds the count up again, and refuses a note or coin not on the list.
    - The Z report keeps the count, how many of each, and shows it under Notes and Coins.
    - **Checked in the browser** on the first PC, as `user@izsuite.io`, with POS on, and Cash, Card and Front Counter (R1) added by hand:
      - Settings → Point of Sale showed the Closing Sessions card with the variance field. Ticking the count showed USD's usual notes and coins. Trimmed to 100, 50, 20, 10, 5, 1, 0.25: "Closing settings saved."
      - S-00001 opened with $150. Close Session listed the seven with How Many. 1 × $100, 2 × $20 and 2 × $5 showed their amounts, $150.00 counted and "It matches the cash expected."
      - Closed: "Session S-00001 closed." The Z report's Notes and Coins read 1 × $100.00, 2 × $20.00 and 2 × $5.00.
      - The Activity Log named the settings, the opening and the closing. The session, the settings, the register, the methods, POS's state, the sign-in and the activity were then put back.
- **Phase C: selling** - split on starting it (2026-11-19): C1, the selling screen; C2, payment and the completed sale; C3, discounts, price overrides and cash rounding; C4, the receipt.
  - **Phase C1 is built:**
    - Open POS, first in Point of Sale's sidebar, under a new permission, Selling: Open POS (with Open and Close Sessions - and so Sessions' View - and Contacts' View). It sells in the user's own open session; without one it says so, and Open Session opens one there.
    - It shows the register - its location, currency and price list - and the session. The items to sell are active and not for purchases only:
      - found by name, Item Number, SKU, barcode, category and, while brands are on, brand - within the category tile chosen - alphabetical, up to 24;
      - a barcode or SKU typed exactly, as a scanner types it, with Enter, goes into the cart at once - the SKU in any case;
      - before any search, the most-sold items: those on the most issued invoices at the register's location in the last 30 days, up to 12;
      - each with its picture, SKU and price at the register.
    - The cart: one line per item - added again, one more of it - with its quantity, stepped or typed to 3 decimals, in the item's unit, and a note; an order note; and the customer, the walk-in one of Settings → Point of Sale to start, or any active customer.
    - Prices, in the register's currency: the customer's own price list (an active one naming them, for the register's currency and location, by priority), else the register's price list, else the item's sales price (a combo priced as its parts, theirs) divided by today's rate when the register's currency is not the base one. Each line says where its price came from. Price lists are the full Sales app's.
    - Taxes as Settings → Taxes says - the customer's treatment's rate, else the item's own, else the default - with or without tax in the price. The API works out the subtotal, each tax and the total as a sales document's.
    - Without today's rate for the register's currency, it says so and prices nothing.
    - The cart is kept in this browser for the session until Cancel Order, which asks first, empties it. Nothing of it is on the server: the order comes with C2.
    - **Found while checking:** the page loaded twice - again as the navigation arrived after it - so the cart was priced twice and its totals came late. It reloads only when another business is picked now.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS and full Sales switched on by hand, taxes on with VAT at 15%, a till with a Cash method, Front Counter (R1) priced by Shop Prices (Black Tea at 3.00), Rahman Traders' own list (2.80, 2.50 from 10), five items in Drinks and Snacks, and two issued invoices at Main Location:
      - Without a session Open POS said so; Open Session opened S-00001 there: "Session S-00001 opened." It showed the tiles Drinks and Snacks, and Most Sold: Black Tea at $3.00 and Butter Biscuits at $1.20.
      - The tea's barcode with Enter put it in the cart at "$3.00 / Piece · Price list: Shop Prices", VAT 15% $0.45, total $3.45; scanned again, 2 of it. The biscuits' tile, and "mug-1" with Enter, added theirs: $15.20, VAT $2.28, total $17.48.
      - Rahman Traders as the customer: the tea at $2.80, "Their price list: Rahman Prices", and 10 of it at $2.50. A quantity of 0 said "Type a quantity above 0, to 3 decimals."
      - Reloaded, the cart came back - its customer, lines and notes - priced again. The Tea Gift Set showed $11.50, its parts'. Cancel Order asked first, then emptied the cart, back to the walk-in customer.
      - As a throwaway cashier whose role holds Open POS alone: Open POS and Sessions in the sidebar, not Registers. Open Session offered Back Counter (R2), saying "Front Counter: You are not one of this register's cashiers."; there the tea sold at its own $3.50, and at $2.80 to Rahman Traders. With Open POS taken off the role, the sidebar lost it and the page said "Your role does not allow this."
      - The items, lists, invoices, registers, sessions, settings, member, the apps' and taxes' state, the sign-in and the activity were then put back.
  - **Phase C2 is built:**
    - Pay on the cart, under a new permission, Selling: Sell Items (with Open POS), once the cart is priced as it stands, for a customer, every line with a price. It takes the payment by the methods the register takes now - switched on, into an active account taking customer payments in its currency (a cash method's into the register's own cash account, when it has one):
      - one or several: each method chosen starts with what is still due;
      - cash for what was handed over - as much of it as is due paid, the rest the change - and other methods for their amount, with their reference where they need one;
      - Complete Sale once they pay the total exactly; done, it says the sale's number and the change to give, and the cart starts again.
    - The API prices the cart again: a line without a price is refused, and a total that changed since says what it is now - the cart is then priced again. Change comes only from a method that gives it; more than the total, or less, is refused.
    - **A completed sale is one invoice** of the business, written in one transaction:
      - issued at once, numbered by its register's own receipt run - its prefix and five digits, R1-00001 - skipping a number an invoice has already; dated and due today, at the register's location, in its currency, the order note its notes and each line note its line's description;
      - made in Point of Sale, so Sales Lite and Sales list it marked POS, its page says Made In Point of Sale, and it counts once, as any issued invoice;
      - each payment a customer payment, numbered as receipts are (RCT-00001), into its account and that account's history - the invoice paid.
    - A POS order keeps the sale: its session, register, cashier, customer and invoice, what was handed over and the change, each payment's method as it was named then, and where each line's price came from.
    - The session's takings count its sales now: how many, their total and taxes, the payments by method, and the cash sales - in the cash expected, and in the Z report.
    - Each sale is logged once, on its invoice: "Sold R1-00001 at the register "Front Counter" (R1) to Walk-in Customer, for USD 17.25: Cash 17.25".
    - A card or mobile payment completes at once, the cashier confirming it by hand; one waiting as Pending comes with Phase D.
    - **Found while checking:** a payment refused for a total that had changed left the cart showing the old total until something in it changed; the cart is priced again now.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%, Cash into the Front Till and Card into City Bank (needing a reference), and Front Counter (R1) taking both:
      - S-00001 opened with $100. Two teas and a mug: $15.00, VAT $2.25, "Pay $17.25". Cash, $20 handed over: Still to Pay $0.00, Change $2.75; then "Sale R1-00001 completed. Change: $2.75".
      - Three packs of biscuits and a green tea, $8.74: Card for $5.00 kept Complete Sale greyed out until its reference was typed; Cash started at the $3.74 left, and $5 handed over gave $1.26 change. "Sale R1-00002 completed."
      - The mug's price changed while its payment was open: "The total is now USD 10.35: check the cart, and take the payment again." Back on the cart, it read the new total.
      - Sales Lite's Invoices listed R1-00002 and R1-00001 marked POS, Issued and Paid. R1-00001's page showed its lines and VAT, Paid −$17.25, "RCT-00001 · Front Till · Cash $17.25" and "Made In Point of Sale".
      - The session read Sales 2 · $25.99, Taxes $3.39, Cash $20.99, Card $5.00 and Cash Expected $120.99.
      - As a throwaway cashier whose role held Open POS alone, at Back Counter (R2): a cart, and no Pay. With Sell Items, "Pay $4.60" and "Sale R2-00001 completed. No change to give."
      - The orders, invoices, payments, account entries, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase C3 is built:**
    - Settings → Point of Sale has a Selling card, saved apart: the discount limit - a percentage - and the cash rounding step, each empty for none.
    - Three new permissions under Selling, each bringing Open POS: Apply Discounts, Override Prices, and Discount Beyond the Limit (with Apply Discounts) - a manager's.
    - A cart line's + Price or Discount: a price typed over the item's, for whoever may override prices - and why, needed to take the payment - and a discount, by percentage or amount, for whoever may give one. The sale has a discount of its own, shared over its lines before tax, as a sales document's is. A percentage takes up to 100 off.
    - Each line shows what its discount takes off, and an override the price it replaced.
    - **Beyond the limit** - a line's or the sale's discount, as given, or a price typed more than the limit below the one it replaced - the sale needs Discount Beyond the Limit, and why; anyone else is told so, and cannot take the payment. The order keeps the reason and who allowed it, as the invoice keeps the reason; an overridden line keeps the price it replaced, and why.
    - **Paid in cash at all**, the total rounds to the step, the nearest: the difference is a Cash rounding line of the invoice - no share of the discounts, no tax - and the order keeps it. Paid by other methods alone, the total stands.
    - **Found while testing:** a 10% discount rounded to the cent can come to just over 10% of a total, and counted as beyond a 10% limit; a percentage counts as given now.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS on by hand, taxes on with VAT at 15%, Cash and Card (needing a reference) at Front Counter (R1):
      - Settings → Point of Sale's Selling card saved a 10% limit and 0.05 rounding: "Selling settings saved."
      - Two teas at 10% off ("$3.50 / Piece · Item price · $0.70 off", $6.30), biscuits, and 5% off the whole sale: Subtotal $7.50, Discount −$0.38, VAT $1.07, Total $8.19. Cash started at $8.20 - Cash Rounding +$0.01 - and $10 handed over gave $1.80 change. R1-00001's invoice kept the 5% discount, the tea's $0.70 off and a Cash rounding line of $0.01, untaxed.
      - The mug's price typed as $7.00: "Price changed, was $8.00", 12.5% under it - beyond the limit. Pay waited for the override's reason ("Display model") and "Why Beyond the 10% Limit" ("Last one left"), then took $8.05 by card, unrounded. The order kept the reason, allowed by izSuite User, and the line the price it replaced.
      - As a throwaway cashier who may sell and give discounts: no Price field. 20% off a green tea said "These discounts go beyond the 10% limit: someone with Discount Beyond the Limit gives them.", and Pay stayed greyed out; at 10%, Pay $4.14, and $4.15 in cash once rounded.
      - The orders, invoices, payments, account entries, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase C4 is built:**
    - A sale's receipt is a PDF on an 80 mm roll, one page as long as the receipt (at least 4.9 inches), to print from the browser on a receipt printer, or save. Drawn from what its invoice kept, in the invoice template's language, dates and money:
      - the business - its logo, name, address, phone, e-mail, registration and tax numbers - and the location, with its own header lines;
      - the receipt number, the date and time sold in the business's time, the register, the cashier, and the customer - none for the walk-in one;
      - each item with its quantity, unit, price, line note and discount; the subtotal, the sale's discount, each tax, the cash rounding and the total;
      - each payment by its method's name then and its reference, what was handed over where it was more, and the change;
      - the return policy and footer as Settings → Point of Sale had them when it was sold - the order keeps them - and "Powered by izSuite" as the invoice was issued with it.
    - **The first copy is its cashier's**, made as the sale completes: the sale's dialog has Receipt, E-mail Receipt and New Sale. Every later copy is a reprint, under a new permission, Receipts: Reprint Receipts (with Open POS). It says REPRINT across its top, the order counts its copies, and each reprint is logged: "Reprinted receipt R1-00001, copy 2". A copy that could not be made is not counted.
    - **E-mailed** - by its cashier, or whoever may reprint - to the customer's own address when they have one, or one typed: in the business's receipt e-mail of Settings → Email, the walk-in customer greeted as "Customer", the receipt attached as the original. Each is logged.
    - Under the cart, the session's last sale, with its receipt - Reprint Receipt once one was made, for whoever may - and E-mail Receipt.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS on by hand, taxes on with VAT at 15%, Cash and Card (needing a reference) at Front Counter (R1), a return policy of two lines and the footer "Thank you for shopping with us!":
      - S-00001 opened with $100. Two teas - one "No sugar" - and a mug for $17.25, $20 in cash: "Sale R1-00001 completed.", Change: $2.75. Receipt closed the dialog and opened the PDF: Billing Starter, Main Location, R1-00001, 19 Nov 2026 17:58, Front Counter (R1), izSuite User, "2 Piece × $3.50 $7.00", No sugar, VAT 15% $2.25, Total $17.25, Cash $17.25, Tendered $20.00, Change $2.75, the policy, the footer and Powered by izSuite - no customer named.
      - Closed, the cart's Last sale R1-00001 said Reprint Receipt: the copy said REPRINT, and the order counted 2. E-mail Receipt to a typed address: "Receipt R1-00001 is on its way to walkin.buyer@example.test." The queue sent it - "Dear Customer," with Receipt-R1-00001.pdf - to the log mailer.
      - Rahman Traders, $4.60 by card ("AUTH-5521"): the sale's dialog started with their own address, and sent it; the receipt named them, with "Card · AUTH-5521".
      - As a throwaway cashier whose role held Sell Items alone, at Back Counter (R2): R2-00001's first copy printed, without REPRINT; then Last sale offered E-mail Receipt alone, and a second copy - theirs or R1-00001 - was refused: "Receipt R2-00001 was printed already: a copy needs Reprint Receipts." With Reprint Receipts, Reprint Receipt came back, and its copy said REPRINT.
      - The orders, invoices, payments, e-mails, account entries, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
- **Phase D: holding, pending payments and credit** - split on starting it (2026-11-19): D1, holding and resuming orders; D2, pending payments; D3, credit sales and customer credit; D4, the formal invoice.
  - **Phase D1 is built:**
    - Hold Order under the cart, under a new permission, Selling: Hold and Resume Orders (with Open POS). The cart is held as it stands - its customer, lines with their notes, prices typed over (with why) and discounts, the order's discount and note - priced then, numbered H-00001, at its register. It is no sale: nothing reaches Sales, stock or the payment accounts. The cart empties for the next customer: "Order H-00001 held."
    - What the user may not give is not held either, and an item no longer sold is refused, as a sale's are.
    - Held Orders, at the head of the cart, with how many wait: the orders held at the user's register - by anyone - each with its customer, items, total when held, who held it, when, and when it expires. Resume loads one into an empty cart: "Hold or cancel the sale in the cart to resume another."
    - **Resuming prices it again.** What changed is said first, with its total then and now: a price that is not what it was ("Ceramic Mug: USD 8.00 when it was held, USD 9.00 now."), an item no longer sold - left out - a customer no longer sold to - back to the walk-in one - and a price typed over or a discount the user may not give, left out. With none of its items sold any longer, there is nothing to resume. Resumed, it waits no longer.
    - A held order is resumed at the register it was held at. Cancelling one needs a second new permission, Cancel Held Orders (with Hold and Resume Orders), and a reason.
    - Settings → Point of Sale's Selling card: Keep Held Orders For (Hours), up to 720 - empty, an order waits until it is resumed or cancelled. Past its time one cannot be resumed; the scheduler records it as expired, hourly.
    - Each hold, resume, cancellation and expiry is logged on the held order: "Held the order H-00001 at the register "Front Counter" (R1) for Rahman Traders: 2 items, USD 17.25".
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS on by hand, taxes on with VAT at 15%, Cash and Card at Front Counter (R1):
      - Two teas - one "No sugar" - and a mug for Rahman Traders, the order note "Back in 10 minutes": Hold Order said "Order H-00001 held.", the cart emptied back to the walk-in customer, and Held Orders (1). A green tea held next was H-00002. Nothing was sold: no invoice, no order.
      - The mug's price raised to $9.00 by hand. Held Orders listed both, each "Held by izSuite User, 19 Nov 2026, 18:22"; Resume on H-00001 said "Ceramic Mug: USD 8.00 when it was held, USD 9.00 now." and "Its total: $17.25 when held, $18.40 now." Resume with These Changes loaded Rahman Traders, both lines, "No sugar" and the order note: "Order H-00001 resumed.", Pay $18.40 - sold as R1-00001.
      - H-00002 cancelled, "Customer left without it": the list said none was held. Settings saved 4 hours: "Selling settings saved."
      - As a throwaway cashier who may sell, hold and resume, at Back Counter (R2): Held Orders started empty - R1's stay at R1. A tea held was H-00003, "expires 19 Nov 2026, 22:27", with Resume and no Cancel. Without Hold and Resume Orders: neither Hold Order nor Held Orders, and the list's API answered "Your role does not allow this."
      - The held orders, orders, invoices, payments, account entries, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase D2 is built:**
    - A payment method can wait to be confirmed - a card or mobile payment the cashier cannot see arrive: Settings → Point of Sale's method form, "Its payments wait to be confirmed", never for cash. The list marks it Confirmed Later.
    - Taken in a sale, its line says it waits. The sale completes as any - its invoice, its order, its receipt - but that payment is **pending**: the order's, with the account its money is to go into, and no customer payment yet - nothing in the account, the invoice owing it. The sale's dialog says "Card $10.00 (pending)", its receipt "Card · AUTH-1 (pending)", and the sale's log entry "Card 10.00 (pending)".
    - The session's totals count it apart, as Waiting to Be Confirmed - not under its method, not in the cash - while its Z report keeps what waited when it closed.
    - Under a new permission, Pending Payments: Confirm Pending Payments (with Sessions' View): the cart's Pending Payments (n) opens the session's page, whose Waiting to Be Confirmed card lists each with its sale, method, amount, reference and when. Confirm makes it the customer payment - numbered as receipts are, dated the day it is confirmed, into its account - and pays the invoice: "The Card payment for R1-00001 is confirmed." Decline, with a reason, leaves the invoice owing it, paid from the invoice. Each is done once, and logged on the invoice.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS on by hand, taxes on with VAT at 15%, Cash and Card (needing a reference, and confirmed later) at Front Counter (R1):
      - Settings → Point of Sale listed Card as Confirmed Later; its form had the box ticked.
      - A tea and a mug, $13.23: Card $10.00 ("AUTH-7781") said "It waits as pending until someone who may confirm payments says it arrived."; the rest in cash. "Sale R1-00001 completed.", "Card $10.00 (pending) · Cash $3.23". The cart then had Pending Payments (1).
      - The session's page: Cash $3.23, Waiting to Be Confirmed $10.00, and the card listing "R1-00001 · Card · $10.00", "Reference AUTH-7781". Confirm: "The Card payment for R1-00001 is confirmed.", then Card $10.00 under the methods. R1-00001 was paid - RCT-00001 the cash, RCT-00002 the card into City Bank.
      - A green tea, $4.60 all by card ("AUTH-9902"), declined - "Terminal shows it failed": "The Card payment for R1-00002 is declined: its invoice still owes it." R1-00002 stayed Unpaid, $4.60 due, the reason kept.
      - As a throwaway cashier whose role held Sell Items alone, at Back Counter (R2): a card sale's payment waited; the cart had no Pending Payments, and the session's page showed Waiting to Be Confirmed $4.03 with no list to confirm.
      - The orders, invoices, payments, account entries, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase D3: credit sales and customer credit** - split on starting it (2026-11-19): D3a, credit sales; D3b, the customer's credit as a payment.
    - **Phase D3a is built:**
      - With the full Sales app, under a new permission, Selling: Create Credit Sales (with Sell Items), what a sale's payments leave can stay on a named customer's account: the payment dialog's "Leave what is still to pay on the customer's account" - never the walk-in customer's. Still to Pay then reads On Account, and the sale completes with any payments - or none.
      - The sale is still one invoice: paid in part, or unpaid, owing the rest, due by the customer's payment terms - else Settings > Sales's. The order keeps what was left on account; the receipt says "On account · due 19 Dec 2026", and the sale's log entry "Cash 5.00, on account 10.00". Later payments are taken from the invoice, in Sales.
      - **The credit limit** is the customer's credit control's (Doc 16 Phase G1), counting only what is left on account: within it, the sale goes ahead; over it, a customer who warns lets it, the sale's dialog saying so; one who blocks refuses it, unless the user may override credit limits (Sales' Override Credit Limits) and says why - the order keeps the reason, and the invoice's log entry says "Sold R1-00003 over its customer's credit limit, because: ...".
      - Paid in full, a sale needs no credit, whatever is asked. Without the full Sales app, a sale is never left on account.
      - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS and full Sales switched on by hand, taxes on with VAT at 15%, Cash and Card at Front Counter (R1), and Rahman Traders on 30 days with a $20.00 limit that warns:
        - A tea and a mug for Rahman Traders, $13.23: $5 in cash, then the box ticked - On Account $8.23. "Sale R1-00001 completed.", "Cash $5.00", "On account: $8.23, on the invoice." R1-00001 was Partially Paid, $8.23 due on 19 Dec 2026, 30 days' terms.
        - The same again, all on account: completed with "Rahman Traders has a credit limit of USD 20.00, and this takes their balance to USD 21.46."
        - Rahman Traders made to block: a green tea on account was refused - "... would take their balance to USD 26.06. To go on, give a reason." - and Why Go Over the Credit Limit appeared; with "Pays every Friday", R1-00003 completed, the order keeping the reason, the invoice's log saying it.
        - As a throwaway cashier who may sell, but not create credit sales: no box in the payment dialog for Rahman Traders, and the API answered "Leaving a sale on account needs Create Credit Sales."
        - The orders, invoices, payments, account entries, registers, sessions, settings, member, the apps' and taxes' state, the sign-in and the activity were then put back.
    - **Phase D3b is built:**
      - With the full Sales app, a named customer's own credit - their advances and credit notes with credit left, in the register's currency - pays part of a sale: the payment dialog's "Pay with the customer's credit: $6.00 held", and Credit to Use, starting with as much as the credit and the total allow. Still to Pay then counts it first.
      - It is applied to the sale's invoice as Sales applies credit (Doc 16 sections 16.15 and 16.18) - oldest first, each source read again and locked in the sale's own transaction - and is no payment: nothing goes into an account. The order keeps how much; the receipt says "Customer credit", and the sale's log entry "Customer credit 6.00, Cash 9.00".
      - Never more than the customer holds - "Rahman Traders holds USD 6.00 of credit." - nor than the total; the walk-in customer holds none. It needs no permission beyond Sell Items.
      - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS and full Sales switched on by hand, taxes on with VAT at 15%, and a $6.00 advance (ADV-00001) recorded for Rahman Traders:
        - A tea and a mug for Rahman Traders, $13.23: the dialog offered "Pay with the customer's credit: $6.00 held"; ticked, Credit to Use 6.00, and Cash started at $7.23. "Sale R1-00001 completed.", "Customer credit $6.00 · Cash $7.23".
        - R1-00001 was Paid: $7.23 by RCT-00001 into the till, $6.00 allocated from ADV-00001, whose balance was then nil.
        - The advance, allocation, orders, invoice, payments, account entries, registers, session, settings, the apps' and taxes' state, the sign-in and the activity were then put back.
  - **Phase D4 is built.** Phase D is done.
    - Under the cart, Last sale's Formal Invoice - for a sale to a named customer, by its cashier or whoever may reprint receipts: the sale's own invoice is given the business's invoice number, once, at its location, in its period - never a second sale. The order keeps the receipt number; the row then reads "Last sale R1-00001 · invoice INV-00001", and Formal Invoice opens it again.
    - Its PDF is the business's A4 or Letter invoice, naming the receipt: "POS Receipt R1-00001". Sales lists it by its invoice number, still marked POS; the receipt still says R1-00001.
    - A sale to the walk-in customer has none: "A formal invoice is made out to a named customer: R1-00002 was sold to the walk-in customer." Given, it is logged on the invoice: "Gave the POS sale R1-00001 its formal invoice number INV-00001".
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%:
      - A sale to Rahman Traders, $17.25 in cash, as R1-00001. Formal Invoice opened "Invoice INV-00001": Bill To Rahman Traders, POS Receipt R1-00001, the lines and VAT, PAID. The row then read "Last sale R1-00001 · invoice INV-00001".
      - A walk-in sale after it, R1-00002: no Formal Invoice. Sales Lite's invoices listed INV-00001 and R1-00002, both POS - one invoice for each sale.
      - The orders, invoices, payments, account entries, register, session, settings, the app's and taxes' state, the sign-in and the activity were then put back.
- **Phase E: returns, refunds and exchanges** - split on starting it (2026-11-19): E1, returns and their refunds; E2, refund receipts, other refund methods and returns without a receipt; E3, exchanges.
  - **Phase E1 is built:**
    - Return, at the head of the selling page, under a new permission group, Returns and Refunds: Process Returns (with Open POS), and Process Refunds (with Process Returns). In one's own session, the sale is found by its receipt number - "There is no sale with the receipt number R9-99999." - at a location the user may use.
    - Each of its items shows how much was sold and how much is still returnable - what its credit notes have not taken - with its price. What comes back - each with its condition: Resellable, Damaged, Quarantined or Not Returned - and why; what it credits is worked out as each line changes, at the sale's prices, discounts and taxes ("Credits $4.03").
    - **Underneath** it is the Sales app's credit note of the sale's invoice, issued at once (CN-00001), so it counts once; the return is RT-00001, keeping its lines, conditions and reason. No more comes back than is left: "Only USD 3.50 of this line is left to credit."
    - **Paid back** - for whoever may process refunds - in cash from the drawer (the register's cash account, else its cash method's), or by the sale's own method into its account: a customer refund out of that account. Never more than the sale was paid in money; what is not paid out stays as credit on the credit note, or takes off what the invoice still owes. **Kept as credit** instead - a named customer's only - nothing is paid out. Without Process Refunds, a return is kept as credit.
    - The session's Cash Refunds count what its returns paid back in cash, and Cash Expected is less by it. Each return is logged on its credit note: "Took back RT-00001 from R1-00001 at the register "Front Counter" (R1), for USD 4.03: Cash 4.03 - Wrong flavour".
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%, Cash and Card at Front Counter (R1), a cash sale to the walk-in customer (R1-00001, two teas and a mug) and a card sale to Rahman Traders (R1-00002, a mug):
      - Return, R1-00001: "Walk-in Customer · sold 19 Nov 2026, 19:38 · paid by Cash", Black Tea 2 sold, 2 returnable; In cash or By the sale's own method (Cash) - no credit for the walk-in customer. One tea, Resellable, "Wrong flavour", in cash: "Credits $4.03", then "Return RT-00001 recorded." and "Pay back $4.03 by Cash."
      - R1-00002 for Rahman Traders offered Kept as the customer's credit too: the mug, Damaged, "Chipped", kept as credit - "Nothing is paid back. $9.20 stays as credit on CN-00002."
      - The session read Cash Refunds $4.03 and Cash Expected $113.22; CN-00001 was used up by its refund out of the till, CN-00002 kept $9.20.
      - As a throwaway cashier who may process returns but not refunds: R1-00001 had its tea at 1 returnable, and no way to pay it back - so no return.
      - The returns, credit notes, refunds, orders, invoices, payments, account entries, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase E2 is built** - but for returns without a receipt (see [Small things left](#small-things-left)):
    - **A refund receipt** for each return: Refund Receipt once it is recorded, on the receipt's 80 mm roll - headed Refund Receipt, the return's number and its sale's, when, where and by whom, each line brought back, the credit note's totals, what was paid back and how - "(pending)" while it waits - and what was kept as credit.
    - **Another method**: "By another method" - one of the register's methods, and why - under a third new permission, Use Alternative Refund Methods (with Process Refunds). The return keeps why.
    - **A method that waits to be confirmed** (Phase D2's), the sale's own or another: the refund is pending - nothing paid out, the credit on the credit note: "$4.03 by Card waits to be confirmed." Whoever may confirm pending payments sees it on the session's page, under Refunds Waiting to Be Confirmed: Confirm makes it the customer refund out of its account, never more than the credit note may still refund; Decline, with a reason, leaves the credit on the credit note. Each is done once, and logged on the credit note.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%, Cash and Card (confirmed later) at Front Counter (R1), and a cash sale R1-00001:
      - Return, R1-00001: a tea, "Wrong flavour", By another method - Card, "Customer has no cash" - "Credits $4.03". "Return RT-00001 recorded." and "$4.03 by Card waits to be confirmed."
      - Refund Receipt opened "Refund Receipt RT-00001": Return RT-00001, Sale R1-00001, the tea, VAT 15% $0.53, Total $4.03, "Card (pending) $4.03".
      - The session's page listed "RT-00001 · from R1-00001 · Card · $4.03" under Refunds Waiting to Be Confirmed; Confirm: "The Card refund for RT-00001 is confirmed." - a $4.03 refund out of City Bank, the reason kept.
      - The member's side - another method refused without Use Alternative Refund Methods - is covered by the API tests, not the browser.
      - The return, credit note, refund, order, invoice, payments, account entries, register, session, settings, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase E3 is built**, and with it Phase E:
    - **Exchanged**, a fourth way to settle a return: nothing is paid out, its credit kept on its credit note for the sale of the replacement - for any customer, the walk-in one too, and for whoever may process returns: "Its credit of $9.20 pays for the replacement: start its sale, and add what the customer takes instead." Start the Exchange Sale puts the exchange in the cart, for the return's customer: "Exchange for RT-00001: its credit of $9.20 pays for this sale first."
    - **The replacement's sale** is paid by that credit first, as much as it costs - applied from the credit note to its invoice - and its payments pay the rest ("Pay $4.03"). Costing less, what the credit leaves over is paid back in cash from the register, for whoever may process refunds - a customer refund out of the till, never more than the returned sale was paid in money: "Paid Back in Cash $5.17". The customer's own credit does not pay it besides; Hold Order is not offered while the cart carries an exchange; Cancel Order leaves the credit on its credit note.
    - **Two separate, linked records**: the return keeps the sale it paid, and the sale its return, how much the credit paid and what was paid back. Each exchange is sold once, to its return's own customer: "RT-00001's exchange was sold already." The sale's receipt shows "Exchange · RT-00001" and "Paid back"; the refund receipt, "Kept for the exchange". The session's Cash Refunds count what exchanges paid back. Logged on the invoice: "Sold R1-00002 at the register "Front Counter" (R1) to Walk-in Customer, for USD 3.50: Exchange RT-00001 3.50, Cash 4.50 paid back".
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%, Cash and Card at Front Counter (R1), and a cash sale of a mug to the walk-in customer, R1-00001 ($9.20):
      - Return, R1-00001: the mug, Damaged, "Chipped", Exchanged - "Credits $9.20". "Return RT-00001 recorded." and "Its credit of $9.20 pays for the replacement: ..."; Start the Exchange Sale.
      - A Black Tea in the cart ($4.03): "... pays for this sale first, and the $5.17 it leaves over is paid back in cash", and Pay $0.00. Take Payment showed Exchange RT-00001 −$4.03, Still to Pay $0.00 and Paid Back in Cash $5.17; Complete Sale: "Sale R1-00002 completed. Pay back $5.17 in cash." Its receipt: "Exchange · RT-00001 $4.03", "Paid back $5.17".
      - The session read Cash Sales $9.20, Cash Refunds $5.17 and Cash Expected $54.03.
      - As a throwaway cashier who may process returns and sell but not refund, at Back Counter (R2): a second mug sale, R1-00003, came back offering Exchanged alone (RT-00002). A tea in its place said "paying the difference back needs Process Refunds", and Complete Sale was refused: "The replacement costs less than RT-00002's credit: paying the difference back needs Process Refunds." With a mug added (Pay $4.03), cash paid the rest: "Sale R2-00001 completed." - "Exchange RT-00002 $9.20 · Cash $4.03". A third exchange, cancelled, took its banner and the page's note with it.
      - The returns, credit notes, refunds, allocations, orders, invoices, payments, account entries, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
- **Phase F: cash movements and the X report** - split on starting it (2026-11-20): F1, cash movements and their approval; F2, a cash expense paid from the drawer, recorded in Expenses; F3, the X report.
  - **Phase F1 is built:**
    - **Cash In or Out**, on the selling page, under a new permission group, Cash Movements: Record Cash Movements (with Open POS) and Approve Cash Movements (with Sessions' View). In one's own open session: Cash In, Cash Out, Safe Drop, Bank Deposit or Float Adjustment (into the drawer or out of it), an amount and why. "The drawer's cash is in Front Till: it should hold $68.40 now."
    - **Each moves money** between the drawer's cash account - the register's cash account, else its cash method's - and another active account of the business in the session's currency, by a completed transfer whose reference is the movement's number (CM-00001): a safe drop into a cash account (the safe), a bank deposit into a bank account, the others any. Never the drawer's own account, and no more out of the drawer than it should hold: "The drawer should hold USD 28.40: no more can come out of it."
    - **The session counts what moved**: its Cash In and Cash Out, and Cash Expected with them - so the Z report too, at the close.
    - **Above a limit** - Settings → Point of Sale → Closing Sessions' new Cash Movement Needing Approval - one recorded by someone who may not approve cash movements waits, nothing moved: "Safe drop CM-00003 waits for a manager's approval: nothing moves until then." The session's page lists its movements under Cash Movements; whoever may approve them approves one ("the cash has moved") or declines it with why ("nothing moves"), while the session is open. A session closes only once none waits: "CM-00003 waits for approval: approve or decline it, then close the session." Each movement, approval and decline is logged on the session.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, Front Till, City Bank and a Back Office Safe (a cash account), Front Counter (R1) taking Cash and Card, a $50.00 float and a cash sale of two mugs ($18.40):
      - Cash In or Out: "The drawer's cash is in Front Till: it should hold $68.40 now."; a Safe Drop of $40 into Back Office Safe, "Too much cash in the drawer": "Safe drop CM-00001 recorded."
      - A Bank Deposit (City Bank chosen for it) of $50: "The drawer should hold USD 28.40: no more can come out of it."; of $20: "Bank deposit CM-00002 recorded." The session then read Cash Out $60.00 and Cash Expected $8.40.
      - Settings → Point of Sale: Cash Movement Needing Approval $30 - "Closing settings saved."
      - As a throwaway cashier who may record cash movements but not approve them, at Back Counter (R2) with a $100 float: a $60 safe drop, "End of shift" - the dialog said "Above $30.00, it counts once a manager approves it." - waited: "Safe drop CM-00003 waits for a manager's approval: nothing moves until then." Their session's page listed it Waiting for Approval, with no Approve; Close Session was refused: "CM-00003 waits for approval: approve or decline it, then close the session."
      - Back as `user@izsuite.io`, on that session's page: Approve - "Safe drop CM-00003 approved: the cash has moved." - Cash Out $60.00, Cash Expected $40.00.
      - The cash movements, transfers, order, invoice, payment, account entries, accounts, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase F2 is built:**
    - **Paid Out**, a sixth kind of cash movement, with the Expenses app: an expense paid from the drawer - who it is paid to, one of the business's active expense categories, the amount and why. Underneath, an expense of Expenses at the register's location, in the session's currency, without tax - its reference the movement's number - recorded at once and paid from the drawer's cash account. It counts as the session's cash out.
    - It is offered only where the drawer may pay one: with the Expenses app on, and the drawer's account paying expenses - "Shop Till does not pay expenses: allow it in Payment Accounts to pay one out of the drawer."
    - Above Settings' limit it waits like any other; approved, its expense is made and paid then. The session's page names its payee, and its expense once it moved.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, the Expenses app on as the dev business has it, Front Counter (R1) taking Cash into Front Till, a $50.00 float and a cash sale of two mugs ($18.40):
      - Cash In or Out offered Paid Out; chosen, Paid To and Expense Category (the business's categories) took the account's place. $12.50 to "Door Fix Co", Repairs, "Fixing the shop door": "Paid out CM-00001 recorded."
      - The session's page listed "CM-00001 · Paid Out · −$12.50 to Door Fix Co · EXP-00001" and read Cash Out $12.50, Cash Expected $55.90. Expenses listed EXP-00001 - Door Fix Co, Repairs, $12.50, Recorded, Paid.
      - As a throwaway cashier who may record cash movements - and nothing of Expenses - at Back Counter (R2) with a $30 float: $8 to "Corner Shop", "Milk for the staff": "Paid out CM-00002 recorded." - EXP-00002, paid; their session read Cash Out $8.00, Cash Expected $22.00.
      - The cash movements, expenses and their payments, order, invoice, payment, account entries, accounts, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back - and the plan's "More users than the plan includes" notice the hourly check gave while the member was there.
  - **Phase F3 is built**, and with it Phase F:
    - **X Report** on the session's page while the session is open, for whoever may view sessions: its totals so far on the receipt's 80 mm roll - the business and location, the session, register and cashier, when it opened and "As at" now; its sales, taxes, payments by method and those waiting to be confirmed; and its cash - the opening cash, cash sales, refunds, cash in and out, and the cash expected. "Not a closing: the session stays open."
    - **Z Report** in its place once the session is counted: the same from what its closing kept, with when it closed, the cash counted, and the variance with why.
    - Each one made is logged on the session ("Printed the X report of the POS session S-00001"). Asked for the other - an X report of a closed session, a Z report of an open one - it says which to use.
    - The receipt's look and business head are now shared partials (`documents/partials/pos-roll-styles` and `pos-roll-business`), used by the receipt, the refund receipt and the reports.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, Front Counter (R1) taking Cash into Front Till, a $50.00 float, a cash sale of two mugs ($18.40) and a $20 safe drop:
      - The session's page offered X Report: "X Report S-00001" on the roll - Front Counter (R1), izSuite User, opened 21:09, As at 21:10, Sales (1) $18.40, Taxes $2.40, Cash $18.40; Opening cash $50.00, Cash sales $18.40, Cash refunds $0.00, Cash in $0.00, Cash out −$20.00, Cash expected $48.40; "Not a closing: the session stays open." (A first look showed "−$0.00" for no refunds; the minus now shows only on an amount.)
      - Closed with $46.40 counted, "Two dollars short": the page offered Z Report instead - closed 21:11, Cash expected $48.40, Cash counted $46.40, Variance −$2.00, "Two dollars short".
      - As a throwaway member who may only view sessions, the session's page offered Z Report and opened it.
      - The cash movement, transfer, order, invoice, payment, account entries, accounts, registers, session and its closing, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
- **Phase G: the POS pages, Documents and the Dashboard** - split on starting it (2026-11-20): G1, the Orders, Returns and Refunds, and Cash Movements pages; G2, the POS Overview and the Dashboard's POS card; G3, POS documents on the Documents page.
  - **Phase G1 is built:**
    - Point of Sale's menu gains **Orders**, **Returns and Refunds** and **Cash Movements** after Sessions - 18.3's order - each under a new View permission: Orders (a new group), Returns and Refunds, and Cash Movements, each with Sessions' View. Processing returns now brings their View, and recording or approving cash movements theirs.
    - Each lists its records at the locations the user may use, the latest first, by register and cashier - the Sessions page's registers, and now their cashiers - and by session from a session's page ("Its sales", "Its returns": "Session S-00001 ×").
    - **Orders**: the receipt number - opening its invoice for whoever may view invoices, a formal invoice's number beside it - when, the register, session, cashier and customer, how it was paid, its total and what returns have credited of it; a receipt number searched for.
    - **Returns and Refunds**: the return and when, its sale, where and by whom, what it credits, and how it was paid back - "Cash $9.20", waiting or declined, kept as credit, or exchanged (naming the replacement's sale); by how it was paid back and its refund's status, and a number searched for - its own or its sale's. Whoever may process returns opens each one's refund receipt.
    - **Cash Movements**: each movement and when, where, its kind and amount (+ or −), the other account - or a paid out's payee and expense - why, by whom and its status; by kind and status, and by who recorded it.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, Front Counter (R1) taking Cash into Front Till, and in session S-00001 two cash sales (R1-00001, two mugs, $18.40; R1-00002, a tea, $4.03), a return of a mug in cash (RT-00001, $9.20), a $20 safe drop and a $5 cash in:
      - Orders listed R1-00002 ($4.03) and R1-00001 ($18.40, $9.20 returned), each opening its invoice; "00001" in the search left R1-00001.
      - Returns and Refunds listed RT-00001 - from R1-00001, credits $9.20, "Cash $9.20" - and its Refund Receipt opened over the page.
      - Cash Movements listed CM-00002 (Cash In, +$5.00, from Back Office Safe) and CM-00001 (Safe Drop, −$20.00); Kind, Safe Drop, left CM-00001.
      - The session's page's "Its sales" opened Orders for that session alone: "Session S-00001 ×".
      - As a throwaway member who may view Orders alone, Point of Sale's menu showed Sessions and Orders; Orders listed both sales, their numbers not links, with a Cashier filter; Returns and Refunds said "Your role does not allow this."
      - Found while checking: the list's refund receipt dialog was made as it opened, so it never loaded; it is made with the page now.
      - The return, credit note, refund, cash movements, transfers, orders, invoices, payments, account entries, accounts, registers, session, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase G2 is built:**
    - **Overview**, first in Point of Sale's menu (18.3's order), for whoever may view its orders: today - the business's day - at the registers of the locations the user may use, in the base currency, each sale valued as its invoice was. Sales Today and how many orders, the Average Order, Returns (what they credit, and what was paid back), the sessions open now and those closed today with a variance - each opening the page it counts; what each payment method took (completed payments), the items that took the most (before tax), the open sessions and the variances, each opening its session; and Open POS, for whoever may sell.
    - **The Dashboard's Point of Sale card**, while POS is on, for whoever may view its orders: today's figures as the Overview counts them - at the location the Dashboard shows, whatever its range - with what each method took and the top items.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%, Front Counter (R1) taking Cash and Card, and in session S-00001 a cash sale of two mugs (R1-00001, $18.40), a card sale of three teas (R1-00002, $12.08) and a mug brought back in cash ($9.20):
      - Point of Sale's menu started with Overview: "Today, 19 Nov 2026, at the registers of your locations - in USD." - Sales Today $30.48 (2 orders), Average Order $15.24, Returns $9.20 ("1 today · $9.20 paid back"), Open Sessions 1, Cash Variances 0; Payments by Method Cash $18.40 and Card $12.08; Top Items Ceramic Mug × 2 $16.00 and Black Tea × 3 $10.50 (before tax); Open Sessions "S-00001 · Front Counter (R1) · izSuite User".
      - The Dashboard's Point of Sale card showed the same figures, with "By method Cash $18.40 · Card $12.08" and "Top items Ceramic Mug × 2 · Black Tea × 3".
      - The session closed $1.20 short: Open Sessions 0, and Cash Variances 1 - "S-00001 · Front Counter (R1) · -$1.20".
      - As a throwaway member who may view Orders alone, Point of Sale's menu showed Overview, Sessions and Orders, and the Overview opened, without Open POS.
      - The return, credit note, refund, orders, invoices, payments, account entries, accounts, registers, session and its closing, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
  - **Phase G3 is built**, and with it Phase G:
    - **The Documents page lists POS's documents** (18.34) under a new source app, Point of Sale: each sale's invoice as its **POS Sales Receipt** - a **POS Invoice** once formally invoiced - each return's credit note as a **Credit Note**, and each return as its **Refund Receipt**, a row of its own for whoever may view POS's returns, dated as its credit note and found by its number or its sale's.
    - Each keeps its POS order, register and session references under its label: "RT-00001 · R1-00001 · Front Counter (R1) · S-00001". They are sales documents still - Sales' source lists them too, but for the refund receipts - and a refund receipt opens on Returns and Refunds, searched for it, with no Preview or actions of its own there.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, Front Counter (R1) taking Cash, and in session S-00001 a cash sale to the walk-in customer (R1-00001, $9.20), one to Rahman Traders formally invoiced ("R1-00002 is now invoice INV-00001.") and R1-00001 brought back in cash (RT-00001):
      - Documents, Source app Point of Sale, listed CN-00001 (Credit Note, "RT-00001 · R1-00001 · Front Counter (R1) · S-00001", Issued, Refunded), RT-00001 (Refund Receipt, "R1-00001 · Front Counter (R1) · S-00001", Completed, no Preview), INV-00001 (POS Invoice, "R1-00002 · Front Counter (R1) · S-00001", Issued, Paid) and R1-00001 (POS Sales Receipt, "Front Counter (R1) · S-00001").
      - RT-00001's actions: "Open in Returns and Refunds" - the page searched for RT-00001.
      - As a throwaway member who may view documents, invoices and credit notes but not POS's returns, the same source listed the three sales documents, their amounts hidden, and no refund receipt.
      - The return, credit note, refund, orders, invoices, payments, account entries, accounts, registers, session, settings, member, the app's and taxes' state, the sign-in and the activity were then put back.
- **Phase H: POS reports** - split on starting it (2026-11-20): H1, the sales reports; H2, discounts, taxes, returns, voids and items; H3, sessions and cash. In all three:
  - **A Point of Sale group on the shared Reports page,** while the business has POS's records - on, or turned off since - each counting what is at the locations the user may use.
  - **A new permission, POS Reports: View POS Reports** (18.37), which brings Reports' View with it. A report needs it and the view of what it counts - Orders' View for its sales. Roles made from the Manager template get it; give it to custom roles in Settings → Users & Roles.
  - Each has the Reports page's period and exports, and POS's filters that apply: location, register and cashier.
  - Completed sales count once, by their invoice's date, in the base currency at their own rate - held, payment-pending and cancelled orders never - and returns take off what their credit notes credit, by the credit note's date (18.38).
  - **Phase H1 is built:**
    - **Twelve reports.** POS Sales Summary groups by day unless told (every day of the range, quiet ones too), or by week, month, location, register, cashier, salesperson, hour, item, category or brand, with its headline figures and a chart over time. Under 18.36's names it is grouped one way: Sales by Location, Register, Cashier, Salesperson, Item, Category, Brand, Hour and Day.
      - A row has the orders, sales before tax, the tax (while taxes show), the total, the average order, returns, net sales (before tax: sales less returns) and net tax. By item, category or brand: quantities sold and returned, and sales before tax after each line's share of the sale's discount, a category or brand being the item's now.
      - **Sales by Hour** is the hour of the business's day a sale was completed or a return made, and **Sales by Day** each day of the range. A return counts for its sale's salesperson.
    - **Sales by Payment Method:** what each method took (completed payments, after change) and paid back for the period's returns, each by the name it had then. The cash an exchange gave back is on a row of its own. Credit sales, and the credit an exchange used, are not payments.
    - **Average Order Value:** orders, their total and average - with tax and before it - and items per order, by day, week, month, location, register or cashier. Returns are not taken off an order's value.
    - **Register and Cashier filters** (`GET /reports/pos/choices`, for View POS Reports): the registers at the user's locations, and their cashiers with whoever sold or took a return there.
    - The Reports page now says so when a role sees none of its reports: Reports' View, which View POS Reports brings, without the view of any report's records.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%, and Front Counter (R1) and Back Counter (R2) taking Cash and Card. At R1, in session S-00001: a cash sale of two mugs (R1-00001, $18.40), a card sale of three teas (R1-00002, $12.08), a cash sale of a green tea and two packs of biscuits (R1-00003, $7.36, dated back to 18 Nov at 14:20 in the database), and a mug of R1-00001 brought back in cash (RT-00001, $9.20). At R2, a throwaway member's cash sale of a tea (R2-00001, $4.03):
      - The Reports page ended with a Point of Sale group of the twelve.
      - POS Sales Summary for 18 to 19 Nov, by day: Sales Before Tax $36.40, Returns $8.00, Net Sales $28.40 and Average Order $10.47, with a chart of the two days. 18 Nov had 1 order ($6.40, $0.96 tax, $7.36); 19 Nov 3 orders ($30.00, $4.51 tax, $34.51, average $11.50, returns $8.00, net $22.00); in total 4 orders and $41.87.
      - Register, Back Counter (R2): "18 Nov 2026 - 19 Nov 2026 · Back Counter (R2)", 18 Nov empty and 19 Nov 1 order of $3.50. Grouped by Cashier: izSuite User 3 orders, net $24.90; Counter Cashier 1, $3.50.
      - Sales by Hour: 14:00 - 14:59, 1 order and $6.40; 22:00 - 22:59, 3 orders and net $22.00. Sales by Item: Black Tea 4 ($14.00), Ceramic Mug 2 sold and 1 returned (net $8.00), Green Tea 1 ($4.00), Butter Biscuits 2 ($2.40). Sales by Category: Drinks $18.00, No category $8.00, Snacks $2.40.
      - Sales by Payment Method: Cash 3 payments, $29.79 received, 1 refund and $9.20 paid back, net $20.59; Card 1 payment, $12.08.
      - Average Order Value: Average Order $10.47 and Average Before Tax $9.10; 18 Nov 1 order of 3 items; 19 Nov 3 orders, $11.50 on average, 2 items each; in total 9 items, 2.25 per order.
      - The summary's CSV matched the page, and its Excel and PDF came back.
      - As the throwaway member, who may sell and view POS's orders and reports: the Reports page showed the Point of Sale group alone, and Sales by Register ran with a Cashier filter (no Location filter, dev having one location). With View POS Reports taken off their role, the page listed no reports and POS Sales Summary said "There is no such report, or its app is not on."
      - Found while checking: a role with Reports' View and none of the records a report counts saw an empty Reports page. It now says "There are no reports for you here yet: each needs the view of the records it counts."
      - The return, credit note, refund, orders, invoices, payments, account entries, accounts, registers, sessions, settings, member, the app's and taxes' state, the sign-in and the activity were then put back. The database matched its backup.
  - **Phase H2 is built:** six more reports, each with location, register and cashier.
    - **Discounts and Price Overrides:** each completed sale that gave a discount - on its lines or the whole - or had a price overridden, oldest first. It shows what the sale came to before its discounts, the discounts and their share, how many prices were overridden and what that changed (below zero when lowered), why, and the manager who approved a discount beyond the limit. Its headline figures are the line discounts, the sale discounts and the override change.
    - **Taxes Collected**, while taxes are on or sales kept tax, for whoever may also see tax information: by tax and rate, the taxable sales and the tax their invoices kept, less what the returns' credit notes gave back. Tax Summary still has every app's.
    - **Returns and Refunds**, for whoever may view POS's returns: each return as its credit note is dated, with its sale, where, by whom, why, what it credited (tax included), how it was paid back and where its refund stands - paid, waiting or declined; none for credit kept or an exchange. Its headline figures are what was credited, paid back, waiting, and not paid back.
    - **Void and Cancellation Report:** held orders cancelled (with why) or expired, and payments and refunds declined, by the business's days, each in its own currency, with who called it off. A completed sale is never voided: its return corrects it.
    - **Top-Selling Items:** the top 100 by quantity sold less returned, then by net sales, with each one's share; narrowed to an item category if asked.
    - **Estimated POS Margin**, for whoever may also see item costs: by item, the net quantity and net sales, less the purchase price now times that quantity. An item without one shows no cost, and the totals leave it out. It is an estimate, not a Profit and Loss Statement.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, taxes on with VAT at 15%, Front Counter (R1) taking Cash and Card (confirmed later), and the mug's purchase price at $5.00. In session S-00001: two mugs less 10% for cash (R1-00001, $16.56), a tea's price overridden from $3.50 to $3.00 ("Dented tin") paid by card (R1-00002, $3.45), a green tea less $0.50 for cash (R1-00003, $4.03), a mug of R1-00001 brought back in cash (RT-00001, $8.28), three packs of biscuits held and cancelled ("Customer left"), and the card payment declined ("The bank refused it"):
      - The Point of Sale group had eighteen reports.
      - Discounts and Price Overrides: R1-00001, $16.00 before and $1.60 off (10.0%); R1-00002, $3.00, one price overridden, -$0.50, "Dented tin"; R1-00003, $4.00 and $0.50 off (12.5%); in total $23.00 and $2.10 (9.1%). Line Discounts $1.60, Sale Discounts $0.50, Override Change -$0.50.
      - Taxes Collected: VAT 15%, $20.90 taxable and $3.14 collected, $7.20 and $1.08 returned, net $13.70 and $2.06.
      - Returns and Refunds: RT-00001 of R1-00001, "Chipped handle", $8.28 credited and paid back in Cash, Paid.
      - Void and Cancellation Report: H-00001, Held order cancelled, USD $4.14, by izSuite User, "Customer left"; R1-00002, Card payment declined, $3.45, "The bank refused it".
      - Top-Selling Items: Ceramic Mug (2 sold, 1 returned, $7.20, 52.6%), Green Tea ($3.50, 25.5%) and Black Tea ($3.00, 21.9%). With Item category Drinks: "· Drinks items", the two teas.
      - Estimated POS Margin: Ceramic Mug, 1, $7.20 less $5.00, $2.20 (30.6%); the teas without a cost; "Total of items with a cost" $2.20.
      - As a throwaway member who may view POS's orders and reports, but not its returns, tax information or item costs: fifteen reports, without Taxes Collected, Returns and Refunds and Estimated POS Margin. Returns and Refunds said "There is no such report, or its app is not on."; the Void and Cancellation Report ran.
      - The return, credit note, refund, held order, orders, invoices, payments, account entries, accounts, registers, session, settings, member, the app's and taxes' state, the sign-in and the activity were then put back. The database matched its backup.
  - **Phase H3 is built**, and with it Phase H - all 21 reports of 18.36:
    - **Session Summary**, for whoever may view sessions: each session opened in the period, by the business's days - where, whose, where it stands (open, suspended, waiting for approval or closed), its opening float, sales, the cash it should hold, what was counted and the difference. A counted session's figures are those its latest closing kept, and one still open's as they stand now.
    - **Cash Variance Report**, for whoever may view sessions: each session closed over or short in the period, by its latest count - the expected and counted cash, the difference, why, and whether it was approved, waits for approval or was within the limit. Its headline figures are what was over, short, and the net.
    - **Cash Movement Report**, for whoever may view cash movements: each cash in, cash out, safe drop, bank deposit, float adjustment and paid out recorded in the period - its session, kind, amount in or out, the account or payee on the other side, why, who recorded it, and its status with who decided it. The Cashier filter is who recorded it; the totals add completed movements.
    - Each is in its registers' currencies: totals and headline figures show while the rows share one.
    - Every POS report now counts a sale only while its invoice is issued: one voided in Sales drops out, as it does from Sales' own reports.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, VAT at 15%, a variance limit of $2.00, and Front Counter (R1) and Back Counter (R2) taking Cash. Session S-00001 at R1: a $50.00 float, two mugs for cash ($18.40), $10.00 cash in from Back Office Safe (CM-00001), counted $80.00. At R2, a throwaway member's S-00002: a $20.00 float, a tea for cash ($4.03), a $10.00 safe drop (CM-00002), counted $10.00. Then S-00003 at R1, still open: a $50.00 float and a green tea ($4.60):
      - Session Summary: S-00001 Closed, $18.40 of sales, $78.40 expected, $80.00 counted, $1.60 over; S-00002 Waiting for approval, $14.03 expected, $10.00 counted, -$4.03; S-00003 Open, $54.60 expected, no count yet; in total 3 sales, $27.03, and -$2.43 of variance.
      - Cash Variance Report: Over $1.60, Short -$4.03, Net Variance -$2.43. S-00001 "A customer left their change", Within the limit; S-00002 "A note missing", Waiting for approval - and once approved, "Approved by izSuite User".
      - Cash Movement Report: Cash In $10.00, Cash Out $10.00, Net $0.00. CM-00001, Cash in, $10.00 in, Back Office Safe, "Change for the drawer", by izSuite User, Completed; CM-00002, Safe drop, $10.00 out, by Counter Cashier.
      - As the member, who may sell, move cash and view POS's reports but not its orders: the Point of Sale group listed Session Summary, Cash Movement Report and Cash Variance Report alone, the Cash Movement Report ran, and POS Sales Summary said "There is no such report, or its app is not on."
      - The sessions and their closings, cash movements, transfers, orders, invoices, payments, account entries, accounts, registers, settings, member, the app's and taxes' state, the sign-in and the activity were then put back. The database matched its backup.
- **Phase I: cashier security and devices** - split on starting it (2026-11-20): I1, the cashier's PIN and the register's lock; I2, a manager's approval at the register; I3, registered devices.
  - **Phase I1 is built** (migration 000208):
    - **Each user sets their own POS PIN** on the selling screen (Set PIN, then Change PIN): 4 to 6 digits, typed twice, with their account's password - the re-authentication 18.31 asks for. It is kept hashed on their account, one PIN for the person wherever they sell, owner or member, and never shown. Setting or changing it is logged.
    - **Lock** locks the register of one's own open session. **Settings → Point of Sale → Selling** gains Lock a Register After (Minutes), up to 240: a register left that long without a key or a touch locks - on the screen, and on the server, which counts from the session's last use.
    - While a register is locked nothing is sold at it: searching, pricing, selling, holding, returns and cash movements are refused with "Front Counter (R1) is locked: enter your PIN to go on." (423), and the screen shows its lock over the cart.
    - It opens with the cashier's PIN, or their password until they set one. A wrong one is refused under its field, and tries are held back to 10 a minute. A search the lock refused runs again.
    - **Switching cashiers:** each cashier sells in a session of their own, so another cashier signs in and opens theirs; the first cashier's sales and session stay theirs (18.31's "changing cashiers doesn't change the original user"). Suspended stays unused.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand, Front Counter (R1) taking Cash, Settings' lock after 1 minute, and session S-00001 open:
      - Open POS showed Set PIN and Lock. Lock covered the screen: "Front Counter (R1) is locked - Session S-00001. Enter your password to go on - or set a PIN to open it faster."
      - Meanwhile a search through the API was refused with 423. A wrong password: "That is not your password."; the right one opened it.
      - Set PIN with 2580 twice and the password: "Your PIN is saved.", and the button read Change PIN.
      - Reloaded after more than a minute without use, the screen came up locked: the server had locked it. "1111": "That PIN is not right."; 2580 opened it. Left alone for a minute, the screen locked itself.
      - Locked on the server behind the open screen, a search brought the lock up. Once opened with the PIN, the search ran again and listed Black Tea and Green Tea.
      - Settings → Point of Sale → Selling showed Lock a Register After (Minutes) at 1; 300 was refused, and 5 saved: "Selling settings saved."
      - A throwaway cashier of Back Counter (R2) with no PIN locked their register. It refused the owner's PIN ("That is not your password.") and refused a search with "Back Counter (R2) is locked: enter your password to go on."
      - Found while checking:
        - The API told a cashier with no PIN to enter their PIN; it says "your password" to them now.
        - The refused search's message stayed under the search box once the register was open; the search runs again now.
        - Settings said "The lock minutes field must not be greater than 240."; it has its own words now.
      - The sessions, registers, settings, member, the PIN, the app's and taxes' state, the sign-in and the activity were then put back, and the hourly usage check's notice to the throwaway member deleted. The database matched its backup, but for migration 000208's columns.
  - **Phase I2 is built** (migration 000209):
    - **A manager approves at the cashier's register** what the cashier may not do alone - a price typed over, a discount beyond the limit, cash moved above the limit, a refund by another method - by choosing themselves and typing their PIN. Whoever may do that thing, has a PIN and may use the register's location may approve it; the cashier never approves for themselves.
    - **Ask a Manager** shows where it is needed: on a cart line (Ask a Manager to Change the Price), under a discount beyond the limit, beside a cash movement above the limit (Ask a Manager Now), and on recording a return paid back by another method.
    - An approval lasts ten minutes, for that one kind of thing, in that session. The cart's go with its quotes, its hold and its sale, and are let go once it is sold or cancelled.
    - Each is named: a sale's discount beyond the limit keeps its approver (as before), a line's price typed over its own, a cash movement its decider - and it moves at once - and a return its refund's approver. The activity log says who approved what, where.
    - The refusals say so: "… someone with Discount Beyond the Limit gives it - or approves it at the register.", "… needs Use Alternative Refund Methods - or a manager's approval at the register."
    - **Checked in the browser** on the second PC, with POS switched on by hand, a 10% discount limit, cash movements above $50.00 needing approval, the owner `user@izsuite.io` holding PIN 2580, and a throwaway cashier of Back Counter (R2) who may sell, give discounts, move cash and refund - but not override prices, go beyond the limit, approve cash or refund by another method. As the cashier, in session S-00001 at R2:
      - On a Black Tea line, + Price or Discount offered "Ask a Manager to Change the Price". Its dialog, "Approve a Price Change", named izSuite User; "1111" was refused ("That PIN is not right."), and 2580 approved it. The Price field then showed, and the tea went to $3.00 ("Dented tin").
      - That was more than 10% off: "These discounts go beyond the 10% limit … Ask a Manager". "Approve the Discount" with 2580 brought the reason field, "Approved by izSuite User.", and with "Damaged stock" Pay $3.45 completed R2-00001 in cash.
      - The sale kept izSuite User as approver of the discount and of the line's price, and its log said "… because Damaged stock, approved by izSuite User, prices typed over approved by izSuite User", after "izSuite User approved a price typed over at Back Counter (R2), in session S-00001" and the same for the discount.
      - Cash In or Out: a $80.00 safe drop showed "Above $50.00, it counts once a manager approves it. Ask a Manager Now". "Approve the Cash Movement" opened over it; with 2580: "Approved by izSuite User: it moves at once." Recorded: "Safe drop CM-00001 recorded." - completed, decided by izSuite User.
      - Return, R2-00001's tea, "By another method", Card, "Customer asked for card": Record Return asked "Approve the Refund Method", and with 2580 "Return RT-00001 recorded. Pay back $3.45 by Card." - its approver izSuite User.
      - Found while checking: the approval dialog opened behind the cash dialog; it is drawn last on the page now.
      - The return, credit note, refund, cash movement, transfer, orders, invoices, payments, account entries, accounts, registers, session, settings, member, the PIN, the app's and taxes' state, the sign-in and the activity were then put back. The database matched its backup, but for migration 000209's columns.
  - **Phase I3 is built** (migration 000210), and with it Phase I - Doc 18 is built but for offline mode (18.29):
    - **Each register keeps its devices** (18.30): receipt printers, barcode scanners, cash drawers and customer displays - each named, connected through the browser, USB, the network or Bluetooth, at an address if it has one, in use or switched off - from Devices on its row of the Registers page, for whoever may view registers. Manage Registers adds, changes, removes and tests them; Remove takes one off at once, as it holds no records. Changes and tests are logged.
    - **Its test:** a receipt printer's is a test page on the receipt roll, to print from the browser; a barcode scanner's, a code it reads into the dialog; a drawer's or display's is marked done. A device switched off is not tested.
    - **Its last use:** a receipt printed at its register uses its receipt printers, and an item found by its very barcode or SKU there - scanned - its scanners.
    - Driving a device itself - opening a drawer, a display's lines, a fiscal printer - waits for the device integrations; the browser prints and the scanner types, as before.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with POS switched on by hand and Front Counter (R1) taking Cash:
      - Registers: each row had Devices. Front Counter's opened empty ("No devices yet.") with Add a Device.
      - "Counter Printer" (Receipt Printer, through the browser) and "Hand Scanner" (Barcode Scanner, USB) were added: "Counter Printer added.", "Hand Scanner added.", each "Not yet" used or tested.
      - The scanner's Test asked "Scan any barcode with Hand Scanner now"; 4006381333931 typed as a scanner would: "Hand Scanner read 4006381333931.", tested 19 Nov 2026, 23:52.
      - The printer's Test opened its test page on the receipt roll: Billing Starter, Main Location, "Test Page", Register Front Counter (R1), Printer Counter Printer, Printed 19 Nov 2026 23:52, a line of figures and letters, "If you can read all of this, the printer works."
      - After a session, an item found by its barcode, and a sale's receipt printed, both showed as last used 19 Nov 2026, 23:52.
      - Edit, In use cleared: "Hand Scanner saved.", the row "Barcode Scanner · Switched off" without Test. Remove: "Counter Printer removed."
      - Found while checking: the daily usage snapshot ran during the check, with POS on; it was deleted with the rest.
      - The devices, session, order, invoice, payment, account entries, accounts, register, settings, the app's and taxes' state, the sign-in and the activity were then put back. The database matched its backup, but for migration 000210's table.

### 7. Doc 19: eCommerce (next: Phase G)

The gap check was done on 2026-11-20. Doc 19 is eCommerce: the business's online storefront, where customers browse its items, order and pay, follow their orders, download what they bought and ask for returns. It sells through the shared Contacts, Items, Taxes, Payment Accounts and Sales records: an online order counts as a sale once, through its invoice.

**Already built, which eCommerce uses:**

- The Apps Center lists eCommerce as coming soon, from Premium. Point of Sale (Doc 18) showed how an app sells through Sales: orders of its own, each completed one an issued invoice of the business, payments into payment accounts by method, pending ones confirmed by hand, returns as credit notes and refunds, and its own entries on the Documents page, the Dashboard and Reports.
- Items: physical and digital products, services and combos, with SKU, barcode, category, brand, unit, sales price, tax default, sales description and an image; a digital product's files and how it is delivered (by hand, download, license key or external link).
- Contacts with billing and shipping addresses, duplicates merged by hand, marketing consent with its source and date (Doc 17 Phase I3), and custom fields.
- Sales: invoices with discounts, taxes and rounding, numbering, currencies at a rate, customer payments, credit notes and refunds, the customer's credit (advances and credit notes), and the full Sales app's price lists with quantity breaks, sales orders, deliveries and returns. Secure customer links (`/d/{token}`) to view and download a document.
- CRM leads with their items, and draft quotations, for a request for a quotation.
- Files within the plan's storage, PDFs (Gotenberg), e-mail through the queue in the business's templates, notifications, the Activity Log, idempotent saving, plan limits, locations, roles and permissions.
- The frontend renders its pages on the server (Nuxt), so public pages can carry their own titles, descriptions and structured data for search engines.

**Missing (what Doc 19 adds):**

- 19.1 to 19.5: the eCommerce menu, Settings → eCommerce, storefronts (name, code, status, languages, currencies, countries, location, support contact, logo and colours) up to the package's limit, and domains: an izSuite subdomain, custom domains once verified, the primary one, redirects from old ones, and a page for a store that is not open.
- 19.6, 19.7 and 19.36: the storefront's pages (home, shop, categories and collections, product, search, cart, checkout, account, order tracking, contact, about, the four policies, and pages of the business's own), its theme and branding with a preview before publishing, and SEO: titles, descriptions, clean links, canonical links, a sitemap, robots rules, sharing images and product structured data.
- 19.8 to 19.13: the catalog - items published online with their own title, link, descriptions, images, category, collections, keywords, visibility, publication status and dates, sales method and SEO - with options and variants, collections, filters, search with suggestions, and online prices with their source.
- 19.14 to 19.20: the cart, checkout in seven steps (as a guest or signed in), finding or making the customer's contact, the order with its fields and snapshots, its three statuses (order, payment and fulfilment), and its confirmation.
- 19.16: customer accounts, apart from the business's users: profile, addresses, orders, invoices, tracking, returns, downloads and consent.
- 19.21 to 19.24: editing and cancelling orders, payment methods each into a payment account, payment links, and holding orders for review.
- 19.26 to 19.30: shipping zones and methods, pickup, fulfilments with carriers and tracking, digital delivery, services, and requests for a quotation.
- 19.31 to 19.35: discounts and promotions, abandoned carts, returns and refunds, product reviews and the customer's notifications.
- 19.37 to 19.43: the settings, the Documents page's eCommerce documents, the Dashboard's figures, 21 reports, 20 permissions and the data rules.

**Waiting for other apps or services**, as Doc 19 itself says:

- Inventory (19.25): stock from warehouses, reserved on confirmation and taken on fulfilment, backorders, batches and serials, returns back into stock by their condition, and stock per variant. Until then availability is set by hand, as 19.25 allows.
- Customer groups and Marketing and Loyalty (Doc 21): products shown to chosen groups, group price lists, points, campaigns and automated cart recovery. Customer Subscriptions (Doc 20): recurring products. Appointments, Field Service and Projects: booking a service bought online.
- Accounting (postings), Tax and Compliance (electronic invoicing), Helpdesk (support cases) and Analytics.
- Integrations: payment gateways (paying by card or wallet online, with their callbacks), carriers (live rates and tracking), SMS and messaging, license keys made automatically, advanced fraud checks, analytics services and marketplaces. Until there is a gateway, the storefront takes payments the business confirms: bank transfer and mobile money with the customer's reference, cash on delivery, paying on pickup, and the customer's credit.
- The server: the certificates of custom domains and of the izSuite subdomains are issued on the server, outside the code. The code keeps which domains are verified, for the server to ask.

**Phases, with suggested defaults.** Saying "Start Phase A" accepts them.

- **The storefront** is served by the frontend: at `/store/{code}`, and on its subdomain and verified domains by the host name once the server is set up for it. Its pages render on the server, for search engines.
- **A storefront customer** is an account of its own (an e-mail and a password, verified by e-mail), never a user of the business and not counted as one, and is linked to one shared contact. Guest checkout is on unless switched off.
- **An online order** is a record of eCommerce, numbered by its own sequence (EC-00001). Its invoice is issued when it is confirmed - once paid, or by the business - so it counts as a sale once from then, and its payments go against that invoice.
- **Prices**: the item's sales price, else the storefront's price list or the customer's own (full Sales), with their quantity breaks, and the promotions of Phase H. Each order line keeps its price and where it came from.
- **Variants**: a product's options (size, colour…) group existing items, each item one variant with its own SKU, barcode, price, image and status.
- **Without Inventory**, each product is Available, Out of Stock, or Limited: a quantity set by hand that confirmed orders count down, never a stock movement.
- **Phase A: eCommerce comes on, with its storefronts** - split on starting it (2026-11-20): A1, eCommerce on, with Settings → eCommerce and its storefronts; A2, the plans' limit of storefronts; A3, their domains (Manage Domains).
  - **Phase A1 is built** (migration 000211):
    - The Apps Center offers eCommerce where it was coming soon - from Premium, as its catalog entry had it - needing Sales Lite, with its own page's words. Its menu gains Storefronts.
    - Two new permissions: eCommerce - View eCommerce, and Manage Storefront (with View) - and eCommerce Settings: Manage Application Settings (with Settings' View), for Settings → eCommerce. Roles made from the Manager template get the first two, and leave the settings out, as they do every app's.
    - **Settings → eCommerce**: whether guests may check out without an account (on), whether a customer's account waits for their e-mail to be verified (on), prices shown with tax or without it (with tax), and the prefix of the online orders' numbers (EC-, kept in capitals). They apply once checkout and customer accounts come (Phases D and E). Turned off, eCommerce's settings can be read but not changed.
    - **Storefronts**, in eCommerce's menu - each with:
      - a name of its own in the business, and a code: its address, in small letters, which no other storefront of izSuite has and izSuite does not keep for itself (www, admin, api, shop, store and the like);
      - its status - Draft, Live, Maintenance, Suspended or Archived - of which only Live will take orders;
      - the location it sells from, an active one the user may use;
      - its default currency and the others it shows, among the business's, and its default language and the others, among izSuite's active languages - each default kept among the others;
      - the countries it sells to, and its support e-mail and phone.
    - A new storefront starts at the location picked in the top bar, in the base currency and English, selling to the business's own country when its profile names one.
    - A storefront is seen where its location may be used; to anyone else it is not found. Each one added, changed or deleted is logged. A location a storefront sells from is kept: it is deactivated instead. Until orders come (Phase D), a storefront is deleted outright.
    - Its default warehouse waits for Inventory; its logo and brand colours come with the theme (Phase C).
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with eCommerce switched on by hand (its plan, Billing Starter, has it only from Premium) and EUR added to the business's currencies:
      - The sidebar showed eCommerce → Storefronts, and the page "No storefronts yet. Add one for each online store."
      - New Storefront started at Main Location, in USD and English, as Draft. "Rahman Online", code Rahman-Online, selling to Bangladesh and India, in EUR and Arabic besides, with a support e-mail and phone: "Storefront added.", the row reading rahman-online, USD "Also EUR", English "Also Arabic", "Bangladesh, India" and Draft.
      - Through the API the codes www, RAHMAN-ONLINE and -shop were refused: "izSuite keeps this code for itself: choose another.", "Another storefront has this code: choose another." and "Use letters, digits and dashes, starting and ending with a letter or digit."
      - Edited to Live, with Spanish as its default language - added to its languages: "Storefront saved.", the row Spanish "Also English, Arabic" and Live.
      - Settings → eCommerce opened on its defaults. Guest checkout off, prices without tax and the prefix web-: "eCommerce settings saved.", the prefix WEB- and "Online orders are numbered after it: WEB-00001."
      - The Activity Log named the storefront's addition and change, and the settings.
      - As a throwaway member who may view eCommerce alone: the sidebar showed eCommerce → Storefronts and no Settings, and the list had no New Storefront, Edit or Delete. Adding a storefront and reading Settings → eCommerce through the API were refused (403).
      - Delete, through its confirmation: "Storefront deleted." The Apps Center showed eCommerce on, and Sales Lite "Needed by eCommerce."
      - Found after the check: Manage Storefront brought Settings → eCommerce with it, so the Manager template would have handed out an app's settings. They have their own permission now, eCommerce Settings - covered by the tests, as the owner's view does not change.
      - The storefront, settings, member, the app's state, the business's currencies, the sign-in and the activity were then put back. The database matched its backup, but for migration 000211's changes.
  - **Phase A2 is built** (migration 000212):
    - **Each plan gives so many storefronts** (19.4): Storefronts per Business in the admin's plan form - empty for no limit, like its users and locations - kept in its versions too. Billing Starter starts at one; Standard and Premium with no limit.
    - A storefront counts unless it is archived. Adding one past the limit - or opening an archived one again - is refused: "Billing Starter includes one storefront. Upgrade the plan to add another." A business over a lower limit keeps its storefronts; it only cannot add more.
    - Settings → Storage & Usage shows the storefronts against the limit while eCommerce is on. The Pricing page and Settings → Apps & Subscription say each plan's storefronts where the plan has eCommerce.
    - A storefront saved without its status keeps the one it has; it went back to Draft before.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` on Billing Starter, with eCommerce switched on by hand:
      - Settings → Storage & Usage showed Storefronts "0 of 1".
      - With a storefront added through the API, the next one from New Storefront was refused in its dialog: "Billing Starter includes one storefront. Upgrade the plan to add another." Storage & Usage then read "1 of 1", Limit reached.
      - The Pricing page listed "Unlimited storefronts" for Premium - the plan with eCommerce - and none for Billing Starter and Standard.
      - The admin's plan form was left to the tests: signing in as the seeded admin would start its two-factor setup.
      - The storefront, the app's state, the sign-in and the activity were then put back. The database matched its backup, but for migration 000212's changes.
  - **Phase A3 is built** (migration 000213), and with it Phase A:
    - **Each storefront has its izSuite address**: its code under izSuite's storefront domain - rahman-online.store.izsuite.com, the domain set on the server (`ECOMMERCE_STOREFRONT_DOMAIN`).
    - **Domains of the business's own** (19.5), from Domains on each row of the Storefronts page - seen by whoever may view eCommerce, changed under a new permission, Domains: Manage Domains (with View eCommerce):
      - added waiting, each a name no other storefront of izSuite has and never under izSuite's own domain, with the TXT record that proves it is the business's (`_izsuite-verify.` before the name) and the CNAME record that points it at izSuite;
      - verified once its DNS shows that record - looked up when Verify is asked, its last look kept;
      - one verified domain is the storefront's primary one, the host the others redirect to; with none, the izSuite address is;
      - removed at any time, the primary one's place going back to the izSuite address, its orders and customers untouched.
    - **Which names izSuite answers for**, asked without signing in (`GET /storefront-hosts?domain=`): a storefront's izSuite address or a verified domain, with the storefront's code, status and primary host. The server asks before it issues a certificate for a name; the storefront's pages (Phase C) will ask where to redirect.
    - Each domain added, verified, made primary or removed is logged. Issuing the certificates and serving the storefront at its names are the server's: see the deploy note.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with eCommerce switched on by hand and a storefront, Rahman Online (rahman-online), added through the API:
      - Domains showed the izSuite address rahman-online.store.izsuite.com as Primary, and "No domains of your own yet. Add one, point it at izSuite, and verify it."
      - Shop.Rahman.Test added: "shop.rahman.test added. Give it its DNS records, then verify it.", Waiting for its DNS, with its TXT record (_izsuite-verify.shop.rahman.test → izsuite-verify=…) and CNAME (→ domains.store.izsuite.com).
      - Verify, against the real DNS: "shop.rahman.test is not verified yet: its TXT record _izsuite-verify.shop.rahman.test was not found. A DNS change can take some hours to reach everywhere - try again later." Its lookup was kept.
      - With the domain marked verified in the database, standing in for its DNS: Verified with Make Primary, then "Primary domain saved.", the domain Primary and the izSuite address offering Make Primary.
      - Without signing in, `/storefront-hosts` answered shop.rahman.test and rahman-online.store.izsuite.com with the code, Draft and the primary host shop.rahman.test; www.rahman.test, "izSuite has no storefront at this name." (404).
      - Remove asked once more ("Remove shop.rahman.test?"), then "shop.rahman.test removed.", the izSuite address Primary again.
      - As a throwaway member who may view eCommerce alone: the domains and their records, without Add, Verify or Remove.
      - Found while checking: a refused Verify left its row without its last look until the dialog opened again, and the view-only member was told to Verify. Both fixed.
      - The domains, storefront, member, the app's state, the sign-in and the activity were then put back. The database matched its backup, but for migration 000213's table.
- **Phase B: the catalog** - split on starting it (2026-11-20): B1, items published online; B2, categories, collections and filters (Manage Categories and Collections); B3, options and variants.
  - **Phase B1 is built** (migration 000214):
    - **A Catalog page** in eCommerce's menu, under a new permission group, Catalog: View (with View eCommerce and Items' View), Publish Products, and Manage Categories and Collections (for B2). It lists the business's items for sale - sold, or sold and bought - with their category, sales price, picture and online status (Not online, Draft, Scheduled, Published, Hidden or Archived), searched by name, number, SKU or barcode, and narrowed by that status and by category.
    - **An item's page in the catalog** puts it online, one listing per item (19.8 and 19.9):
      - its online title and link - made from the title unless given, and its own in the business - a short description, starting as the item's sales description, a full description with the editor's formatting, and search keywords;
      - pictures beside the item's own image - up to 12, JPG, PNG or WebP of up to 5 MB each, public as a storefront shows them, with thumbnails - and up to five https video links;
      - its status - Draft, Scheduled (from a later first day, Published from then), Published, Hidden or Archived - who sees it (everyone, signed-in customers, or only those with its link), and its first and last days, the business's;
      - how it sells - at the item's price, by a request for a quotation, by asking for the price, or not online - its availability without Inventory (Available, Out of Stock, or Limited with the quantity left), and its weight for shipping;
      - its SEO title and description, with how a search result would read, and its storefronts: every one - those added later too - or those chosen.
    - Published or scheduled, its item is active and - sold at its price - priced; a new listing of an item without a price starts sold by quotation. Each listing put in, changed or taken out, and each picture added or removed, is logged. Taken out, its pictures go with it and the item stays as it was; once orders come (Phase D), a listing will be archived instead.
    - A listed item without an image of its own shows its listing's first picture on the Catalog page. Customer groups' visibility waits for Doc 21; the storefront's pages that show the listings come with Phase C.
    - **Checked in the browser** on the second PC, as `user@izsuite.io` with eCommerce switched on by hand, a storefront, and four items: Ceramic Mug (Kitchen, sold, $8.00), Black Tea (sold and bought, $3.50), Office Paper (bought only) and Custom Shelf (sold, no price):
      - The Catalog page listed Black Tea, Ceramic Mug and Custom Shelf - not Office Paper - each Not online, Custom Shelf with "No price".
      - Ceramic Mug's page started from the item - "A sturdy mug for tea and coffee.", Draft, Everyone, every storefront - with the link ceramic-mug made from its title. With a full description, "dishwasher safe" in bold, keywords and Published: "Online product saved.", and the page Published.
      - Two pictures added through the API - the browser pane picks no files - showed as thumbnails; Remove took one off: "Picture removed."
      - Limited without a quantity: "Say how many are left to sell." under Left to Sell. With 12 it was saved, and the list read Published, "At its price · 12 left".
      - Custom Shelf started sold by quotation. At its price and Published it was refused: "The item has no sales price: give it one in Items, or sell it by quotation."
      - As a throwaway member who may view the catalog alone: Catalog in the sidebar, and an item's page read only - no Save, Take Out or Add Pictures.
      - Take Out of the Catalog asked first, then '"Ceramic Mug" taken out of the catalog.', the page Not online again.
      - Found while checking: the First Day and Last Day fields ran out of the page's side column, and a listed item without an image of its own showed no picture in the list although its listing had pictures. The days now stack, and the list shows the listing's first picture - checked with the mug put back and given one.
      - The listings and their pictures (their files too), items, category, storefront, member, item numbers, the app's state, the sign-in and the activity were then put back. The database matched its backup, but for migration 000214's changes.
  - **Phase B2 is built** (migration 000215), under Manage Categories and Collections (section 19.11):
    - **The Catalog page has three tabs** - Products (B1's list), Categories and Collections - each seen with the catalog's View.
    - **An item category's online side.** Categories lists each item category with how many of its items are in the catalog. Each gets:
      - its link, made from its name unless given, and its own in the business;
      - whether the storefront's menu lists it, and its place there;
      - a description with the editor's formatting, and its SEO title and description;
      - a picture - JPG, PNG or WebP of up to 2 MB, public, with a thumbnail - kept at once, and giving the category its online side if it had none.
    - **Collections** of the business's products, for a promotion, a season, a brand or a theme of its own (customer groups' wait for Doc 21):
      - a name and a link, each its own in the business;
      - up to 500 listings chosen by hand, shown in the order chosen - or, for a brand's, every listed product of the brand, while the business keeps brands;
      - Active or Hidden, first and last days (the business's), a description, an SEO title and a place;
      - a picture, once it is saved.
    - **A listing's attributes**, for the storefront's filters: up to 20 names and values, such as Material: Stoneware, each name once. The filters themselves (category, brand, price, availability, options, rating and attributes) come with the shop's pages in Phase C, options with B3 and ratings with Phase I.
    - Each online category saved, each collection added, changed or deleted, and each picture added or removed, is logged. Deleting an item category takes its online side and picture with it. Deleting a brand leaves its collections without it, to be given another.
    - **Checked in the browser** on the second PC, as `user@izsuite.io`. Set up: eCommerce switched on by hand, brands on, the categories Kitchen and Drinks, the brand Rahman, and three items in the catalog - Ceramic Mug (Kitchen), Black Tea (Drinks, Rahman) and Green Tea (Rahman):
      - Kitchen saved with place 1: "Online category saved.", /categories/kitchen, "Listed, place 1".
      - Drinks given the link kitchen: "Another category has this link." A picture given through the API (the browser pane picks no files) started its online side, drinks, and showed as a thumbnail.
      - A collection, Summer Sale, with two products chosen: "Collection added." A brand's, Rahman Teas: "Every listed product of Rahman (2)".
      - Summer Sale renamed Winter Sale with its link left blank: "Collection saved.", still /collections/summer-sale, as the form had said.
      - Ceramic Mug's attributes: Material: Stoneware, and Capacity with no value - "Give the attribute its value." under it. With 350 ml: "Online product saved."
      - As a throwaway member who may view the catalog alone:
        - Categories and Collections had no Edit, Delete or New Collection, and the mug's attributes were read only;
        - changes through the API were refused: "Your role does not allow this."
      - Delete asked first ("Delete Winter Sale? This can't be undone."), then 'Collection "Winter Sale" deleted.'
      - The brand Rahman deleted: Rahman Teas then read "None: its brand was deleted", and saving it asked "Choose one of your brands."
      - At phone width the tabs, the lists and the attributes fit.
      - Found while checking:
        - A form's link for a blank Link field was "product" for a collection or category with no name yet. For one already online, it was what its name would make, although the API keeps the link it has. Each now shows what the API will use.
        - Deleting an item or an item category left its listing's or online category's pictures behind, their rows and stored files: the database dropped the listing or online side alone. Both now go through their models, and a test checks it.
        - A brand's collection without its brand read "Every listed product of its brand (0)".
      - The collections, online categories and listings were then put back, with their pictures (their files too). So were the items, categories, brand, member, the app's state, the sign-in and the activity. The database matched its backup, but for migration 000215's changes. 000215 was rolled back and run again.
  - **Phase B3 is built** (migration 000216), under Publish Products (section 19.10):
    - **Options and Variants**, a card on a listed item's page, saved on its own:
      - up to three options, each a name and its values (Size: S, M, L): each name once, and each value of an option once;
      - with options, the items it groups as its variants: its own item first, then other items for sale of the same type that have no listing of their own and are no other listing's variants - up to 100;
      - each variant with a value of each option (no two the same), shown or hidden, its availability without Inventory (Available, Out of Stock, or Limited with the quantity left), and its weight (none: the listing's). Its SKU, barcode, price and image are its item's, kept in Items, so its SKU is its own.
    - While the listing is published or scheduled, each variant shown is of an active item, priced when sold at its price; a hidden one need not be. The same is checked when the listing is published.
    - Without options it has no variants: removing the last option removes them, and the listing's own availability counts again. With variants, the Selling card says each variant has its own, and the Catalog page says how many variants it has.
    - **An item grouped as another listing's variant** has no listing of its own. The Catalog page shows it as a Variant of that listing, and Not online leaves it out. Its page points to the listing.
    - Saving options and variants is logged. Taking the listing out takes its variants with it; deleting a variant's item takes that variant. Past orders keep their lines when a variant goes (Phase D). Stock per variant waits for Inventory (19.25).
    - **Checked in the browser** on the second PC, as `user@izsuite.io`. Set up: eCommerce switched on by hand, and five items for sale - T-Shirt S, M and L (TS-S, TS-M and TS-L, at $10, $10 and $12), T-Shirt XL without a price, and a Ceramic Mug - with S listed as Classic T-Shirt and the mug listed, both drafts:
      - Add an option made the listing's own item, T-Shirt S (TS-S), its first variant, "The listing's own item". The option: Size, with S, M, L, XL.
      - The variants' item list offered T-Shirt L, M and XL: not the mug, which is listed, nor S, already chosen.
      - M was given size S, like the first variant: "Another variant has these options." under it. L was Limited with no quantity: "Say how many are left to sell." With M and 5: "Options and variants saved.", and the Selling card read "Each variant has its own, under Options and Variants."
      - The Catalog page showed T-Shirt M and L as "Variant of Classic T-Shirt", and XL as Not online. M's page read "A variant of Classic T-Shirt: it is sold there, and its options are changed there.", and its link opened the listing.
      - XL was added through the API, then the listing published: refused, 'The variant "T-Shirt XL" has no sales price: give it one in Items, or hide the variant.' With XL hidden: "Online product saved.", Published. The status typed in the page stayed while the variants were saved.
      - As a throwaway member who may view the catalog alone: the four variants read only, with no Add, Remove or Save. The API refused changes: "Your role does not allow this."
      - At phone width the card fit.
      - XL removed: three variants saved. The option removed: "Options removed.", and the Availability field was back.
      - Found while checking: the Catalog page's row of a listing with variants still showed the listing's own availability. It now says how many variants it has: "At its price · 3 variants". Not online then listed T-Shirt XL alone.
      - Added after the check, as Pictures does: an item not yet in the catalog shows Options and Variants with "Put it in the catalog first, then sell it in sizes, colours… as its variants." It compiles, but was not seen in the browser.
      - The listings and their variants, items, member, the app's state, the sign-in and the activity were then put back. The database matched its backup, but for migration 000216's changes. 000216 was rolled back and run again.
- **Phase C: the storefront's pages** - split on starting it (2026-11-20):
  - C1, the store opens: its shop, search, filters and product pages, and the page of a store that is not open;
  - C2, its home, category and collection pages, and search suggestions;
  - C3, filters by price, options and attributes, each offering what the products shown have;
  - C4, theme and branding, with a preview before publishing (Manage Themes and Pages) - split on starting it into C4a, the theme and branding, and C4b, the homepage's sections and banner images;
  - C5, the contact and about pages, the four policies, and pages of the business's own up to the package's limit - split on starting it into C5a, the contact and about pages and the four policies, and C5b, pages of the business's own up to the package's limit;
  - C6, SEO - canonical links, the sitemap, robots rules, sharing images and product structured data - and the cookie notice - split on starting it into C6a, the SEO, and C6b, the cookie notice and its settings.
  - **Phase C1 is built** (no migration): the storefront at `/store/{code}`, its pages rendered on the server for search engines.
    - **Open or not.** A store is open while it is Live, its business has eCommerce on, and the business is not suspended or closed. Otherwise its address shows only its name and that it is closed for maintenance or not open, answering 503, and none of its products. An unknown store answers 404.
    - **What it shows**: the listings published - or scheduled and begun - and not over, of active items it sells, on every storefront or on this one. Those for everyone are in its lists and search; those kept for their link open at their link. Listings for signed-in customers wait for customer accounts (Phase E). A listing's variants are those shown, of active items.
    - **Its header and footer**: its name, a search, Shop all and the categories in its menu; its support e-mail and phone, whether prices include tax (when the business has taxes on), and "Powered by izSuite" (by the package from C4).
    - **Its home**: its categories and its eight newest products. C2 adds its collections, and C4 the sections its owner chooses.
    - **The shop**: every product, 24 a page. Searched by title, keywords, descriptions, SKU, brand or category; narrowed by category, brand and in stock only; sorted by newest, name or price. Its filters are kept in the address, shown as chips to remove, and cleared at once; when nothing matches, it says what to try.
    - **A product's page**: its pictures (its item's image first), brand, title, price, short description, and its variants to choose from - each with its price, stock, SKU and picture - then its description and details. One sold by quotation or on request, or not sold online, says so with the store's e-mail and phone. Buying comes with the cart (Phase D).
    - **Prices** (19.13): the item's sales price - a combo's components' sum when priced that way - with its tax added or taken out as Settings → eCommerce shows prices, in the storefront's currency at the day's rate (the base currency while it has none). The tax is the one a sale takes for no customer, as POS picks it. Price lists and promotions come with the cart and Phase H.
    - The public API: `GET /api/v1/stores/{code}`, `/products` and `/products/{slug}`, 120 a minute per address.
    - **Checked in the browser** on the second PC, signed out, as a customer. Set up: eCommerce switched on by hand, brands on, a live storefront, Rahman Shop (`/store/rahman-shop`), and Rahman Outlet in maintenance; Kitchen and Drinks in the menu; and six listings - Ceramic Mug (two pictures, a description and details), Black Tea and Green Tea (Rahman's, Green out of stock), Classic T-Shirt (sizes S, M and L, M out of stock and L with 3 left), Custom Shelf (by quotation), and Paper Cups, a draft:
      - The home showed the store's name, Shop all, Kitchen and Drinks, and five products - not the draft - with "From $10.00" for the T-shirt, "Price by quotation" for the shelf, and "Out of stock" under Green Tea. Its HTML came from the server whole, with the title "Rahman Shop".
      - Searching "coffee" found the mug by its keywords, the search shown as a filter to remove. Drinks with In stock only left Black Tea alone. Searching "bicycle": "No products match." and what to try.
      - The T-shirt's page: S at $10.00, "In stock", SKU TS-S. L: $12.00, "Only 3 left", TS-L. M: "Out of stock".
      - The mug's page: Shop / Kitchen / Ceramic Mug, the two pictures to choose from, its description and its details (Material: Stoneware, Capacity: 350 ml); its card in the shop showed the first picture's thumbnail. The shelf's page offered "E-mail us" and "Call +8801700000000".
      - Rahman Outlet: "The store is closed for maintenance. Please come back soon." (503). A made-up store: "There is no such store." (404). The draft's address answered 404 too.
      - At phone width the shop and a product's page fit, the filters one under another.
      - Found while checking: sorting by price put the shelf, which has no price, first from low to high. Those without a price now come last either way.
      - The storefronts, listings and their pictures (their files too), items, categories, brand, the app's state and the activity were then put back. The database matched its backup.
  - **Phase C2 is built** (no migration):
    - **A category's page** (`/store/{code}/categories/{link}`): its picture, name and description, then its products - searched, narrowed and sorted as on the shop. Its SEO title and description, else its name. The menu's categories open their pages, as does a product's breadcrumb.
    - **A collection's page** (`/collections/{link}`): its picture, name and description, then its products - those chosen, first in its own order ("Featured"), or a brand's - searched, narrowed and sorted. Only active collections within their days are shown; others answer 404.
    - **The home** shows the store's collections, with their pictures, before its categories and newest products.
    - **Search suggestions** (19.12): as a customer types two letters or more, the store's first six products that match, and the categories and collections by that name, each opening its page. Search still shows every product that matches.
    - The public API gains `GET /api/v1/stores/{code}/categories/{link}`, `/collections`, `/collections/{link}`, `/suggestions?search=` and `/products?collection=`.
    - **Checked in the browser** on the second PC, signed out. Set up: Phase C1's store, Kitchen's description and picture, and three collections - Summer Sale (Black Tea, the mug and the T-shirt, in that order, with a picture), Rahman Teas (the brand's) and Old Stock (hidden):
      - The home showed Summer Sale, with its picture, and Rahman Teas - not Old Stock.
      - Summer Sale's page: its picture and description, then Black Tea, Ceramic Mug and Classic T-Shirt, in its order. By price, high to low, the T-shirt came first; back on Featured, its order again. Its filters offered no category.
      - Kitchen, from the menu: "Kitchenware - Rahman Shop" in the tab, its description with its formatting, its picture and the mug, and Kitchen marked in the menu. The mug's breadcrumb links to it.
      - Typing "tea" suggested Green Tea, Black Tea, the mug (its description mentions tea) and the collection Rahman Teas. Choosing the collection opened it - the brand's two teas - and closed the suggestions. "kit" suggested the Kitchen category.
      - Old Stock's address answered 404, "There is no such collection in this store.", and so did a made-up category.
      - At phone width, the collection's page and the suggestions fit.
      - Found while checking: a collection's sort said "Newest" while its products showed in its own order. It now offers "Featured", its order, first.
      - The collections, the store, its listings and pictures and the rest were then put back. The database matched its backup.
  - **Phase C3 is built** (no migration): filters by price, options and attributes (19.11), on the shop and on category and collection pages.
    - **What they offer**: what the products in view have - the lowest and highest price, as placeholders for a range in the store's currency; each option's values (Size: S, M, L), by name across products; and each attribute's values (Material: Cotton, Stoneware). Each list follows the page's search, category, collection, brand and stock.
    - **How they narrow**: a product is within a price range when its price - or, with variants, one of theirs - is in it, as the store shows prices (its tax and currency). Options pass when one of its variants has every value chosen; attributes, when it has each one. Names and values are matched without regard to case. They are kept in the address (`price_min`, `price_max`, `option[Size]`, `attribute[Material]`) and shown as chips to remove.
    - Worked out from the store's listings - up to 2,000 at a time - with the same pricing as the pages, not in the database, so a price filter is exact whatever an item's tax.
    - The public API gains `GET /api/v1/stores/{code}/facets`, and `/products` takes `price_min`, `price_max`, `option[...]` and `attribute[...]`.
    - **Checked in the browser** on the second PC, signed out, with Phase C2's store:
      - The shop offered a price range from 3.5 to 12, Size (S, M, L), Capacity (350 ml) and Material (Stoneware).
      - Size L left the T-shirt, with the chip "Size: L". A price from 5 to 9 left the mug.
      - Kitchen's page offered only what the mug has: a price of 8, Capacity and Material - no Size.
      - Found while checking: at phone width the price range ran off the page, and its chip said only "5 to 9". The range now fits, and the chip reads "Price: 5 to 9".
      - The store and its listings, collections and pictures were then put back. The database matched its backup.
  - **Phase C4a is built** (migration 000217): a storefront's theme and branding (19.7), under a new permission, eCommerce → Themes and Pages → Manage Themes and Pages.
    - **What it sets**: its logo (in its header, in place of its name) and favicon, chosen from pictures uploaded for it; three colours (main, background and text); a font (the system's, sans, serif or rounded); its name on the left or in the middle, and an announcement line across the top; product cards standard or minimal, their pictures square, portrait or landscape; the footer's words, its address and opening hours; and links to its social pages (Facebook, Instagram, X, YouTube, TikTok, LinkedIn and WhatsApp), each starting https://.
    - **Draft, preview, publish.** Changes are saved as a draft, and its customers keep seeing the theme published - izSuite's look until one is. Preview opens the store in the draft, under a bar that says so, for those who may manage themes - even a store not yet Live, which its customers still see closed. Publish puts the draft in the theme's place; Discard Draft brings the published theme back. Pictures neither theme shows are deleted then. A storefront keeps up to 20 pictures.
    - **"Powered by izSuite"** shows in the footer unless the plan removes izSuite's branding (the package's existing Removes Branding term).
    - A theme changes no product or order, only how the store looks, and takes no code of its own: 19.7's custom scripts are not offered.
    - The API gains `GET` and `PUT /api/v1/ecommerce/storefronts/{storefront}/theme`, and `POST` `.../theme/pictures`, `.../theme/publish` and `.../theme/discard`. The public `GET /api/v1/stores/{code}` gains the theme, and every store call takes `preview=1` with the token of one who may manage themes.
    - **Checked in the browser** on the second PC, with Phase C2's store:
      - The Theme page: two pictures - uploaded through the API, as the browser pane cannot pick files - chosen as the logo and the favicon. A link starting http:// was refused with "Give the link starting https://.". Then "Draft saved.".
      - Preview: the bar, the announcement, the logo in the middle, serif headings, tall cards without frames, the store's favicon, and the footer's words, address, hours and social links. Leaving the preview: izSuite's look.
      - "Theme published.": the store, signed out, in the theme, rendered on the server. A new draft discarded: "Draft discarded.", and Serif back.
      - The outlet, not yet Live, previewed: "Its customers see it closed."; signed out, closed (503).
      - A member who may only view the catalog: no Theme on the Storefronts page, the Theme page's address took them to Items, and the outlet stayed closed to them.
      - At phone width the Theme page and the themed store fit.
      - Found while checking: the colours' fields were too narrow for their codes (now one colour a row); headings kept izSuite's font (they now take the store's); the minimal cards kept their frame, then lost their pictures' background (both fixed); the preview's bar said customers saw the theme published when they saw the store closed (it now says which); the address and opening hours were cramped side by side (now one under the other).
      - The store, its listings, collections and pictures - the theme's among them - the member, the app's state, the sign-in and the activity were then put back. The database matched its backup, but for migration 000217's changes. 000217 was rolled back and run again.
  - **Phase C4b is built** (no migration): the home page's sections and banner images (19.7), part of the theme - drafted, previewed and published with the rest of it, under Manage Themes and Pages.
    - **Its sections**, up to ten, in the order chosen - each moved up or down, removed, or added at the foot:
      - a banner: one of the storefront's pictures (among its 20), a heading - the store's name when empty - a line of text, and a button to the shop, a category or a collection;
      - collections: those chosen, in the order chosen, or every one the store shows;
      - categories: those in its menu;
      - products: the newest, or a collection's in its order - 4, 8 or 12 of them - with "See all";
      - text: a heading and words of its own, their lines kept.
    - A section with nothing to show today is left out - a collection hidden or over, say - and a banner's button that named it goes to the shop. With no sections, the home shows a link to the shop.
    - Until its sections are chosen, a store's home is the one it had: its name with Shop all, its collections, its categories and what is new.
    - The first banner's heading is the page's h1; with no banner, the store's name is, hidden from view.
    - A banner's picture stays while either theme shows it; once neither does, it goes when the draft is published or discarded, as the logo's and favicon's do.
    - The API: the theme gains `home`, and the Theme page's call `choices` (the business's categories and collections). The public `GET /api/v1/stores/{code}/home` gives the sections, each with what it shows.
    - **Checked in the browser** on the second PC, with Phase C2's store:
      - The Theme page's Home Page card showed the four sections the home had. A wide picture - uploaded through the API, as the browser pane cannot pick files - was chosen for the banner, with a heading, a line and a button "See the sale" to Summer Sale. A Text section and a Products section of Summer Sale (4) were added, and the products moved up to second.
      - "Draft saved.", then the preview: the banner over its picture, "On sale" with Summer Sale's products and "See all", then Collections, Categories, New in and About us, its two lines kept. The banner's heading was the page's h1. Signed out, the store kept its home.
      - "Theme published.": the store, signed out, with the new home, rendered on the server.
      - A banner's button pointed at a category with none chosen: "Choose where it goes." under the field. Kitchen chosen: "Draft saved.". Then "Draft discarded.", and Summer Sale was back.
      - A member who may only view the catalog, asked through the app itself: the published home even with `preview=1`, and 403 on the theme.
      - At phone width the home and the Home Page card fit.
      - Found while checking: a banner's upload button was read out as "Upload a picture for the banner picture"; it now says "for the banner".
      - The store, its listings, collections, pictures and theme, the member, the app's state, the sign-in and the activity were then put back. The database matched its backup.
  - **Phase C5a is built** (migration 000218): a storefront's pages (19.6) - its about and contact pages and its four policies (shipping, return and refund, privacy, and terms and conditions) - under Manage Themes and Pages.
    - **Each page** starts unwritten, with its title, at a link of its own, the same in every store: `/pages/about`, `/pages/contact`, `/pages/shipping-policy`, `/pages/return-and-refund-policy`, `/pages/privacy-policy` and `/pages/terms-and-conditions`. Its words are rich text with headings of two levels, cleaned as they are kept. It has a title for search engines (its own title unless one is given) and a description.
    - **Published or a draft.** A draft is seen only by those who may manage pages, previewing the store; its customers are told "There is no such page in this store." (404). A policy, or the about page, is published only with words in it: "Write the page before publishing it." Each page added, changed, published or unpublished is logged.
    - **The footer** links to the pages published, in its order, except those kept out of it, which are still at their links.
    - **The contact page** shows the storefront's support e-mail and phone and its theme's address and opening hours - the draft theme's while previewing - then any words of the business's own. A form for customers to write in waits for enquiries (Phase H, with requests for a quotation).
    - The preview's bar now says the store is shown with its drafts - its theme and pages.
    - The API gains `GET /api/v1/ecommerce/storefronts/{storefront}/pages`, and `GET` and `PUT .../pages/{about|contact|shipping|returns|privacy|terms}`. The public side gains `GET /api/v1/stores/{code}/pages/{slug}`, and the store's `pages` for its footer.
    - **Checked in the browser** on the second PC, with Phase C2's store:
      - Storefronts → Pages: the six pages, each "Not written yet".
      - The Privacy Policy written with a heading and a subheading: "Page saved as a draft.". Signed out, its link was not found; previewed, the page showed with its headings.
      - Published: "Page saved and published.". The contact page, published with no words of its own, showed the store's e-mail and phone. The About page, published with no words: "Write the page before publishing it." under its words.
      - Signed out, rendered on the server: the footer linked Contact Us and Privacy Policy; the privacy page kept its headings and was titled "Privacy Policy - Rahman Shop"; About, unpublished, answered 404.
      - A member who may only view the catalog, asked through the app itself: 403 on the pages and on saving one. The Pages action and the pages' addresses follow the same rule as the Theme's, checked in Phase C4a.
      - At phone width the editor, the Pages list and the store's pages fit.
      - Found while checking: the preview's bar spoke of the draft theme alone; the contact page's hint said its details came under the words, where they come above; and the Pages list said "Linked" of pages not yet written - it now says whether each is in the footer.
      - The store and the rest were then put back. The database matched its backup, but for migration 000218's changes. 000218 was rolled back and run again.
  - **Phase C5b is built** (migration 000219): pages of the business's own on a storefront (19.6), up to the plan's limit on each storefront, under Manage Themes and Pages.
    - **A page of its own** has what a standard page has - its title, its words with headings, published or a draft, its link from the footer and its SEO - and the link it is given, or one made from its title (numbered when it is taken): letters, digits and dashes that no other page of the storefront has and no standard page keeps. It has its place in the footer, after the standard pages, lowest first. Each page added, changed or deleted is logged.
    - **The plan's limit**: Pages per Storefront, a new term of the plans, as the admin sets it - empty for no limit. Billing Starter starts at three; the paid plans have no limit, as for storefronts. Past it: "Billing Starter includes 3 pages of your own on each storefront. Upgrade the plan to add another." The standard pages are outside it. The Pages list says how many are in use; the plans say it where they have eCommerce ("Unlimited pages per storefront"); the admin's plans list and versions show it.
    - The API gains `POST /api/v1/ecommerce/storefronts/{storefront}/pages`, and `GET`, `PUT` and `DELETE .../pages/{id}`; the plans gain `storefront_page_limit`.
    - **Checked in the browser** on the second PC, with Phase C2's store, the business on Billing Starter:
      - Pages: "Pages of Your Own", "0 of the 3 your plan gives each storefront, besides the pages above."
      - New Page: Wholesale, published - "Page added.", its editor opened at its id, with the link wholesale and the place 1. Its link changed to privacy-policy: "This link is kept for one of its standard pages: choose another." under the field.
      - Careers (place 0) and Size Guide added through the API; a fourth was refused: "Billing Starter includes 3 pages of your own on each storefront. Upgrade the plan to add another." The list then said all three were in use, with no New Page.
      - Signed out, rendered on the server: the footer linked Careers, Wholesale and Size Guide, by their places, and Wholesale's link opened it.
      - Size Guide deleted from the list: "Page deleted.", and room for another.
      - The pricing page: "Unlimited pages per storefront" for the plan with eCommerce.
      - A member who may only view the catalog, asked through the app itself: 403 on adding and on deleting a page.
      - At phone width the list and the editor fit.
      - The admin's plan form is checked by the tests alone: the browser check does not sign in as the admin.
      - The store, its pages and the rest were then put back. The database matched its backup, but for migration 000219's changes. 000219 was rolled back and run again.
  - **Phase C6a is built** (no migration): a storefront's SEO (19.36).
    - **Search and Sharing**, a new card on the Theme page, is drafted, previewed and published with the rest of the theme: the home's title (the store's name when empty) and description - the description too of any page with none of its own - its sharing picture (one of the storefront's pictures; none: its logo), and whether search engines may list the store (on). It shows the store's sitemap address, for Google Search Console and the like.
    - **Every page of the store** says, rendered on the server: its title and description; its canonical link - its address with no search, filters, sort or preview, but with its page number past the first; what a shared link shows (Open Graph and X cards: title, description, picture - its own, else the store's sharing picture, else its logo - address, type and the store's name); and whether search engines may list it.
    - **Kept from search engines** ("noindex, follow"): every page while the published theme says not to list the store; a preview; a store not open (503) or not there; a product kept for its link; a search; and a list filtered or sorted.
    - **Product structured data**: a product's page carries a schema.org Product - its name, description, pictures, SKU, brand and link - with, sold at its price, its offer: its price, or its variants' lowest and highest, in the store's currency, and whether it is in stock.
    - **The sitemap**, at `/store/{code}/sitemap.xml`: its home and shop, its categories' pages and those of the collections it shows, the products it lists for anyone and its published pages, each with the day it last changed. It is empty while the theme says not to list the store, and there is none (404) for a store not open.
    - **A robots.txt of its own waits for its own domain**: crawlers read robots.txt only at a host's root, and stores are served under `/store/{code}` until the server serves their domains (Phase A3). Canonical links and the sitemap use those addresses, on the host they are served from, and move with them then.
    - The API: the theme gains `seo`, a product's page `listed`, and the public side `GET /api/v1/stores/{code}/sitemap`.
    - **Checked in the browser** on the second PC, with Phase C2's store, the mug's pictures, and the Custom Shelf kept for its link:
      - The Theme page's Search and Sharing card: a title, a description and a sharing picture (uploaded through the API). "Draft saved."; previewed, the home's title, description and picture were the draft's, kept from search engines, its canonical link without the preview.
      - Published, then signed out, rendered on the server: the home's title, description, canonical link, Open Graph and X tags with the sharing picture, and "index, follow". The mug: its own picture to share it by, and its Product data with its pictures and price. The T-shirt: an AggregateOffer of its variants' prices. The shelf, kept for its link: "noindex, follow", and no offer (it is sold by quotation). A search of the shop: noindex, its canonical link the shop's. The shop's second page: its canonical link kept "?page=2". A collection: its own picture.
      - The sitemap listed the home, the shop, both categories, the two collections shown (not the hidden one) and the four products listed (not the shelf, nor the draft cups), with their days.
      - "Let search engines list the store" unticked and published: every page "noindex, follow", and an empty sitemap. The outlet, not open: 503 with noindex, and no sitemap (404).
      - A member who may only view the catalog, through the app itself: 403 on saving the theme.
      - At phone width the new card fits.
      - Found while checking: a product with no pictures carried an empty list of them in its data; it now leaves them out.
      - The store and the rest were then put back. The database matched its backup.
  - **Phase C6b is built** (migration 000220): the storefronts' cookie notice, under Settings → eCommerce's cookie and consent settings (19.37).
    - **Settings → eCommerce** gains a Cookie Notice: whether the storefronts show their visitors one (on), and its words - empty for izSuite's own: "This store uses the cookies it needs to work. With your consent, it may also use cookies that help it understand how it is used." A form that sends neither, as one from before this phase would, keeps them as they are.
    - **On a storefront** the notice sits at the foot of every page, rendered on the server, until the visitor chooses Accept All or Only Essential. The choice is kept for a year in a cookie of the store's own path, so each store asks once. The notice links to the store's privacy policy once it is published, in the footer or not.
    - Nothing optional is set yet: a storefront keeps only the cookies it needs (its team's preview). Analytics and the like, when a store has them, are to wait for Accept All (`useStoreConsent().allowsOptional`).
    - The API: the settings gain `cookie_notice` and `cookie_notice_text`; the store's own call gains `cookie_notice` (its words, and whether its privacy policy is published - or null).
    - **Checked in the browser** on the second PC, with Phase C2's store:
      - Settings → eCommerce: the Cookie Notice, on, with izSuite's words shown as its placeholder. Words of the business's own saved: "eCommerce settings saved."
      - The store: the notice at its foot with those words; once the privacy policy was published (kept out of the footer), a link to it.
      - Only Essential: the notice went, and stayed gone on the shop's page; the choice was kept in a cookie of the store's path alone (the workspace's pages do not see it). Rendered on the server: the notice for a visitor with no choice, none for one who had chosen.
      - The notice switched off: none on the store, and its words kept.
      - A member who may only view the catalog, through the app itself: 403 on reading and on saving the settings.
      - At phone width the notice fits, its words above its buttons.
      - The store, the settings and the rest were then put back. The database matched its backup, but for migration 000220's changes. 000220 was rolled back and run again.
- **Phase D: cart and checkout** - the cart and its prices; shipping zones and methods, and pickup locations; checkout in its seven steps, the contact found or made, consent, the payment methods without a gateway, the order and its confirmation e-mail. Permissions Manage Shipping and Manage Payment Methods. Split on starting it (2026-11-21), with these defaults:
  - **D1, shipping zones and methods, and pickup points** (19.26), in Settings → eCommerce under a new permission, Manage Shipping:
    - a zone: its name and countries, narrowed if wished to regions, cities and postal codes (a code, or its start with `*`). An address takes the methods of the first zone it matches, in the zones' order;
    - a zone's methods: flat rate, free, by weight, by order value or by quantity (each in bands), local delivery, or quoted after the order. Each has its price in the base currency, its tax (none, or a sales tax rate), its delivery days, the order values and weights it takes, the categories it carries (every product unless some are chosen), and is on or off. Carrier rates wait for the carriers' integrations;
    - pickup points: the business's active locations, each switched on with its instructions and when an order is ready. Pickup is free;
    - only physical products need shipping: digital products and services do not.
  - **D2, payment methods without a gateway** (19.22), in Settings → eCommerce under a new permission, Manage Payment Methods: bank transfer, mobile money or wallet, cash on delivery and pay on pickup. Each takes money into an active payment account that takes customer payments, for orders in that account's currency; has its instructions to the customer; may ask for the customer's payment reference; and is offered for the countries, order amounts and delivery (shipped, picked up or either) chosen. Cash on delivery is for shipped orders and pay on pickup for picked-up ones. Cards and gateways wait for the payment integrations, and the customer's credit for signed-in customers (Phase E).
  - **D3, the cart** (19.13, 19.14): kept by the API for each visitor of a storefront, by a token their browser keeps. Priced as the store shows prices: the item's price, or with full Sales the storefront's own price list (new on the storefront), with its quantity breaks. Its estimated tax is shown. Add to Cart on a product's page for one sold at its price, its variant chosen, never beyond what a Limited product has left, up to 50 lines; a cart page; and carts expire after Settings → eCommerce's days (30). A cart reserves nothing.
  - **D4, checkout and the order** (19.15 to 19.20):
    - the steps on one page: customer details, addresses, delivery or pickup, payment, review, terms and privacy, then the confirmation. Guests may check out unless Settings → eCommerce says not; signing in comes with Phase E. A cart of digital products or services skips delivery;
    - shipping only to the storefront's countries. The API works out the totals again, and refuses an order whose total is no longer the one reviewed. Placing an order twice makes one;
    - the order: numbered from the order prefix (EC-00001), Pending, its payment Unpaid, its fulfilment Unfulfilled (Not Required when nothing is shipped), with its lines' prices and where they came from, taxes, shipping, and the customer's details and addresses as given;
    - its contact: the one an earlier guest order made for the same e-mail, else a new customer contact. A contact of the business's own with that e-mail is not linked while the e-mail is unverified (Phase E links verified customers); the two are merged by hand if they are one;
    - the terms and privacy policy accepted, recorded on the order. Marketing consent is optional and apart: given, it is recorded as the contact's consent (Promotions and Offers, by e-mail, from the website form);
    - its invoice is issued when it is confirmed (Phase F). The business's Orders page comes with Phase F; until then each order is in the Activity Log.
  - **D5, the confirmation** (19.20, 19.35): the order's page for the customer at its own secure link - its number, lines, totals, delivery, payment status and instructions - and the order-received e-mail, through the queue.
  - **Phase D1 is built** (migration 000221): shipping and pickup (19.26), in Settings → eCommerce under a new permission, eCommerce → Shipping → Manage Shipping.
    - **Zones**, in an order. Each has a name of its own in the business, its countries, and - if wished - the regions, cities and postal codes it is narrowed to, up to 200 of each. A postal code may be a start followed by `*`: 12* holds 1207.
    - **An address takes the methods of the first zone it is in**: its country among the zone's, and its region, city and postal code among those the zone names. Names match whatever their case, postal codes whatever their spaces. So a narrow zone (Inside Dhaka) goes before a wide one (Bangladesh).
    - **A zone's methods**, in an order:
      - flat rate and local delivery (by the business itself), each at a price; free; or quoted after the order, with no price yet;
      - by weight, by order value or by quantity: up to 20 bands, each from a value at a price, kept from the lowest. An order pays the band of the highest start it reaches; one below the first start is not offered the method;
      - each with its tax (none, or a sales tax rate that is on, while the business has taxes on), its delivery days, the order values and weights it takes, the categories it carries, and on or off. With categories chosen, a method is offered only when every product shipped is of them.
    - Prices and order values are in the base currency, as items' prices are; weights in kg. A zone deleted takes its methods with it. Each zone, method and pickup change is logged.
    - **Pickup**: the business's active locations - and an inactive one still offered - each switched on with its instructions and when an order is ready ("Ready in 2 hours"). An inactive location is not switched on. Pickup is free, and every storefront offers it at the locations switched on.
    - **What a checkout will be offered** (`ShippingRates`, for Phase D4): the address's zone, and its methods that are on and take the order, each at its price - none yet when quoted. An order's weight comes from the catalog: each listing's or variant's weight, in kg, g, lb or oz, turned into kg.
    - **Who may**: Manage Shipping comes with eCommerce's View and Settings' View. The Settings → eCommerce tab shows for it or for Manage Application Settings, each card as the role allows. Roles made from the Manager template get it, as they get CRM's rules in Settings → CRM.
    - The API: `GET /api/v1/business-settings/ecommerce/shipping`; `POST`, `PUT` and `DELETE` on `.../shipping-zones` and `.../shipping-methods`; and `PUT .../pickup-points/{location}`.
    - **Checked through the API** on the second PC, not in the browser, which this session could not drive. As `user@izsuite.io` with eCommerce switched on by hand, and as two throwaway members:
      - Shipping read empty, with Main Location as a pickup point not offered, no tax rates (taxes off) and USD.
      - Inside Dhaka (Bangladesh; Dhaka; postal codes 12* and 1000) and Bangladesh were added at places 1 and 2: "Shipping zone added.". Bangladesh again: "Another shipping zone has this name."; a postal code *12 was refused.
      - Methods: Local Delivery ($2.50, 0-1 days) and Free Over 50 in Inside Dhaka. In Bangladesh, Courier by weight - bands given from 5 kg then 0, kept from 0: $6, then $12 from 5 kg, up to 20 kg, 2-4 days - and Freight, quoted, from 20 kg. A quantity method with no rates: "Give at least one rate."; weights from 10 to 2: "The heaviest weight is below the lightest."
      - Pickup at Main Location with its instructions and "Ready in 2 hours": "Pickup saved.".
      - A member with eCommerce's settings alone: 403 on shipping. A member with Manage Shipping alone: shipping read, and Standard ($5) added to Bangladesh.
      - What a checkout would be offered (`ShippingRates`, in tinker): Dhaka 1207 with an order of $60, Inside Dhaka's Local Delivery 2.50 and Free Over 50; Sylhet at 6 kg, Courier 12.00 and Standard 5.00; at 25 kg, Freight (quoted) and Standard; Kolkata, no zone.
      - Settings → eCommerce, rendered on the server for the Manage Shipping member: the Shipping card, without the general settings. Its dialogs are left to the [testing checklist](#testing-checklist).
      - The zones, methods, pickup, members, tokens, the app's state and the activity were then put back. The database matched its backup, but for migration 000221's tables.
  - **Phase D2 is built** (migration 000222): the storefronts' payment methods without a gateway (19.22), in Settings → eCommerce under a new permission, eCommerce → Payment Methods → Manage Payment Methods.
    - **Each method**, in an order, has a name of its own in the business, which customers see; its type - bank transfer, mobile money or a wallet, cash on delivery, or pay on pickup; the payment account it takes money into, an active one that takes customer payments (or its own still); and its instructions, shown at checkout and with the order.
    - **A bank transfer or mobile money** must say how to pay. Its customers may give their payment's reference at checkout: not asked, optional (a transfer's to start) or required (mobile money's). It may be for shipped orders, picked-up ones, or either.
    - **Cash on delivery** is for shipped orders, and paying on pickup for picked-up ones. Neither asks a reference.
    - **It is offered** for orders in its account's currency, in the countries it names (none: every one), and within its order amounts (the order's total).
    - **What an order will be offered** (`EcommercePaymentOptions`, for Phase D4): the methods on, whose account is active, takes customer payments and is in the order's currency, for its country, amount and delivery. An order with nothing to ship or collect - digital products and services - is offered only the methods for either.
    - Each method added, changed or deleted is logged. A method whose account is switched off, or stops taking customer payments, is kept but says it cannot be used, and is not offered. Orders placed by a method (Phase D4) will keep what it was.
    - **Who may**: Manage Payment Methods comes with eCommerce's View and Settings' View. Roles made from the Manager template get it, as they get Manage Shipping.
    - Cards and gateways wait for the payment integrations, and the customer's credit for signed-in customers (Phase E). Confirming the payments comes with Phase F.
    - The API: `GET`, `POST`, `PUT` and `DELETE /api/v1/business-settings/ecommerce/payment-methods`.
    - **Checked through the API** on the second PC, not in the browser. As `user@izsuite.io` with eCommerce switched on by hand and two USD accounts made for the check (City Bank and Cash Box), and as two throwaway members:
      - The methods read empty, with both accounts to choose from.
      - A bank transfer with no instructions: "Tell customers how to pay: the account or number to pay into." With them, for Bangladesh and orders up to 5,000: "Payment method added.", its reference optional.
      - bKash (mobile money) with its reference required. Cash on Delivery from 10, sent with a required reference and for picked-up orders: kept with none, for shipped orders.
      - A member with Manage Payment Methods alone added Pay at the Shop. A member with Manage Shipping alone was refused the methods (403).
      - "bKash" again: "Another payment method has this name."; amounts from 50 to 10: "The highest order amount is below the lowest."
      - What orders would be offered (`EcommercePaymentOptions`, in tinker), in USD to Bangladesh: $120 shipped, Bank Transfer, bKash and Cash on Delivery; picked up, Bank Transfer, bKash and Pay at the Shop; nothing to deliver, Bank Transfer and bKash. To India: bKash and Cash on Delivery. $6,000: bKash and Cash on Delivery. $5: Bank Transfer and bKash. In euros: none.
      - Settings → eCommerce, rendered on the server for the member with Manage Payment Methods: the Payment Methods card alone. Its dialog is left to the [testing checklist](#testing-checklist).
      - The methods, accounts, members, tokens, the app's state and the activity were then put back. The database matched its backup, but for migration 000222's table.
  - **Phase D3 is built** (migration 000223): the cart (19.13, 19.14).
    - **A visitor's cart** needs no sign-in. Its first product makes it, on the storefront, and gives back a token, which a cookie of the store's keeps for a year; only the token's hash is kept. It lasts while it is used: Settings → eCommerce's Keep Carts For (30 days to start, 1 to 365) counts from its last use, and then it expires (hourly). A cart is not an order or a sale, and reserves nothing.
    - **Add to Cart**, on the page of a product sold at its price: how many, whole ones, with its options chosen when it has variants. It must be one the store shows, in stock, and never more than a Limited one has left, counting what the cart has already. A product sold by quotation or on request, out of stock, or with no price is refused, and so are a 51st line and more than 999 of a product. A refused first product makes no cart.
    - **The cart page** (`/store/{code}/cart`): each line's picture, product and options, its price - and, once, the price it was shown at before when that changed - its quantity, changed one at a time or typed, its amount, and Remove. The header shows how many products the cart holds.
    - **Read as the store sells now**, every time:
      - a line whose product the store no longer shows or sells at its price, or whose variant it no longer shows, is Unavailable;
      - one out of stock is Out of Stock, and one beyond what is left is Limited, with what is left;
      - such lines are left out of the totals, and checkout waits until they are changed.
    - **Prices** (19.13): with full Sales, a storefront may sell at a price list of its own - a new Price List field on the storefront, an active list in its currency for its location - with the list's quantity breaks, on its pages and in the cart. Otherwise each item's own price. A customer's own price list waits for signed-in customers (Phase E), and promotions for Phase H. The shop's sorting by price still goes by the items' own prices.
    - **Totals** are worked out as the invoice will be (`SalesLines`, `SalesCalculator`), with the tax a sale takes for no customer: the subtotal as the store shows prices, then the tax - on its own line when prices are shown without it, said under the total when they include it - and the total. Shipping comes at checkout.
    - A cart's changes are not business activity: none is logged.
    - The API, without signing in: `GET /api/v1/stores/{code}/cart`, `POST .../cart/lines`, and `PUT` and `DELETE .../cart/lines/{line}`, the cart known by `X-Cart-Token`. Besides: the storefront's `price_list_id` and its options' `price_lists`, the settings' `cart_days`, and `php artisan ecommerce:expire-carts`, scheduled hourly.
    - **Checked through the API** on the second PC, not in the browser, signed out. On a live store made for the check, Rahman Shop (`rahman-shop`), with Ceramic Mug ($8), Classic T-Shirt (S out of stock, M hidden, L with 3 left) and Custom Shelf (by quotation):
      - The cart read empty without a token. Two mugs: "Added to your cart.", a 48-character token, $16.00, ready.
      - The T-shirt in S: "Out of stock." - with no token sent, and no cart made. In M (hidden): "Choose its options."; four in L: "Only 3 left."; two in L: added, Size L, TS-L, $12.00. The shelf: "This product is not sold online at a price: ask the store about it."
      - The mugs set to 3. Then, in the database, the mug's price to $9 and L's stock to 1: the cart read the mug at $9.00, "was 8.00", and L Limited with 1 left, outside the $27.00 total, not ready. L set to 2 again: "Only 1 left."; removed: ready.
      - Another token: "Your cart has expired. Add the products again." (404).
      - Settings → eCommerce: Keep Carts For 0, "Keep carts for a day at least."; 14 saved, and the cart's expiry moved to 14 days from its next use.
      - Rendered on the server: the T-shirt's page with Add to Cart and the header's cart; the shelf's with "Ask us for a quotation" and no Add to Cart; the cart page, "Your Cart", loading in the browser, kept from search engines (noindex). The rest is left to the [testing checklist](#testing-checklist).
      - A storefront's price list needs full Sales, which the dev business does not have: the tests check it.
      - The store, items, listings, variants, cart, settings, token, item numbers, the app's state and the activity were then put back. The database matched its backup, but for migration 000223's changes.
  - **Phase D4 is built** (migration 000224): checkout and the order (19.15 to 19.20).
    - **Checkout** (`/store/{code}/checkout`, from the cart page) has its steps on one page:
      1. the customer's details: name, e-mail, phone as Settings → eCommerce asks (required to start, optional, or not asked), and a company if they buy for one;
      2. the billing address, in any country, and - with something to deliver - whether it is delivered there, else an address of its own in a country the store sells to;
      3. delivery: the shipping methods of the zone the address is in (Phase D1), each at its price as the store shows prices, free, or quoted after the order - or a pickup point, free;
      4. payment: the ways to pay offered for the order's currency, country, total and delivery (Phase D2), with the chosen one's instructions, and the customer's payment reference where the method takes one;
      5. and 6. the review - products, delivery, tax and total - a note when the store asks for one, the terms and privacy policy to accept (linked once published), and marketing consent, optional and apart;
      7. the confirmation: the order's number, how to pay it, how it reaches the customer, and what was ordered.
    - What the cart comes to is worked out by the API again whenever a choice changes, and once more when the order is placed. A cart of digital products and services alone skips delivery, and is offered only the ways to pay for either delivery.
    - **Placing an order** needs the store Live and taking guests; every line of the cart buyable; the delivery and payment chosen still offered; the shipping address in a country the store sells to; the payment's reference when the method requires it; the terms accepted; and the total the customer reviewed - else "The total is now USD 21.00: review your order, then place it again." (409). Signing in comes with Phase E, so a store with guest checkout off takes no orders until then. The team previewing a store not yet Live may try its checkout, but not place an order.
    - **A cart becomes one order at most**: placed again, it answers with the same order. Nothing is reserved or counted down: confirming the order does that (Phase F).
    - **The order** is numbered from Settings → eCommerce's prefix (EC-00001, its own sequence) and is Pending. Its payment is Unpaid - Pending once the customer gives a payment reference - and its fulfilment Unfulfilled, or Not Required when nothing is delivered. It keeps:
      - the customer's details and addresses as given;
      - each product's listing, variant, item, title, options and SKU, its price as the business keeps prices and as shown, where the price came from (the item or the storefront's price list), its tax and its weight;
      - the shipping as a line of its own, with its tax;
      - how it is delivered - the method's name, price and days, or the pickup point's name, address and instructions - and how it is paid: the method's name, type and instructions, and the reference;
      - the customer's note, the terms' acceptance and the marketing consent, the customer's address and browser, and where the visit came from: the utm source, medium and campaign and the site that sent it, kept 30 days on the store;
      - its location, currency and rate, and its totals.
    - **Its contact** (19.17): a guest's e-mail is unverified, so the order is linked only to a contact an earlier guest order made for that e-mail - still active and with it, or the one it was merged into. Otherwise a new shared contact is made: a customer - an individual, or a business when a company is given, the buyer its contact person - with the e-mail, phone and addresses given, the store's language and a note naming the order. A contact of the business's own with that e-mail is never linked by it; the two are merged by hand if they are one.
    - **Marketing consent**, given, is recorded as the contact's: Promotions and Offers, by e-mail, from the website form, the order its evidence. A business without Settings → CRM's consent purposes gets the starting list.
    - **Kept for its orders**: a storefront with orders is archived, not deleted; a contact or an item on an online order is deactivated, not deleted; and a listing on one, taken out of the catalog, is archived instead.
    - Each order placed, contact made and consent recorded is in the Activity Log as the customer's doing. The business's Orders page and confirming an order (its invoice) come with Phase F; the confirmation e-mail and the order's own page with D5.
    - Settings → eCommerce gains Phone at Checkout (required, optional or not asked) and whether customers may leave a note.
    - The API, without signing in: `POST /api/v1/stores/{code}/checkout/quote` and `POST /api/v1/stores/{code}/checkout`, the cart known by `X-Cart-Token`. The settings gain `checkout_phone` and `checkout_notes`.
    - **Checked through the API** on the second PC, not in the browser, signed out. On Phase D3's dev store, with Bangladesh's Standard ($5, 2-4 days) and Freight (quoted after the order), pickup at Main Location, and Bank Transfer, bKash (reference required), Cash on Delivery and Pay at the Shop:
      - Two mugs, then the quote with no address: pickup at Main Location, Bank Transfer and bKash, $16.00. With Dhaka and Standard: Standard $5.00 and Freight (no price), Cash on Delivery offered too, $21.00.
      - The terms unticked: "Accept the terms and the privacy policy to place your order."; bKash without a reference: "Give your payment's reference (its transaction ID)."; the old total: "The total is now USD 21.00: review your order, then place it again." (409).
      - Placed by bank transfer with reference TRX-1001, marketing consent and a note, from "facebook": "Order placed.", EC-00001, Pending, its payment Pending, Unfulfilled, $21.00. Placed again: the same order (200).
      - Its contact C-00007, Karim Rahman, a customer in Dhaka, "Made by the online order EC-00001 on Rahman Shop."; consent given for Promotions and Offers by e-mail from the website form, after the business's 34 starting CRM options were added; the cart Converted.
      - A T-shirt in L, collected at Main Location and paid at the shop, for KARIM@example.test: EC-00002, Unpaid, the same contact.
      - The Activity Log: the contact, the consent and both orders, by no user.
      - The checkout page, rendered on the server with a cart: "Checkout", loading in the browser, kept from search engines. Its steps are left to the [testing checklist](#testing-checklist).
      - Found while checking: the delivery's snapshot had a `kind` of its own (the method's), which hid the order's delivery kind in what the customer sees. It is `method_kind` now.
      - The store, its shipping, pickup and payment methods, the orders, carts, contact, consent, the CRM options started for it, accounts, items, sequences, token, the app's state and the activity were then put back. The database matched its backup, but for migration 000224's changes.
  - **Phase D5 is built** (migration 000225), and with it Phase D: the order's confirmation (19.20, 19.35).
    - **The order's own page** (`/store/{code}/orders/{key}`), for its customer without signing in: its link's key - 64 characters, only its hash kept - is given at checkout and in the e-mail, and is the same however often the cart is placed. It shows when the order was placed, its three statuses in words ("Received", "Payment: not paid yet", "Delivery: not sent yet"), how to pay it, how it reaches them, and what they ordered at what total - rendered on the server and kept from search engines. It shows while the store is there, open or not: the order is the customer's. A wrong key, or another store's, finds nothing. Signed-in customers' orders come with Phase E.
    - **The confirmation** (checkout's last step) links to that page.
    - **The order-received e-mail**, sent through the queue once the order is saved: from izSuite's address under the store's name, with replies to its support e-mail. It holds the order's number and day, what was ordered and its totals - in the business's formats - how it reaches them (the address, or the pickup point's instructions and when it is ready), how to pay it (the amount and reference to use, or the reference given), the method's instructions, a button to the order's page, and the store's phone.
    - **The order's e-mail history**, kept with it: each e-mail's kind, to whom, and whether it was queued, sent, or failed after three tries - with why. One sent already is never sent again. The order's later news (confirmed, shipped and so on) come with Phases F, G and I.
    - The API, without signing in: `GET /api/v1/stores/{code}/orders/{key}`.
    - **Checked through the API** on the second PC, not in the browser, with Phase D4's dev store:
      - An order placed by bank transfer: its e-mail row Sent within two seconds, by the queue worker. The dev log held the e-mail: to karim@example.test, Reply-To "Rahman Shop <help@rahman.test>", "Your order EC-00001 at Rahman Shop", "Dear Karim Rahman", "2 × Ceramic Mug: $16.00", "Delivery: Standard" with the address, "Pay $21.00, with your order number, EC-00001, as the payment's reference.", the button to its page, and "or call +8801700000000".
      - Found while checking: the plain-text part escaped the apostrophe ("payment&#039;s"); it is printed as it is now, as other plain-text e-mails are.
      - The order's page through the API: EC-00001, Pending, Unpaid, without its key; a made-up key: "There is no such order. Check the link you were sent." (404). Rendered on the server: "Order EC-00001", "Placed on November 21, 2026", Received, "Payment: not paid yet", "Delivery: not sent yet", what was ordered, noindex.
      - The store, its order, e-mail and the rest were then put back. The database matched its backup, but for migration 000225's table.
  - **Phase D in a headless browser** (2026-11-21, after D5): Chromium driven by Playwright in Docker (`../doc-19-phase-d-ui-check.py`; see [Things that catch you out](#things-that-catch-you-out)), on the dev store - 19 steps, all passing in the end:
    - As a member with eCommerce's settings, Manage Shipping and Manage Payment Methods: Settings → eCommerce showed its four cards. Add Zone "Bangladesh"; a zone "Dhaka City" with the city and postal code 12* typed as tags ("cities Dhaka; postal codes 12*"); Standard (USD 5.00, 2-4 days); Courier by weight with two rates ("2 rates, from USD 6.00"); a method by quantity without a price refused in its dialog; pickup at Main Location, Offered; Bank Transfer and Cash on Delivery ("Shipped only"). At phone width the cards and the method's dialog fit.
    - As a visitor: the T-shirt's Add to Cart greyed out in S (out of stock); two in L added, the header counting 2; two more refused, "Only 3 left."; a mug added (3); no Add to Cart for the shelf. The cart page: + on the mug, $40.00. Checkout: details, address, Standard and Bank Transfer, $45.00; Place Order refused without the terms; then "Order EC-00001 is placed", the header's cart empty; its own page, "Payment: not paid yet", "Delivery: not sent yet". At phone width the product, cart and checkout fit, the review last.
    - **Found, and fixed**:
      - Settings → eCommerce never loaded the countries, so a zone's and a payment method's country lists were empty, and zones showed country codes. SettingsEcommerce loads them now.
      - Checkout: an answer to an address typed earlier came back after the customer chose a delivery, and undid the choice - Place Order was then refused, "Choose one of the delivery options offered for this address." An answer to choices since changed is now dropped, and Place Order waits for one still due.
      - The line above the cart's Total broke at the summary's column gap; the confirmation's "see your order" looked like plain text. Both mended.
    - The screenshots were read one by one. The dev data was then put back; the database matched its backup, but for migration 000225's table.
- **Phase E: customer accounts** - signing up, verifying, signing in and resetting the password; profile, addresses, orders, invoices and receipts, tracking, guest orders linked once verified, consent, and closing the account. Split on starting it (2026-11-21), with these defaults:
  - **E1, the account** (19.16):
    - a customer signs up on a store - name, e-mail, password (izSuite's password rules) and, if they like, a phone. The account is the business's, signing them in on each of its storefronts; it is never a user of the business, nor counted as one;
    - a 6-digit code by e-mail verifies the address (for 30 minutes; a new one after a minute). While Settings → eCommerce says so (on to start), an unverified account cannot sign in;
    - signing in and out; a forgotten password reset by an e-mailed code, which verifies the address too; the profile (name and phone); a new password; and closing the account - kept, closed, with its orders, and signed out everywhere. Signing up again with its e-mail opens it again, to be verified again;
    - the customer's sign-in is a token of its own (`X-Customer-Token`) on the store's calls: no business route reads it, and no customer route takes a business user's token.
  - **E2, the account's contact, orders and addresses** (19.16, 19.17):
    - a verified account is linked to its contact: the one its e-mail's guest orders made, else the business's one contact with that e-mail - two or more is not certain, so a new one is made - else a new customer contact. Its e-mail's guest orders become its own;
    - signed in, checkout starts from the account's details and saved addresses, and the order is the account's. With guest checkout off, checkout asks the customer to sign in;
    - the account's pages: its orders with their statuses (tracking), each order's page - with its invoice and receipts once Phase F issues them - and saved addresses, with a default for billing and one for delivery;
    - an account opened again by signing up (E1 empties its verification) shows its earlier orders and addresses only once verified again: whoever signs up with a closed account's e-mail sets its password, and with Verify Customer E-mail off is signed in at once.
  - **E3, what a signed-in customer is offered** (19.8, 19.13, 19.16): listings kept for signed-in customers, shown to them; with full Sales, their own price lists before the storefront's, with their quantity breaks, worked out by the API; their communication consent (marketing by e-mail, given or withdrawn, recorded as the contact's) and their language among the store's. The customer's credit as a payment waits for the order's invoice (Phase F).
  - **Phase E1 is built** (migration 000226): the customer's account (19.16).
    - **Signing up** on a store (`/store/{code}/account/sign-up`, from the header's Sign in beside the cart): name, e-mail, a password by izSuite's rules (8 characters at least, with capital and small letters, a number and a symbol) typed twice, and a phone if they like. Only an open store takes sign-ups. The account is the business's - one per e-mail, kept in small letters - and signs its customer in on each of the business's storefronts. It is never a user of the business, nor counted as one.
    - **Its e-mail is verified** by a 6-digit code sent to it at once, from izSuite's address under the store's name, with replies to its support e-mail. A code holds 30 minutes; a new one may be asked for a minute after the last, and spends the one before; five wrong tries spend it too. While Settings → eCommerce's Verify Customer E-mail is on (as it starts), an account signs in only once verified: signing in before sends a code (unless one went in the last minute) and leads to verifying it. With it off, signing up signs in at once, and the code still verifies the e-mail.
    - **Signing in** with the e-mail and password; five wrong tries in a minute for an e-mail from one address wait a minute. What is said never tells whether an e-mail has an account - "The e-mail or the password is not right."; a code "is on its way" if it has one - but on signing up with one in use.
    - **A forgotten password**: a code e-mailed, then a new password with it. It verifies the e-mail too, signs every other sign-in out, and signs in.
    - **The account page** (`/store/{code}/account`): the name, e-mail and "A customer since"; Sign out; the details (name and phone - the e-mail stays); a new password, given the current one, which signs the other sign-ins out; and closing the account with its password - kept, closed, with its orders (Phase E2), and signed out everywhere. Signing up again with its e-mail opens it again, to be verified again. Not signed in, the page asks to sign in first and comes back.
    - **The sign-in** is a token of the account's own, kept by a cookie of the store's and sent as `X-Customer-Token`; only its hash is kept, for 60 days from its last use. No business route reads it, and a business user's token signs no customer in. Sign-ups, new codes and forgotten passwords are held to ten a minute from one address.
    - Signing up, opening again and closing are in the Activity Log as the customer's doing; the rest is not.
    - The API, on the store's calls: `POST /api/v1/stores/{code}/account` (signing up), `.../account/verify`, `.../account/verify/resend`, `.../account/sign-in`, `.../account/password/forgot` and `.../account/password/reset`; signed in, `POST .../account/sign-out`, `GET`, `PUT` and `DELETE .../account`, and `PUT .../account/password`.
    - **Checked** on the second PC, through the API and in a headless Chromium (`../doc-19-phase-e1-ui-check.py`), on a live store made for the check, Rahman Shop:
      - Through the API: signed up as Karim@Example.test - kept in small letters, "verify your e-mail with the code we sent", no token. Signing in before verifying: "Verify your e-mail first: we have sent you a code." (403), and no second code within the minute. The dev log held the e-mail: from "Rahman Shop <hello@izsuite.com>", Reply-To "Rahman Shop <help@rahman.test>", "Your code to verify your e-mail at Rahman Shop", the code and "It holds for 30 minutes." Verified with it: signed in, a 64-character token; the account read, and its phone cleared. The same token as a business user's: 401 on `/auth/me`. Five wrong passwords, then the right one: "Too many tries: try again in 58 seconds." Ten forgotten-password calls in a minute after the sign-up: the tenth refused (429).
      - In the browser, 9 steps, all passing in the end: the header's Sign in; signing up - passwords that differ refused - then the verify page; a wrong code refused, the right one signing in to the account; the details saved; a new password refused with a wrong current one, then saved; signed out, the account page asking to sign in first, the old password refused and the new one signing in; a forgotten password reset by its code; the account closed - a wrong password refused - and no longer signing in; signing in unverified leading to the verify page, then into the account; and at phone width, the sign-up and account pages fitting.
      - **Found, and fixed**: after a wrong code its six boxes stayed filled, so the next code's first digit sent a half-changed code and the right one was dropped - the boxes now empty after a wrong code. Closing the account emptied the page before its card could lead to the store's home, so it stayed on "Loading your account…" - the card leads there itself now. The account page sat in the middle, not lined up with the header as the cart is.
      - The screenshots were read one by one. The store, the accounts, item, listing, the app's state and the activity were then put back; the database matched its backup, but for migration 000226's tables.
  - **Phase E2 is built** (migration 000227): the account's contact, orders and addresses (19.16, 19.17).
    - **A verified account is linked to its contact** - on verifying its e-mail, on a new password (which verifies it), and at each checkout signed in: its own contact while active (or the one it was merged into); else the contact its e-mail's guest orders made; else the business's one active customer (a customer or both) with that e-mail, whatever its case and spaces - two or more is not certain, and a supplier alone is not a customer, so neither is taken; else a new customer contact, an individual, with the account's name, e-mail and phone, the store's language and the note "Made by the store account of … on …". The link is in the Activity Log on the contact.
    - **Its e-mail's orders become its own** then: guest orders, and those of the account before it was closed. An unverified account's orders are only those it placed signed in.
    - **Signed in, checkout** starts from the account: its name, phone and e-mail - which cannot be changed there: the order goes to the account's e-mail - and its addresses for billing and delivery; each address step can be filled from any of its addresses. The order is the account's. Its contact is the account's once verified; an unverified account's order finds or makes its contact as a guest's does. A visitor not signed in is offered to sign in, and comes back to checkout.
    - **With guest checkout off**, checkout asks a visitor to sign in or make an account, and Place Order waits; signed in, they order. Refused to a guest: "Sign in to place your order: this store takes orders from its signed-in customers." (403).
    - **The account's pages** have tabs - Your details, Orders and Addresses:
      - **Orders** (`/store/{code}/account/orders`): the newest first, 20 a page, each with its number, day, products, total and three statuses in words (tracking), opening its own page - as its link's page shows it. Its invoice and receipts join it with Phase F. An order of another account is not found.
      - **Addresses** (`/store/{code}/account/addresses`): up to 20, added, changed and deleted - asked first - one used for billing and one for delivery. The first is both; ticking another takes it over; a default stays until another is ticked; one deleted passes its use to the newest left. Each order placed signed in keeps its billing address and the one it is shipped to, unless the account has it already (whatever its case and spaces), while there is room; the first become the defaults.
    - **Closing the account** deletes its addresses. **Signing up again** with a closed account's e-mail lets its contact and orders go: they come back only once it is verified again, so whoever reopens it - signed in at once while the business does not ask to verify - sees none of them.
    - The API, signed in (`X-Customer-Token`): `GET /api/v1/stores/{code}/account/orders` (`{ data, pagination }`), `GET .../account/orders/{id}`, and `GET`, `POST`, `PUT` and `DELETE .../account/addresses[/{id}]`. Checkout's calls take the sign-in too: its quote says `checkout.signed_in`.
    - **Checked** on the second PC, through the API and in a headless Chromium (`../doc-19-phase-e2-ui-check.py`), on the dev store with Bangladesh's Standard ($5) and Bank Transfer:
      - Through the API: a guest order EC-00001 for Karim@Example.test made the contact C-00007; Karim then signed up and verified: the account linked to C-00007, and its orders listed EC-00001 (2 products, $21.00, Received, unpaid, unfulfilled). Signed in, an order sent with someone@example.test: EC-00002 at karim@example.test, the account's, C-00007; its address kept, for billing and delivery. A second address added for delivery took that use over. Guest checkout off: the quote said `guests: false, signed_in: false`; the guest's order refused (403); the same cart signed in: EC-00003.
      - In the browser, 7 steps, all passing in the end: signed up and verified, the account's tabs, "You have no orders yet."; three addresses added - one ticked for delivery - one changed and one deleted, asked first; checkout signed in, its name, e-mail (read-only) and both addresses filled from the account, placed; the orders list with its statuses, 1 product and $13.00, and the order's own page; guest checkout off - the visitor asked to sign in, Place Order waiting, signed in and back at checkout, placed; guests again, a visitor offered to sign in, the e-mail free to type; at phone width, the orders and addresses pages fitting.
      - **Mended on the way**: the addresses' "For billing" and "For delivery" looked like the tabs, so like buttons - they are small labels now; the account's e-mail at checkout looked editable - it is greyed; the links in checkout's sign-in warning did not stand out - warnings' links are underlined. The dev API, PHP's built-in server, serves one request at a time: a save and the reload after it took more than Playwright's 5-second wait once, so the script waits 15.
      - The screenshots were read one by one. The store, its shipping and payment method, the account, orders, carts, contacts, item, listing, the app's state and the activity were then put back; the database matched its backup, but for migration 000227's changes.
  - **Phase E3 is built** (migration 000228), and with it Phase E: what a signed-in customer is offered (19.8, 19.13, 19.16).
    - **Listings kept for signed-in customers** (B1's "Signed-in customers") show to a customer signed in: in the shop, its search and suggestions, its filters, categories, collections and home, and the brands to filter by - and at their link. To anyone else they are not there: their page answers 404, Add to Cart refuses them ("This product is not sold in this store."), and a cart holding one reads it as Unavailable once signed out. The sitemap lists those for everyone alone, and their pages are kept from search engines.
    - **A customer's own price lists** (full Sales): the active lists in the store's currency that name their contact - at the store's location, within their days, lowest priority number first - come before the storefront's own list (D3), with their quantity breaks. They price the store's pages, the filters by price, the cart and checkout, and each order line keeps the list and rule it was priced by. A list for every customer is not one's own: it is the storefront's to choose. An account not yet verified has no contact, so it pays the store's prices.
    - **Who a store call is for** (`StoreVisitor`): every call of the store's takes the customer's sign-in, `X-Customer-Token` - none, or one that has ended, is a visitor's - and the frontend sends it on each, so the store's pages are rendered on the server for the customer too. What it loads is kept apart by whom it was loaded for, so signing in or out loads the store again, without a reload.
    - **The account's details** gain:
      - a language, shown when the store has two or more: one of the store's, and the contact's preferred language too. One chosen before the e-mail is verified becomes the contact's once linked. The account is the business's, so a language another of its stores offers is kept where this one does not;
      - "Send me news and offers by e-mail", once the e-mail is verified (before, the card says it waits for that): given or withdrawn as the contact's consent - Promotions and Offers, by e-mail, from the website form, its evidence "Store account on …" - and in the contact's Activity. Nothing is recorded while it stands as asked.
    - **At checkout** a customer signed in who takes marketing already is not asked again: "You get our news and offers by e-mail. Change it in your account." Consent ticked at checkout - a guest's too - is recorded only when it does not stand given already.
    - The customer's credit as a payment waits for the order's invoice (Phase F).
    - The API: `GET` and `PUT /api/v1/stores/{code}/account` gain `language`, `languages` and `marketing`; checkout's quote gains `checkout.marketing`. Every store call takes `X-Customer-Token`.
    - **Checked** on the second PC, through the API and in a headless Chromium (`../doc-19-phase-e3-ui-check.py`). Set up: eCommerce on and full Sales by hand; the live store Rahman Shop in English and Arabic; Ceramic Mug ($8, for everyone) and Gold Mug ($30, for signed-in customers); Bangladesh's Standard ($5) and Bank Transfer; and Karim Rahman, a customer contact of the business, with his own price list - the mug at $6, and from 3 at $5.50:
      - Through the API: a visitor's products, the mug alone, and the Gold Mug not found (404). A made-up customer token: a visitor's.
      - In the browser, 10 steps, all passing:
        - signed out, the shop showed the mug at $8.00, and the Gold Mug's address "There is no such product in this store.";
        - Karim signed up with his contact's e-mail and verified it: his details offered Not chosen, English and Arabic, and the marketing box unticked. Arabic and the box saved: "Your details are saved.", and kept on reloading;
        - Shop all: the Gold Mug at $30.00 and the mug at his $6.00, the price filter from 6 to 30. The Gold Mug's page was kept from search engines (noindex);
        - rendered on the server: the shop with his sign-in's cookie showed the Gold Mug and $6.00; without it, neither;
        - three mugs in the cart at his list's break, "$5.50 each", $16.50;
        - checkout: "You get our news and offers by e-mail." instead of the box; placed, EC-00001, $21.50;
        - signed out, then Shop all without a reload: the mug alone, at $8.00. Signed in again: the Gold Mug back. The marketing box unticked and saved;
        - at phone width, the account page fit.
      - In the database: the account linked to Karim's contact, both in Arabic; one consent, withdrawn, "Withdrawn by the customer from their store account on Rahman Shop."; the order's line 3 × $5.50, priced by Karim's Prices; and the link and the consent given and withdrawn in the Activity Log.
      - **Mended on the way**: checkout's "Change it in your account" looked like plain text - it is a link in the accent colour now. The whole check was run again on the final code, all 10 steps passing. After one last change to how the server reads the sign-in's cookie, Karim's shop was rendered on the server once more: with his cookie, the Gold Mug and $6.00; without it, the mug alone at $8.00.
      - The screenshots were read one by one. The dev data was then put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-e3-check.sql`), with the API, queue and scheduler stopped meanwhile; the database matched it (see [Things that catch you out](#things-that-catch-you-out) on comparing after a restore).
- **Phase F: orders in the business** - the Orders page, and an order's page with its three statuses and timeline; confirming it (its invoice), holding it for review and releasing it, cancelling it, editing it before payment, payments confirmed or failed, and payment links. Permissions View, Edit, Confirm, Place On Hold and Cancel Orders. Split on starting it (2026-11-23), with these defaults:
  - **F1, the Orders page and an order's page** (19.18, 19.19), under a new permission, eCommerce → Orders → View Orders (with View eCommerce): eCommerce's menu gains Orders, after Catalog. The list - newest first, 15 a page - is searched by number, name or e-mail and narrowed by storefront and each of the three statuses. An order's page shows its three statuses apart, its customer as given (a guest or signed in) and its contact, its addresses, how it is delivered and paid, its products with where each price came from, its totals and taxes, the customer's note, where the visit came from, the consents, its e-mails and its timeline - read from the Activity Log, as a sales document's is. An order is seen where its location may be used, as its storefront is.
  - **F2, confirming an order - its invoice** (19.20), under Confirm Orders: a Pending order is confirmed once its products can still be sold - each item active, and a Limited one with enough left, which confirming counts down. It keeps the prices it was placed at: a price changed since is pointed out, not applied. Confirming issues its invoice at once - numbered as the business's invoices are, dated that day, made by eCommerce, with its lines, shipping and taxes as the order has them - so it counts as a sale once from then. The order is Confirmed and linked to its invoice; the customer is e-mailed ("Your order is confirmed", with the invoice's link), and their account's order page shows the invoice. Cash on delivery stays Unpaid until its payment is recorded (F3). The store's status does not matter: the order is the customer's already.
  - **F3, payments confirmed or failed** (19.20, 19.22), under Confirm Orders: recording the customer's payment - its amount (the balance to start), day and reference - as a customer payment of the invoice into the account its method takes money into, numbered as receipts are. A Pending order is confirmed first (its invoice issued), as a paid order is. Its payment status follows the invoice: Partially Paid, then Paid. Or the payment is marked failed, with why: Failed, the order still owed. The customer is e-mailed either way ("Payment received" or "Payment not received"). Recorded payments are never changed here: corrections go through Sales (reversing a payment) and refunds through Phase I.
  - **F4, holding, releasing and cancelling** (19.21, 19.24), under Place Orders On Hold and Cancel Orders, each with a reason in the timeline:
    - On Hold: a Pending or Confirmed order not yet fulfilled; released, it goes back to where it was. The order's page shows the business - never the customer - what may want a look: billing and delivery countries that differ, a total above Settings → eCommerce's Review Orders Above (none to start), two failed payments or more, and three orders or more from one e-mail within a day. Settings → eCommerce may hold such orders by themselves when placed (off to start).
    - Cancelled: an order not yet shipped or collected. Its Limited quantities are given back and an invoice with no payment is voided. An invoice with payments is not refunded by cancelling (19.21): the order is refused until its payments are refunded through a credit note (Phase I) or reversed in Sales. The customer is e-mailed ("Your order is cancelled").
  - **F5, editing before payment** (19.21), under Edit Orders: while nothing is paid and nothing fulfilled - the customer's details, the addresses and delivery (shipping re-priced), products added or removed and quantities changed (a new product at its price now, the others at theirs), and a discount (an amount or a percentage, with a reason). The totals are worked out again; a confirmed order's invoice is changed with it while it has no payment. Each change is in the timeline with what it was before. Split on starting it (2026-11-24), with these defaults:
    - **F5a, the customer, addresses and delivery**: the permission Edit Orders, what may be edited and when, the totals and an issued invoice worked out again - shared with F5b - and the customer's details, the addresses, and the delivery with its shipping re-priced.
    - **F5b, products and a discount**: a product added - one the order's storefront lists now, at the price it would charge this customer now (signed in, their own price lists), with its tax now - removed, or its quantity changed, the others at the order's prices; the last product is not removed (cancel the order instead). A confirmed order's Limited quantities follow its products - counted down for more, given back for fewer - as confirming counted them; more than is left is refused as confirming refuses it. A pending order counts nothing, as before. The shipping is re-priced by the order's method for its new products, as checkout would price it; a method no longer offered for them is refused - change the delivery first - and one quoted after the order stays so. A discount on the whole order, an amount or a percentage of its products, with why (required), no more than they come to - the order's `discount_total`, its invoice's discount as Sales gives a document one; one discount at a time, taken off by setting none. The payment method is checked again for the new total, as F5a checks it for a new delivery. Each in the timeline with what it was before.
  - **F6, payment links** (19.23), under Confirm Orders: a secure link for an order not fully paid - its amount (the balance, or less when Settings → eCommerce allows paying in part; off to start), currency, expiry (7 days to start) and the payment methods it offers among the store's - e-mailed to the customer. Its page on the store shows the amount and each method's instructions, and takes the customer's reference: the payment is then Pending for the business to confirm (F3). A link is Active, Paid, Expired or Revoked; one paid is never paid again, and a link is never a sale of its own. Paying by card through a link waits for the gateways. Split on starting it (2026-11-24; F6b's defaults written down 2026-11-27):
    - **F6a, links sent and their page**: Settings → eCommerce's days (7) and paying in part (off); Send Payment Link on an order that takes a payment - neither cancelled nor on hold, and owing something - with its amount, days and methods: those paid from afar (bank transfer and mobile money) that checkout offers the order, its own first; one link at a time, a newer one replacing it; the customer e-mailed; the order's Payment Links card with Revoke; the link's page on the store, by its token - what it asks, never more than is owed, until the end of its last day where the business is, and each method's instructions with the order number as the reference; a link revoked with its order cancelled or its total changed by an edit, and Paid once the order is.
    - **F6b, the customer's payment through the link**: the link's page takes, while it is active, the way they paid - one of its methods - and their reference (required where that method asks for one), once: the order's payment Pending with that reference, unless part is paid already; the link "Reported" on the order's card, its page then saying it has their reference and the business will confirm it; the timeline, as the customer's doing. No bell notice, as for a new order (see [Small things left](#small-things-left)). **Record Payment** on an order whose link was reported starts from it: its amount (at most what is owed), their reference, and the account of the method they paid by; recorded, the link is Paid. **Payment Not Received** clears the report, and the link takes another while it works. A migration adds the report to the link (its method, reference and when). Added on building it: while a link is reported it is neither revoked nor replaced, and the order is not edited - the business records the payment or marks it not received first, so a report is never lost to a new link or total; a part-paid order's report may be marked not received too.
  - **Phase F1 is built** (migration 000229): the Orders page and an order's page (19.18, 19.19).
    - **A new permission**, eCommerce → Orders → View Orders (with View eCommerce), and **Orders in eCommerce's menu**, after Catalog.
    - **The Orders page** (`/ecommerce/orders`): the online orders of the storefronts whose location the user may use, newest first, 15 a page - each with its number (and its storefront when the business has two or more), when it was placed, the customer's name and e-mail as given, its total and how many products, and its three statuses, apart (19.19), with how it reaches the customer (shipped, picked up or nothing to deliver). Searched by number, name or e-mail, whatever their case, and narrowed by storefront (with two or more), order status, payment and fulfilment.
    - **An order's page** (`/ecommerce/orders/{id}`):
      - its three statuses, its storefront and when it was placed;
      - the customer as given - name, e-mail, phone and company - and whether they ordered as a guest or signed in; the shared contact it is for, with its number, whether the order made it, and opening it for whoever may view contacts; the billing address, and the shipping address when it is shipped;
      - its delivery (the method with its price and days, or "Price quoted after the order"; or the pickup point, when it is ready and its instructions), its payment method and the customer's reference, the location it sells from and its currency at its rate;
      - its products - each with its options and SKU, quantity, price as the store showed it and the price list it came from, its tax and amount - then the subtotal, shipping, discount, each tax ("included" when prices included it) and the total;
      - the customer's note, where the visit came from (utm source, medium and campaign, and the site that sent it), when the terms were accepted and whether marketing was asked for, and the order's e-mails with whether each went;
      - its timeline from the Activity Log, newest first, as an invoice's: so far, the order placed. What the business does to it comes with F2 to F6.
    - An order at a location the user may not use is "There is no such order." (404), its timeline too. Its internal fields - its link's key, the customer's IP address and browser - are not sent.
    - **The customer's doing** in a timeline - an order placed, a contact made, consent recorded through `recordCustomer()` - is now by "The customer", not izSuite. Entries written before say izSuite still.
    - **Found and mended on the way**: every record's timeline (invoices, purchases, expenses, leads, opportunities, and now orders) took the About page's `.timeline` style - its list in the middle of the card, with a line down its centre. RecordTimeline has a class of its own now.
    - **Checked** on the second PC, through the API and in a headless Chromium (`../doc-19-phase-f1-ui-check.py`). Set up through the API on a dump of the dev data: eCommerce on (the business on Premium for it), the live store Rahman Shop, Ceramic Mug ($8) and Recipe E-Book ($4) in the catalog, Bangladesh's Standard ($5, 2-4 days) and Bank Transfer into City Bank; then, as visitors, EC-00001 - two mugs for Karim Rahman, shipped, reference TRX-1001, a note, marketing, from facebook's winter campaign - and EC-00002, the e-book for Nadia Islam, unpaid. Three throwaway members: View Orders and contacts at every location; View Orders at another location only; View eCommerce alone.
      - Through the API: the list searched for "karim", EC-00001 alone; the order with its contact C-00007 (made by it), Standard at $5.00 and 2-4 days, Bank Transfer and TRX-1001, the note, the campaign, the terms and marketing, the mug's line and the totals, and its e-mail Sent; its timeline the placing.
      - In the browser, 7 steps, all passing in the end: the Orders page with both, newest first, and their statuses ("Pending", "Unpaid", "Not Required", "Nothing to deliver", "Unfulfilled", "Shipped"); Payment: Unpaid narrowing to EC-00002, "karim" to EC-00001, "nobody" to "No orders match these filters."; EC-00001's page - customer, contact link to `/contacts/{id}`, addresses, delivery, payment, products, $21.00, note, campaign, e-mail and timeline; EC-00002's - Unpaid, Not Required, "Nothing to deliver", "None given", no shipping address; at phone width both pages without sideways scrolling; the member at another location, "No online orders yet." and "There is no such order."; the member without View Orders, no Orders in the sidebar.
      - **Mended on the way**: the three status filters each read "Any", not saying which was which - now "Any order status", "Any payment", "Any fulfilment", one under another on a phone; an order number broke in two on a phone; "Bank Transfer" was followed by its type "Bank transfer" - the type shows only when the name does not say it; and the timelines' layout above.
      - The screenshots were read one by one. The dev data was then put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f1-check.sql`); the database matched it, and matched the backup from before the phase but for migration 000229's menu row.
  - **Phase F2 is built** (migration 000230): confirming an order, its invoice issued (19.20).
    - **A new permission**, eCommerce → Orders → Confirm Orders (with View Orders). Roles made from the Manager template have it; View Only's do not.
    - **Confirm Order** on a pending order's page opens a dialog that asks the API what confirming would do, today: the invoice it issues - its day, contact and total - the e-mail to the customer, and what it counts down ("Ceramic Mug: 10 left, then 8"). What stands in the way is listed instead, and Confirm Order waits:
      - an item of the order inactive in Items;
      - a Limited listing - or variant - with less left than the order holds of it (its lines of it together): "Only 2 of Ceramic Mug left, and the order has 3: change what is left in the Catalog, then confirm the order.";
      - its contact inactive, or no longer a customer;
      - an order not pending: "Order EC-00001 is confirmed already."
    - **Pointed out, never applied**: a price the store sells at now that is not the order's - for its customer as they were priced, signed in with their own price lists - as the store shows prices ("Ceramic Mug sells at $9.00 now: the order keeps $8.00."), and a contact merged since ("… was merged into … since: the invoice goes to …"). Out of Stock set by hand stops nothing - the business may have set it because of this very order - nor does the store's status: the order is the customer's already.
    - **Confirming**, all in one transaction with the order and the stock locked, so an order is confirmed once:
      - its checks again; the first problem refuses it (409);
      - **its invoice issued**: numbered as the business's invoices are (at the order's location), dated that day and due then, for its contact - or the one it was merged into - in its currency at its rate, made by eCommerce; its lines the order's products (a variant's options as the line's description) and its "Shipping: …" line, each at the order's price with the order's tax - a rate changed since is not applied - totalled as checkout totalled them; Your Reference the order's number; the customer's addresses as the order gives them; Settings → Sales's notes and terms, as a new invoice has them. From then it counts as a sale, once;
      - each Limited listing or variant counted down - never a stock movement;
      - the order Confirmed, linked to its invoice, with when and by whom. Its payment status stays as it was: Pending with a reference, Unpaid for cash on delivery, until its payment is recorded (F3);
      - the invoice's secure link made, and the customer e-mailed once all is saved: "Your order EC-00001 at Rahman Shop is confirmed", its invoice's number, what was ordered, and See your invoice (the `/d/` link). It is kept with the order's e-mails;
      - the Activity Log: "Confirmed online order EC-00001 and issued invoice INV-00001" on the order's timeline, and "Issued invoice INV-00001 for online order EC-00001" on the invoice's.
    - **The order's page** says "Confirmed … by …" and links its invoice for whoever may view invoices. **The customer's** order page - by its own link, and on their account - shows "Your invoice, INV-00001: see or download it" while the invoice's link works (revoked, or expired with full Sales' link days, it is not shown).
    - Left as they are, as 19.20 lets them be: the order's shipping method and taxes are not checked again (the order keeps them as placed), and the customer's credit limit (full Sales) is not checked for an online order.
    - **Found and mended on the way**: a secure link's address was made from the request's own host, so the customer's order page - rendered on the server, which asks the API from inside - linked the invoice at `http://api:8000/d/…`, which no customer can open. `DocumentLinks::url()` gives the API's own address (`APP_URL`) now, as public files do, whoever asks; a test asks as the server does.
    - **Checked** on the second PC (2026-11-24), through the API and in a headless Chromium (`../doc-19-phase-f2-setup.py`, then `../doc-19-phase-f2-ui-check.py`). Set up through the API on a dump of the dev data: eCommerce on (the business on Premium for it), City Bank and Cash Box, Ceramic Mug ($8, Limited: 10 left) and Recipe E-Book ($4) on the live store Rahman Shop, Bangladesh's Standard ($5, 2-4 days), Bank Transfer and Cash on Delivery; as visitors, EC-00001 (two mugs for Karim Rahman, bank transfer with TRX-1001), EC-00002 (the e-book for Nadia Islam) and EC-00003 (three mugs for Rafi Ahmed, cash on delivery); then the mug at $9. Two throwaway members: Confirm Orders with View Invoices, and View Orders alone.
      - In the browser, 8 steps, all passing on the final code: the member with View Orders alone sees no Confirm Order; EC-00001's dialog - "Confirming it issues its invoice, dated 24 Nov 2026, to Karim Rahman (C-00007) for $21.00, and e-mails it to karim@example.test.", "Ceramic Mug: 10 left, then 8" and "Ceramic Mug sells at $9.00 now: the order keeps $8.00."; confirmed - "Order EC-00001 confirmed. Invoice INV-00001 issued.", Confirmed, Payment: Pending, "Confirmed … by Check Confirmer · Invoice INV-00001", the timeline's entry and "Order confirmed to karim@example.test"; INV-00001 in Sales - Issued, Unpaid, billed to the order's address, Customer Ref. EC-00001, the mug and "Shipping: Standard", $21.00, Made In eCommerce; EC-00003 with 2 left - the dialog's "Only 2 of Ceramic Mug left, and the order has 3: …" and Confirm Order disabled; with 5 left, confirmed, Payment: Unpaid; the customer's order page by its link - "Your invoice, INV-00001: see or download it", opening the invoice at `http://localhost:8010/d/…`; at phone width a confirmed order and the dialog without sideways scrolling.
      - In the database afterwards: both orders confirmed and linked to INV-00001 and INV-00002, the mug's 2 left, and both "… is confirmed" e-mails sent by the queue worker into `laravel.log` with the invoice's link at `APP_URL`.
      - The first run failed at the dialog: the members' sidebars were read from Redis, cached under F1's check's user ids and `nav_version` (see [Things that catch you out](#things-that-catch-you-out): the cache is in Redis database 1). After the mend above, the dev data was put back from the dump and the whole check run again.
      - The only console message: the public invoice page's Content-Security-Policy refuses the inline script `laravel/boost` (a development package) puts into HTML pages - not the app's, and not there in production.
      - The screenshots were read one by one. The dev data was then put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f2-check.sql`); the database matched it, and matched the backup from before the phase (`../izsuite-dev-before-doc-19-phase-f2.sql`) but for migration 000230's columns.
  - **Phase F3 is built** (migration 000231): an order's payment received or failed (19.20, 19.22), under Confirm Orders - no new permission.
    - **Record Payment** on an order that owes something - neither cancelled nor on hold - opens a dialog that asks the API what recording it would do: the account its payment method takes money into (the one the order was placed with - "Into City Bank, paid by Bank Transfer") and what the order owes, the amount to start; the day (today, never after, nor before its invoice); and the customer's reference to start. A pending order is **confirmed first**, in the same transaction: the dialog shows what Confirm Order's does (its invoice, the count-down, prices changed since), and what stands in the way of confirming stops the payment too.
    - Recording it records **a customer payment of the order's invoice, as Sales records one**: numbered as receipts are (RCT-00001), into that account and its history, by bank transfer, mobile money or cash (cash on delivery and paying on pickup), with its reference. No more than the order owes: "Order EC-00002 owes USD 2.00: record that much at most." The customer is e-mailed "Payment received for your order … at …" - the amount and reference, what is left to pay, and the invoice's link - and the timeline says "Recorded payment RCT-00001 of USD 21.00 for online order EC-00001". An account no longer active, taking customer payments or usable at the user's locations refuses it: record it in Sales instead.
    - **Payment Not Received** while nothing is paid (Unpaid, Pending or Failed already): why, required - "Say why the payment failed: it stays in the order's timeline, not in the e-mail to the customer." The payment is **Failed**, the order still owed and its status as it was; its failures are counted (Phase F4's review), with the last reason. The customer is e-mailed "Your payment for order … was not received" with what is owed and how to pay - never why - and the invoice's link once there is one.
    - **The order's payment status follows its invoice**, however the invoice is settled - on the order's page, in Sales, or reversed in Sales: Partially Paid, Paid (Refunded with Phase I); owing all of it again, Unpaid; while nothing was paid, Pending or Failed stays. A recorded payment is never changed here: it is reversed in Sales.
    - **The order's page**: Record Payment and Payment Not Received beside Confirm Order, and a **Payments** card - what the invoice still owes, each payment (its receipt opening in Sales for whoever may view payments, its day, account and reference; a reversed one struck through), and "Marked failed once, the last time: …".
    - **Found and mended on the way** (from Phase F1): `utils/ecommerceOrders.ts` exported a map called `ECOMMERCE_PAYMENT_TYPES`, the name of Phase D2's list of payment types in `useEcommercePaymentMethods.ts`. Nuxt auto-imports one of the two - F1's map - so Settings → eCommerce's Payment Methods called `.find()` on it and its Add Method had no types. The map is `ECOMMERCE_PAYMENT_TYPE_NAMES` now; the list and the dialog were checked again (below).
    - **Checked** on the second PC (2026-11-24), through the API and in a headless Chromium (`../doc-19-phase-f2-setup.py`, then `../doc-19-phase-f3-ui-check.py`): F2's set-up - Rahman Shop, the Ceramic Mug Limited to 10 and at $9 since the orders, EC-00001 (two mugs, bank transfer with TRX-1001), EC-00002 (the e-book) and EC-00003 (three mugs, cash on delivery) - with the confirming member also viewing payments.
      - In the browser, 8 steps, all passing, run again on the final code: the member with View Orders alone sees none of the three actions; EC-00001's Record Payment - "Recording it confirms the order first, which issues its invoice, … for $21.00, …", "Ceramic Mug: 10 left, then 8", the $9.00 price pointed out, "Into City Bank, paid by Bank Transfer. The order owes $21.00.", 21.00 and TRX-1001 filled in; recorded - Confirmed, Payment: Paid, no actions left, the Payments card ("nothing left to pay", RCT-00001 linking to Sales), both e-mails and the timeline's entry; EC-00002's Payment Not Received - refused without why, then Failed, "Marked failed once, the last time: No transfer arrived for EC-00002.", its e-mail and entry; $2.00 of it recorded - confirmed first, Partially Paid, "$2.00 still to pay", $5.00 more refused; EC-00003 confirmed, then paid into Cash Box; its payment reversed in Sales through the API - the order Unpaid again, the payment struck through and Reversed; at phone width the order and the dialog without sideways scrolling.
      - Settings → eCommerce, as a throwaway member who may manage payment methods: the list read "Bank transfer" and "Cash on delivery" under the methods' names, and Add Method's Type offered all four types; an order's page still showed its payment method.
      - In the database afterwards: three receipts (City Bank's two, Cash Box's reversed), each order's payment status its invoice's, and every e-mail sent by the queue worker - "Payment received …" with its amount and reference, and "… was not received" without the reason and, the order not yet confirmed, without a link.
      - The only console messages were the two refusals the check asked for (422).
      - The screenshots were read one by one; the Payments card's spacing was mended after them, and the card shot again. The dev data was then put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f3-check.sql`); the database matched it, and matched the backup from before the phase (`../izsuite-dev-before-doc-19-phase-f3.sql`) but for migration 000231's columns.
  - **Phase F4 is built** (migration 000232): holding, releasing and cancelling an order (19.21, 19.24), each with why - in its timeline and on its page, never shown to the customer.
    - **Two new permissions**, eCommerce → Orders → Place Orders On Hold and Cancel Orders (each with View Orders). Roles made from the Manager template have them; View Only's do not.
    - **Put On Hold** (in the order's More menu): a pending or confirmed order not yet being fulfilled is On Hold - not confirmed, paid or fulfilled meanwhile ("Order EC-00001 is on hold: release it first."), keeping the status it goes back to. **Release** - the main action while it is held - takes it back there ("It is Pending again."). Its page says "On hold - released, it is Pending again. Why: …". The customer sees their order On hold.
    - **Cancel Order** (in the More menu, in red): a pending, confirmed or held order not yet shipped or collected ("… is on its way or with its customer: it is returned, not cancelled."). An order whose invoice anything settles is refused - "Payments settle part of order EC-00002's invoice, INV-00002: refund them through a credit note, or reverse them in Sales, then cancel the order." - so cancelling never refunds silently (19.21). Otherwise, confirmed once: its Limited quantities given back, to listings and variants still Limited, and its invoice voided as Sales voids one ("Voided invoice INV-00001", its links revoked). The order is Cancelled; the customer is e-mailed "Your order … at … is cancelled" - what it was, that it will not be sent, and, while they said they had paid (Pending, Failed), that the business will be in touch about their money; never why. The order's Payments card says its invoice is void.
    - **What may want a look**, on the order's page alone - "Worth a look - for you, never the customer" - while it is not cancelled: billed in one country and delivered to another; a total, in the base currency, above Settings → eCommerce's **Review Orders Above** (none to start); its payment marked failed twice or more; its e-mail's third order or more within a day, up to it. Each assists a review; none accuses.
    - **Settings → eCommerce**: Review Orders Above, and "Put such orders on hold as they are placed" (off to start) - then an order with something wanting a look is held by izSuite as it is placed, its reason "Held for review as it was placed: …", for the business to release or cancel.
    - **Found and mended on the way** (from Phase F3): the customer's order page still told them how to pay - "We will match your payment…" - once it was paid; and a cancelled order showed its payment's and delivery's words. How to pay shows now only while nothing is paid and the order is not cancelled, and a cancelled order's statuses read "Cancelled" alone.
    - **Checked** on the second PC (2026-11-24), through the API and in a headless Chromium (`../doc-19-phase-f4-setup.py`, then `../doc-19-phase-f4-ui-check.py`): F2's set-up, then Review Orders Above at $20 with holding on, and EC-00004 (two mugs at $9, $23.00) placed after it. Three throwaway members: one who may do all to an order, one who may only view and confirm, one who manages Settings → eCommerce.
      - In the browser, 8 steps, all passing, run again on the final code: the confirming member sees no Put On Hold, Release or Cancel, and nothing on the held EC-00004; EC-00004 held as placed - On Hold, "Held for review as it was placed: Its total, USD 23.00, is above Review Orders Above (USD 20.00).", Worth a look, Release and More only, and the timeline's entry; EC-00001 put on hold - refused without why, then held, its notice and entry - and released, Pending with Confirm Order again; EC-00003 confirmed, then cancelled - Cancelled with why, no actions, "Cancelled: its invoice, INV-00001, is void.", Order cancelled among its e-mails, the timeline's entry; EC-00002 part-paid, its cancelling refused in the dialog with Sales' way out; the customer's pages - EC-00004 On hold without why, still with how to pay; EC-00003 Cancelled alone, without how to pay; Settings → eCommerce showing $20.00 and holding on, saved at 25; at phone width a held order and its open More menu.
      - In the database afterwards: the mug back at 10 after EC-00003's cancelling, INV-00001 void with no working link, EC-00002's invoice issued, the cancellation e-mail sent by the queue worker, and the settings at 25.00 with holding on.
      - The only console messages were the two refusals the check asked for (422 without why, 409 with a payment).
      - The screenshots were read one by one; the notices' spacing and Worth a look on a cancelled order, and the customer's words for one, were mended after them, and the whole check run again. The dev data was then put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f4-check.sql`); the database matched it, and matched the backup from before the phase (`../izsuite-dev-before-doc-19-phase-f4.sql`) but for migration 000232's columns.
  - **Phase F5a is built** (no migration): an order's customer, addresses and delivery edited before payment (19.21).
    - **A new permission**, eCommerce → Orders → Edit Orders (with View Orders). Roles made from the Manager template have it; View Only's do not. Confirm Orders does not need it, though Sales' Confirm needs its Edit: 19.42 lists the two apart.
    - **When**: an order pending, confirmed or on hold, nothing of it paid - Unpaid, Pending or Failed, and its invoice, once issued, settled by nothing: no payment, credit or withholding - and nothing of it fulfilled. Otherwise "Order EC-00001 can no longer be edited: part of it is paid." (or "it is cancelled", "it is being fulfilled"), and the order's More menu does not offer the edits.
    - **Edit Customer**: the name, e-mail, phone and company the customer gave - the order's own copy. Its contact is not changed: that is for Contacts. The order's e-mails go to the new address from then.
    - **Edit Addresses and Delivery**: the billing address; shipped - to the billing address, or one of its own - or picked up; and the method, or the pickup point. As the address changes, the dialog asks what it would be offered: each method checkout offers there for the order's own products, at its price now as the order shows prices ("Standard - $5.00 · 2-4 days", or "price quoted after the order"), and the pickup points ("Main Location · Ready in 2 hours"). A country the store does not sell to says so, and is refused: "Rahman Shop does not deliver to India."; so is a method not offered there. An order with nothing to deliver has its billing address alone.
      - **The shipping re-priced**: the order's shipping line is the new method's at its price now, from checkout's own pricing at the order's currency and rate, or none - picked up, or quoted after the order. The totals and taxes are worked out again at the order's own prices, mode and rounding.
      - **Its payment method**: a changed delivery or country must still be offered it, as checkout offers methods: "Cash on Delivery is not offered for this delivery or country: keep them, or cancel the order for the customer to place it again."
    - **A confirmed order's invoice changes with it**: its lines, totals and what it owes, and the customer's addresses on it. Its number, day and link stay; the customer is not e-mailed.
    - **The timeline**: one entry an edit - "Changed the customer details of online order EC-00001", "Changed the addresses and delivery of online order EC-00001" - with what each thing was and is under it: "4 changes" opens on "Billing address: … → …", "Delivery: Standard, USD 5.00 → Express, USD 12.00" and "Total: USD 21.00 → USD 28.00".
    - **Found and mended on the way** (from Phases F3 and F4): an Activity Log entry's description is at most 255 characters in MySQL, while the tests' SQLite takes any length. A reason up to its 500 characters - for a payment not received, a hold or a cancelling - put into the timeline's words failed with a server error. `ActivityLogService` cuts a description over 255 characters with "…" now, keeping it whole in the entry's properties; the edits list their changes apart.
    - **Checked** on the second PC (2026-11-24), through the API's tests and in a headless Chromium (`../doc-19-phase-f5a-setup.py`, then `../doc-19-phase-f5a-ui-check.py`): F2's set-up - Rahman Shop, EC-00001 (two mugs at $8, Standard at $5: $21.00, by bank transfer), EC-00002 (the e-book, nothing to deliver) and EC-00003 (three mugs, cash on delivery) - then Express ($12, 1-2 days) beside Standard, the business's location a pickup point, and EC-00001 confirmed with its invoice. Two throwaway members: one who may do all to an order, one who may only view and confirm.
      - In the browser, 7 steps, each passing on the final code, though not all in one run: the member who may only confirm sees Confirm Order and no More menu; Edit Customer - the phone and company saved, "Changed the customer details of …" and its "2 changes" ("Phone: +8801700000001 → +8801711111111", "Company: Not set → Rahman Traders"); Edit Addresses and Delivery - Standard at $5.00 and Express at $12.00 offered, the billing address changed and Express picked: "Shipped by Express", "Against invoice INV-00001: $28.00 still to pay." and "4 changes" with both addresses, "Delivery: Standard, USD 5.00 → Express, USD 12.00" and "Total: USD 21.00 → USD 28.00"; INV-00001 in Sales - the same number and day, "Shipping: Express" at $12.00, $28.00 due, billed to the new address; EC-00003's refusals - picked up at "Main Location · Ready in 2 hours": "Cash on Delivery is not offered for this delivery or country: …"; India: "Rahman Shop does not deliver to this country." in the dialog, then "Rahman Shop does not deliver to India." on saving, the order still shipped by Standard; EC-00002's billing address alone, no delivery offered; at phone width the delivery dialog without sideways scrolling.
      - The last two runs: one beside the full suite, 6 of 7 - Edit Customer's save outlasted the 15-second wait with the PC busy; then one alone, stopped by Claude Code after 4 steps, all passing - Edit Customer among them - when the PC ran low on memory. Earlier runs found the server error above (the delivery's first entry put all four changes into its words) and mistakes of the script's own: a method the set-up does not make, "India" matching British Indian Ocean Territory, and the changes read before their list was opened.
      - The run alone's screenshots - the two dialogs, the edited order with its timeline, the invoice - were read one by one. The dev data was then put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f5a-check.sql`); the database matched it, AUTO_INCREMENT included.
  - **Phase F5b is built** (migration 000233): an order's products and discount edited before payment (19.21), under Edit Orders, while F5a's rule lets it be edited.
    - **Edit Products** (in the order's More menu): each product's quantity, or taken off with Remove - never the last ("Keep at least one product: to take them all off, cancel the order.") - and **Add a Product**: the store's products this customer could buy now, searched as it is typed - signed in, as themselves (Phase E3: their own lists, what signed-in customers alone see) - each, or each variant, with its price as the order shows prices and what is left when Limited. A product picked twice is one more of it. Saved:
      - **one added** sells at its price now for this customer, with its tax now; the others keep the order's price - a quantity changed too. A listing the store does not sell this customer now, or one on the order already, is refused under it; so is one out of stock, or without a price.
      - **more of a product** needs its item active and it not out of stock.
      - **Limited stock**: an order confirmed - counted down then - is counted down for more and given back for fewer, never past what is left ("Only 0 of Ceramic Mug left, and the order would take 1 more: …"). A pending order counts nothing, as before, but is never given more than is left ("… and the order would have 4: …").
      - **its shipping re-priced** by the order's own method for its products now, as checkout would price it; a method that would not take them refuses them ("Standard would not take order EC-00002's products: change its delivery first."); one quoted after the order stays so. An order delivering nothing takes no product to deliver ("Tea Towel is delivered, and order EC-00002 has nothing to deliver: the customer can order it apart."); one delivering keeps one.
    - **Give a Discount** (Change Discount once it has one): a percentage of its products, an amount off them - no more than they come to: "The order's products come to USD 4.00: give a discount of that much at most." - or none; with why, required, for its timeline alone. A percentage follows the products as they change. The discount is the amount it takes off, shared over the order's lines as Sales shares an invoice's, and its invoice takes the same amount off - so the two total alike. An amount is taken as the business keeps prices - before tax, or with tax in - and the dialog says so while the order has taxes.
    - **As it shows**: each line, the products and the shipping as before any discount, so a discount never changes what a line shows; the discount itself as the store shows prices - with its tax in when the store shows prices with tax and the business keeps them without - so the order adds up. The order's page says "Discount (10%)" and "Why: …"; the customer's order page, and the order's e-mails from then, say Discount, never why.
    - **Its payment method** still takes the new total, as checkout offers methods by their least and most: "Cash on Delivery takes orders of up to USD 30.00, and the order would come to USD 37.00: keep it as it was, or cancel the order for the customer to place it again." F5a's addresses and delivery check it too, now, when their shipping changes the total.
    - **The timeline**: "Changed the products of online order EC-00001" - each product's "2 at USD 8.00 → 3 at USD 8.00", "none" for one added or taken off, then the shipping, the discount and the total; "Gave online order EC-00001 a discount of 10%: A loyal customer", "Changed the discount of … to USD 3.00: …", "Took the discount off …: …".
    - The customer is not e-mailed about an edit, as in F5a: the business tells them, and their order page shows the order as it is.
    - **Checked** on the second PC (2026-11-24). The full suite ran in two halves in the foreground, the first stopped by Claude Code for want of memory: 1264 tests, 1263 passing, `SalesSettingsApiTest` failing as before. In a headless Chromium (`../doc-19-phase-f5b-setup.py`, then `../doc-19-phase-f5b-ui-check.py`): F2's set-up - Rahman Shop, the Ceramic Mug Limited to 10 and at $9 since, EC-00001 (two mugs at $8, Standard at $5: $21.00, by bank transfer), EC-00002 (the e-book) and EC-00003 (three mugs, cash on delivery) - then a Tea Towel ($3) listed and EC-00001 confirmed with INV-00001; F5a's throwaway members (`f5-setup.php`).
      - In the browser, 7 steps, 6 passing in the run: the member who may only confirm has no More menu; Edit Products on EC-00001 - three mugs, the towel found by "towel" at $3.00 and added twice: "The products of order EC-00001 are saved.", the towels on the order, $35.00 owed on INV-00001, and "3 changes" ("Ceramic Mug: 2 at USD 8.00 → 3 at USD 8.00", "Tea Towel: none → 2 at USD 3.00", "Total: USD 21.00 → USD 35.00"); INV-00001 in Sales with the towels, "Shipping: Standard", Discount −$3.00 and $32.00; the customer's order page - 2 × Tea Towel, Discount −$3.00, Total $32.00, never why; EC-00002's dialog - Remove off for its one product, the towel's button "Needs Delivery" and off - and $5 off its $4 refused under the amount; at phone width the products dialog without sideways scrolling.
      - Give a Discount's save outlasted the 15-second wait with the PC short of memory - its message read was still the products' - and the invoice and customer steps after it showed it saved. Looked at again on the order's page: "Discount (10%)", "Why: A loyal customer", −$3.00, $32.00, "Gave online order EC-00001 a discount of 10%: A loyal customer" with its 2 changes, and Change Discount in the More menu. The script now waits up to 90 seconds for a dialog to close before reading its message.
      - In the database afterwards: the mug Limited at 7 (two counted down as EC-00001 was confirmed, one more for its third), EC-00001 10% off at $3.00 and $32.00, INV-00001's discount an amount of 3.00 to the same $32.00, the other orders as they were, and no e-mail for the edits. The only console message was the refusal the check asked for (422).
      - The screenshots were read one by one - the two dialogs, the order with its More menu, totals and timeline, the invoice, the customer's page, the refusal, the phone. The dev data was then put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f5b-check.sql`); the database matched it, AUTO_INCREMENT included, and matched the backup from before the phase (`../izsuite-dev-before-doc-19-phase-f5b.sql`) but for migration 000233's columns.
  - **Phase F6a is built** (migration 000234): payment links sent for an order not fully paid, and their page on the store (19.23), under Confirm Orders - no new permission. Begun on 2026-11-24 in admin `7d6d2f8` ("workign on doc 19 f5c": the table, model, services, controllers and resources, without routes, e-mail, tests or frontend), finished on 2026-11-27.
    - **Send Payment Link**, in the order's More menu, while a payment could be recorded - neither cancelled nor on hold, and owing something. Its dialog asks the API what a link would ask:
      - what the order owes, in its currency: all of it, not to be changed - unless Settings → eCommerce lets a link ask less, then up to it ("Order EC-00003 owes USD 29.00: ask that much at most.");
      - the days it works - Settings → eCommerce's, 7 to start - and "To the end of" the day that makes, where the business is;
      - the store's methods paid from afar, bank transfer and mobile money, as checkout offers them for the order's currency, country, total and delivery - never cash on delivery or paying on pickup - its own first, all ticked. With none, the dialog says to add one in Settings → eCommerce → Payment Methods, and Send Link waits.
    - **Sent**: the link's token, 64 random characters, is kept only as its hash; its amount, currency, methods (by the names they had), last moment, to whom and by whom are kept. A link still active for the order is replaced - one at a time. The customer is e-mailed "Pay for your order EC-00001 at Rahman Shop": what it asks and until when, the order, "Payment: Bank Transfer or bKash", "Pay $21.00 by 04 Dec 2026, with your order number, EC-00001, as the payment's reference: the link below shows how.", and a Pay for your order button - each method's instructions are on the link's page. The timeline: "Sent a payment link of USD 21.00 for online order EC-00001 to …".
    - **The Payment Links card** on the order's page, for whoever may view it: each link, the newest first - its amount, state (Active, Expired, Paid or Revoked, and why: by hand, a newer link replaced it, the order was cancelled, or its total changed), its methods, when, by whom and to whom it was sent, and the day it works until. **Revoke** on an active one, for whoever may confirm orders, asked first; the customer is not e-mailed. In the timeline: "Revoked the payment link of USD 21.00 for online order EC-00001".
    - **The link's page** (`/store/{code}/pay/{token}`), without signing in, rendered on the server and kept from search engines: "Pay for Order EC-00001", "Pay $21.00 by 4 December 2026", "Use your order number, EC-00001, as the payment's reference. We match your payment and e-mail you once it is confirmed.", each method it offers that the store still takes with its instructions, the store's e-mail and phone, and what the order holds with its total. It never asks more than the order still owes, paid in part since. Expired, revoked or paid, it says so instead: "This link has expired", "This link is no longer in use" ("We may have sent you a newer one."), "Order EC-00002 is paid". An unknown token: "There is no such payment link. Check the link you were sent." (404). It shows while the store is there, open or not, as the order's own page does.
    - **A link stops** when it expires at the end of its last day, is revoked or replaced, when its order is cancelled, or when an edit (F5a or F5b) changes the order's total - it asked what the order came to. Paid in full, however the money came (on the order's page or in Sales), its links still active are Paid, never paid again. No link is sent for a held, cancelled or paid order; holding one leaves its link working. A link is never a sale of its own: the order's invoice, once confirmed, is.
    - **Settings → eCommerce**: Payment Links Work For (days), 1 to 90 (7), and "A link may ask less than the order owes, for the customer to pay in part" (off).
    - Until F6b took the customer's report, a payment through a link was recorded as any other: Record Payment put it into the account of the order's own payment method. F6b's report names the way they paid, and Record Payment uses its account.
    - The API: `GET /api/v1/ecommerce/orders/{id}/payment-link` (what a link would ask), `POST .../payment-links` (idempotent) and `POST .../payment-links/{link}/revoke`, under Confirm Orders; `GET /api/v1/stores/{code}/pay/{token}`. The order's page gives `payment_links`; Settings → eCommerce gives `payment_link_days` and `partial_payments`.
    - **Checked** on the second PC (2026-11-27). The full suite ran in two halves in the foreground: 1281 tests, 1280 passing, `SalesSettingsApiTest` failing as before. The API's own tests (`EcommercePaymentLinksTest`, 7) set the business in Dhaka: a 7-day link sent at 16:00 there works to 17:59:59 UTC on its seventh day. In a headless Chromium (`../f6a-setup.php` - the business on Premium, two throwaway members and their tokens - then `../doc-19-phase-f6a-setup.py` and `../doc-19-phase-f6a-ui-check.py`): Rahman Shop with Bank Transfer, bKash and Cash on Delivery; EC-00001 (two mugs, $21.00, bank transfer), EC-00002 (the e-book, $4.00) and EC-00003 (three mugs, $29.00, cash on delivery). 9 steps, all passing on the final code:
      - Send Payment Link on EC-00001: 21.00 not to be changed ("All it owes: paying in part is off in Settings → eCommerce."), 7 days "To the end of 04 Dec 2026", Bank Transfer and bKash ticked, no Cash on Delivery; sent - "A payment link for order EC-00001 is on its way to karim@example.test.", the card's $21.00, Active, "Bank Transfer or bKash", and the timeline's entry;
      - its e-mail in the dev log, sent by the queue worker - what it asks, "Payment: Bank Transfer or bKash", how to pay and the link - and "Payment link to karim@example.test: sent …" on the order's page;
      - the member who may only view orders: the card, without Revoke, and no More menu;
      - the link's page: "Pay $21.00 by 4 December 2026", both methods with their instructions, the store's e-mail and phone, 2 × Ceramic Mug and $21.00 - rendered on the server, noindex;
      - Settings → eCommerce: 3 days and paying in part, saved;
      - EC-00003: 29.00 to change, and 3 days; $40 refused; $10 by bKash alone sent, then a newer link of $29.00 - the first "revoked: a newer link replaced it", its page "This link is no longer in use";
      - EC-00001's link revoked, asked first: "The payment link for order EC-00001 is revoked.", "revoked: by hand", the timeline's entry, and its page no longer offering to pay;
      - EC-00002: a link sent, then its $4.00 recorded - the link Paid, "the order is paid", no Send Payment Link in the More menu, its page "Order EC-00002 is paid";
      - at phone width the dialog, the card and the link's page without sideways scrolling.
    - In the database afterwards: four links - EC-00001's revoked by hand, EC-00003's $10 replaced and $29 active, EC-00002's paid - each token a 64-character hash, and every e-mail sent by the queue worker. The only console message was the refusal the check asked for (422).
    - The first run's first page outlasted its five minutes, the frontend still building after its restart, and the steps that needed its link failed; the second found two mistakes of the script's own (the e-mail's "sent" in small letters, and the store's e-mail in its footer too). The dev data was put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f6a-check.sql`) after each run. The screenshots were read one by one. The database matched that dump, AUTO_INCREMENT included, and the backup from before the phase (`../izsuite-dev-before-doc-19-phase-f6a.sql`) but for migration 000234's table and columns.
  - **Phase F6b is built** (migration 000235), and with it Phase F: a payment link's customer saying they paid, and the business settling it (19.23), under Confirm Orders - no new permission.
    - **On the link's page**, while it is active, its methods are choices - each with its instructions - with "Your Payment's Reference" where the chosen one asks for it (required, optional, or not at all), and **I Have Paid**. A way the link does not offer, or one the store no longer takes: "Choose one of the ways to pay this link offers."; a required reference left out: "Give your payment's reference (its transaction ID)." Taken, the page says "Thank you: we have your payment, and will e-mail you once it is confirmed." and then "We have your payment - You told us you paid $4.00 by bKash, reference BK-555. We will confirm it and e-mail you." Once only: "We have your payment already: …" (409). An expired, revoked or paid link refuses it: "This link has expired: ask us for a new one.", "This link is no longer in use: …", "Order EC-00002 is paid: there is nothing more to pay through this link."
    - **The report** is kept on the link - when, the way they paid as it was then ({id, name, type, payment_account_id}) and their reference - and the link reads **Reported** (past its day or not). The order's payment is Pending with their reference, unless part is paid already (it stays Partially Paid); its timeline says "Reported paying USD 4.00 for online order EC-00002 by bKash, reference BK-555, through its payment link", by The customer. Nothing is recorded as paid, and nobody is e-mailed: the business confirms it.
    - **The order's page**: the link Reported, and under it "The customer reported paying 27 Nov 2026, 06:36, by bKash, reference BK-555. Record their payment, or mark it not received." - the report stays on the link once it is paid or revoked. While it stands, the link is neither revoked nor replaced ("The customer reported paying through the link of USD 4.00 for order EC-00002: record their payment, or mark it not received, first."), the order is not edited (F5's dialogs are not offered, and the API refuses them, saying so), and Send Payment Link is not offered. Payment Not Received is offered for a part-paid order whose link is reported, too.
    - **Record Payment starts from the report**: "From the customer's report through their payment link, …: paid by bKash, reference BK-555.", into the account of the way they paid (bKash's Cash Box, not the order's own Bank Transfer's City Bank), by its kind (mobile money), the amount the link asks - never more than is owed - and their reference. Recorded, the link is Paid; the timeline adds ", reported through its payment link". An account that no longer takes customer payments stops it, as F3's does: "The account bKash takes money into is no longer active …: record this payment in Sales, …".
    - **Payment Not Received** clears the report - the link Active again, taking another while it works - counts the failure, and e-mails the customer as F3 does; its dialog says the report is cleared. A part-paid order keeps Partially Paid. The timeline: "Marked the payment reported through the payment link of online order EC-00003 as failed: …".
    - Cancelling an order revokes its reported link with the rest, the report kept on it; the customer's cancellation e-mail says the business will be in touch about their money, as for any payment Pending.
    - The API: `POST /api/v1/stores/{code}/pay/{token}` (`payment_method_id`, `reference`), without signing in; the link's page gives each method's `reference` and the link's `reported`; the order's page each link's `reported`; Record Payment's review gives `amount` and `report`.
    - **Checked** on the second PC (2026-11-27). The full suite ran in two halves in the foreground: 1287 tests, 1286 passing, `SalesSettingsApiTest` failing as before. The API's tests: `EcommercePaymentLinkReportsTest` (6). In a headless Chromium (`../f6a-setup.php`, then `../doc-19-phase-f6b-setup.py` - F6a's set-up with bKash asking for a reference - and `../doc-19-phase-f6b-ui-check.py`; links sent through the API, their addresses from the dev mail log). 7 steps, all passing on the final code:
      - EC-00002's link ($4.00): the two ways with their instructions; bKash without a reference refused under it; with BK-555, "Thank you: …", "We have your payment" and what was told, no choices - so again on reloading;
      - EC-00002's page: Payment: Pending, the link Reported with the report and "Record their payment, or mark it not received.", no Revoke, no Send Payment Link or Edit Products in the More menu, Payment Not Received offered, the timeline's entry;
      - Record Payment: the report's line, "Into Cash Box, paid by bKash. The order owes $4.00.", 4.00 and BK-555; recorded - "Payment RCT-00001 recorded for order EC-00002.", the link Paid with its report kept, the payment "Cash Box · BK-555", the link's page "Order EC-00002 is paid";
      - EC-00003's link ($29.00): reported by Bank Transfer with no reference; Payment Not Received - its dialog saying the report is cleared - "The payment of order EC-00003 is marked failed, and the customer told.", the link Active with no report, the timeline's entry; the customer told it again, by bKash with BK-777;
      - EC-00001, $10.00 of its $21.00 paid and paying in part on: a link of $6.00 reported by bKash; the order Partially Paid, the link Reported, Payment Not Received offered, and Record Payment at 6.00 into Cash Box ("The order owes $11.00.");
      - at phone width, what the customer told and the order's card;
      - EC-00001's report marked not received (through the API): Partially Paid still, the link Active, and its form - the choices and the reference - fitting at phone width.
    - In the database afterwards: RCT-00001 into Cash Box by mobile money with BK-555; EC-00002's link paid with its report, EC-00003's reported again, EC-00001's active with none; every e-mail sent by the queue worker. The only console message was the refusal the check asked for (422).
    - The first run stopped in its set-up (the payment methods' list is `data.methods`); the second found a mistake of the script's own (a regex meets an element's whole text, its trailing space too). The dev data was put back from the dump taken before the check (`../izsuite-dev-before-doc-19-phase-f6b-check.sql`) after each run. The screenshots were read one by one; the Payment Links card's words were changed after them ("When they report paying through one, Record Payment starts from what they told."). The database matched that dump, AUTO_INCREMENT included, and the backup from before the phase (`../izsuite-dev-before-doc-19-phase-f6b.sql`) but for migration 000235's columns.
- **Phase G: fulfilment** - fulfilments, partial ones too, with carrier and tracking; ready for pickup, shipped and delivered; the delivery document; digital delivery (secure downloads within limits, license keys, links, by hand) and services. Permission Manage Fulfilment.
- **Phase H: promotions, abandoned carts and quotations** - discount codes and automatic discounts with their rules; abandoned carts, recovered with consent; requests for a quotation, as CRM leads or draft quotations. Permission Manage Discounts.
- **Phase I: after the sale** - return requests and their resolutions, through credit notes and refunds; product reviews and their moderation; the customer's notifications. Permissions Process Returns, Process Refunds and Moderate Reviews.
- **Phase J: overview, documents and reports** - the eCommerce Overview and the Dashboard's card; its documents on the Documents page; the 21 reports. Permissions View eCommerce Reports and Export eCommerce Data.

After Doc 19 come Docs 20 and 21 (Customer Subscriptions, Marketing and Loyalty). Docs 22 and up must be written before they can be built.

## Testing checklist

Manual tests for each phase, to work through one by one once a doc is finished, ticking each off. Every phase adds its own here as it is built (from Doc 17 on). Earlier docs were checked as they were built: see each phase's "Checked in the browser" notes above.

Use a test business. Some tests need a second person with a limited role: in Settings → Users & Roles, make the role, then invite someone with it.

**Before testing on a PC,** bring it up to date as [Starting on another PC](#starting-on-another-pc) says: pull, migrate, restart the queue and the frontend. Each block below needs its phase's migration:

| Block | Migration |
|---|---|
| Doc 16: the fix of 2026-11-16 | none |
| Doc 17: Phases A1, A2 | 000162, 000163 |
| Doc 17: Phases B1, B2a, B2b | 000164, 000165, none |
| Doc 17: Phases C1, C2, C3 | 000166, 000167, 000168 |
| Doc 17: Phase D1 | 000169 |
| Doc 17: Phase D2a | 000170 |
| Doc 17: Phase D2b | none |
| Doc 17: Phase D3a | 000171 |
| Doc 17: Phase D3b | 000172 |
| Doc 17: Phase D4 | none |
| Doc 17: Phase E1 | 000173 |
| Doc 17: Phase E2 | 000174 |
| Doc 17: Phase E3 | none |
| Doc 17: Phase F1 | 000175 |
| Doc 17: Phase F2 | 000176 |
| Doc 17: Phase F3a | 000177 |
| Doc 17: Phase F3b | 000178 |
| Doc 17: Phase F4 | none |
| Doc 17: Phase G1 | 000179 |
| Doc 17: Phase G2 | 000180 |
| Doc 17: Phase G3a | 000181 |
| Doc 17: Phase G3b | 000182 |
| Doc 17: Phase G4 | none |
| Doc 17: Phase H1 | none |
| Doc 17: Phases H2, H3 | none, none |
| Doc 17: Phase I1 | 000183 |
| Doc 17: Phase I2 | 000184 |
| Doc 17: Phase I3 | 000185 |
| Doc 17: Phase I4 | 000186 |
| Doc 18: Phase A1 | 000187 |
| Doc 18: Phase A2 | 000188 |
| Doc 18: Phase B1 | 000189 |
| Doc 18: Phase B2a | 000190 |
| Doc 18: Phase B2b | 000191 |
| Doc 18: Phase C1 | 000192 |
| Doc 18: Phase C2 | 000193 |
| Doc 18: Phase C3 | 000194 |
| Doc 18: Phase C4 | 000195 |
| Doc 18: Phase D1 | 000196 |
| Doc 18: Phase D2 | 000197 |
| Doc 18: Phase D3a | 000198 |
| Doc 18: Phase D3b | 000199 |
| Doc 18: Phase D4 | 000200 |
| Doc 18: Phase E1 | 000201 |
| Doc 18: Phase E2 | 000202 |
| Doc 18: Phase E3 | 000203 |
| Doc 18: Phase F1 | 000204 |
| Doc 18: Phase F2 | 000205 |
| Doc 18: Phase F3 | none |
| Doc 18: Phase G1 | 000206 |
| Doc 18: Phase G2 | 000207 |
| Doc 18: Phase G3 | none |
| Doc 18: Phase H1 | none |
| Doc 18: Phase H2 | none |
| Doc 18: Phase H3 | none |
| Doc 18: Phase I1 | 000208 |
| Doc 18: Phase I2 | 000209 |
| Doc 18: Phase I3 | 000210 |
| Doc 19: Phase A1 | 000211 |
| Doc 19: Phase A2 | 000212 |
| Doc 19: Phase A3 | 000213 |
| Doc 19: Phase B1 | 000214 |
| Doc 19: Phase B2 | 000215 |
| Doc 19: Phase B3 | 000216 |
| Doc 19: Phase C1 | none |
| Doc 19: Phase C2 | none |
| Doc 19: Phase C3 | none |
| Doc 19: Phase C4a | 000217 |
| Doc 19: Phase C4b | none |
| Doc 19: Phase C5a | 000218 |
| Doc 19: Phase C5b | 000219 |
| Doc 19: Phase C6a | none |
| Doc 19: Phase C6b | 000220 |
| Doc 19: Phase D1 | 000221 |
| Doc 19: Phase D2 | 000222 |
| Doc 19: Phase D3 | 000223 |
| Doc 19: Phase D4 | 000224 |
| Doc 19: Phase D5 | 000225 |
| Doc 19: Phase E1 | 000226 |
| Doc 19: Phase E2 | 000227 |
| Doc 19: Phase E3 | 000228 |
| Doc 19: Phase F1 | 000229 |
| Doc 19: Phase F2 | 000230 |
| Doc 19: Phase F3 | 000231 |
| Doc 19: Phase F4 | 000232 |
| Doc 19: Phase F5a | none |
| Doc 19: Phase F5b | 000233 |
| Items: an item's attachments | none |
| Doc 19: Phase F6a | 000234 |
| Doc 19: Phase F6b | 000235 |

### Doc 16: Sales (the fix of 2026-11-16)

**Before you start:** the full Sales app on, two active locations, and a member whose role views sales orders, deliveries and returns, at one location only (Settings → Users & Roles, then their Locations).

- [ ] As that member, a delivery, return or advance at their location opens, with its files and steps. One at the other location does not ("This record is at a location you do not have access to."), and the Deliveries and Returns lists show only theirs.
- [ ] A delivery's or return's file opens only for someone who may view deliveries or returns at its location, even with its link.
- [ ] Price lists and a customer's Credit Control open for whoever has their permission, at any location.
- [ ] Admin panel → Legal Holds: with a hold on the business, deleting a draft delivery or return, their files, or a price list is refused.

### Doc 17: CRM

**Before you start:** turn CRM on in the Apps Center. It works with Sales Lite, full Sales or no Sales.

**Phase A1: menu, settings and lists**

- [ ] The sidebar's CRM section shows Leads, Opportunities and Customers, in that order. Opportunities opens a placeholder page, and Customers opens Contacts filtered to customers.
- [ ] Settings → CRM shows (after Expenses) for a role with Manage CRM Settings, and not for one without.
- [ ] Saving Opportunities Only takes Leads out of the sidebar at once; switching back brings it again. Nothing is deleted.
- [ ] Lead Sources (12), Lost Reasons (9) and Activity Types (8) start with Doc 17's examples. A starting entry can be renamed and switched off, but has no Delete.
- [ ] An entry you add can be renamed, switched off and deleted. A second entry with the same name in the same list is refused.

**Phase A2: the pipeline and sales teams**

- [ ] Settings → CRM shows the Sales Pipeline: New 10%, Qualified 25%, Proposal 50%, Negotiation 75%, Won 100% and Lost 0%.
- [ ] Add a stage, rename one, move one up and down, change a probability, switch one off and remove one, then save: it all stays after a reload.
- [ ] Won stays 100% and Lost 0%, whatever is typed.
- [ ] Switching off the only won stage (or the only lost one, or every open one) is refused: "A pipeline needs an open stage, a won stage and a lost stage, each switched on."
- [ ] Sales Teams show on the CRM tab, with full Sales off too. Add a team with a manager and members; switching it off keeps its manager.
- [ ] A role with Manage CRM Settings but not Sales's settings can add and change teams.

**Phase B1: leads**

- [ ] Add Lead without a phone or an e-mail: "Give a phone number or an e-mail address." Without a source: "Choose where the lead came from."
- [ ] A lead you add is yours and Assigned. Its page shows its details, source, owner, value, dates and who added it, and its location when the business has several.
- [ ] The Location field shows only to someone who may use more than one location.
- [ ] On a lead's page:
  - [ ] Mark Contacted makes it Contacted, and Qualify makes it Qualified (once what Settings → CRM requires is filled in: see Phase B2a).
  - [ ] Unqualify without a reason is refused; with one it is Unqualified, and the page shows the reason.
  - [ ] Reopen takes it back to Assigned.
  - [ ] Archive (the reason is optional) leaves only Reopen.
- [ ] An unqualified or archived lead's Edit page says to reopen it first.
- [ ] The Leads list shows the open leads to start. Its status filter (New is the queue, Archived, All leads), source filter, owner filter and search (name, business, phone, e-mail) all work.
- [ ] As someone with Assign Leads, add a lead with Owner "Nobody yet: the queue". It is New, offering Assign but not Mark Contacted or Qualify.
- [ ] Assign it to a member:
  - [ ] It becomes Assigned and theirs.
  - [ ] Their bell (and e-mail) says "… gave you a lead: …", and the link opens the lead.
  - [ ] The Activity Log shows the assignment with the previous owner.
- [ ] The Assign list offers only active people who may view leads at the lead's location.
- [ ] A member whose role has only Leads View and Create and Edit:
  - [ ] sees only their own leads, with no Owner filter and no Assign or Reopen;
  - [ ] gets "Lead not found." for anyone else's lead;
  - [ ] can add leads for themselves only.
- [ ] With View Team Records they also see the leads of their sales teams' people and those given to their teams. With View All CRM Records they see every lead.
- [ ] A member limited to some locations never sees leads at the others.
- [ ] With Settings → CRM on Opportunities Only:
  - [ ] Leads leaves the sidebar.
  - [ ] A lead's page still opens (from a notification, say) but shows no buttons.
  - [ ] Adding a lead is refused.
- [ ] A lead source that a lead came from cannot be deleted ("Leads came from this source, so it stays. Switch it off instead."). Nor can a sales team a lead names, or a location with leads.
- [ ] Settings → Notifications, for the business and in your profile, lists the new Leads kind.
- [ ] Admin panel → Privacy Requests: export a business's data. The ZIP holds:
  - [ ] the CRM files: `leads.csv`, `crm-lists.csv`, `crm-pipelines.csv` and `crm-pipeline-stages.csv`;
  - [ ] the Doc 16 files: deliveries and returns with their lines, customer advances, sales allocations, customer responses, price lists with their rules, customers and locations, and sales teams with their members.

**Phase B2a: qualification and interested items**

- [ ] Settings → CRM's Qualification card starts with Customer Need ticked. It shows only while the business uses leads.
- [ ] Tick other details (Interested Items, Next Action, say) and save: the Activity Log shows the change. Untick them all and save: any assigned or contacted lead can be qualified.
- [ ] Add Lead and Edit Lead mark the required details with a star, and a lead saves without them.
- [ ] Interested Items or Services:
  - [ ] Search adds one of your items; an item already added is not offered again.
  - [ ] Type a Name adds anything else. Each takes an optional quantity, and the bin removes it.
  - [ ] A row with neither an item nor a name is refused: "Choose an item or type its name."
  - [ ] A role without Items View sees no search, only Type a Name.
- [ ] The Qualification card on Add Lead takes the need, the budget (in the lead's currency), the purchase period, the decision maker, the business size, the next action and notes.
- [ ] A lead's page shows its qualification and items, and lists what qualifying still needs ("Before it is qualified, fill in: …").
- [ ] Qualify with something missing is refused, naming it ("Fill in the next action before qualifying this lead."). Once it is filled in, Qualify goes through.
- [ ] Deleting an item that a lead is interested in leaves the lead with the item's name.

**Phase B2b: a lead's attachments, comments and timeline**

- [ ] A lead's page shows Attachments, and a Comments and Activity card, below its qualification.
- [ ] Upload a file: it is listed with its size, date and who added it, Download opens it, and Remove takes it away.
- [ ] Someone whose role has Leads View but not Create and Edit sees the files but no Upload or Remove.
- [ ] A file of a lead someone does not see does not open for them, even with its link.
- [ ] Post a comment, and delete it. Only its writer can delete a comment.
- [ ] Mention someone (type @):
  - [ ] only people who see the lead are offered;
  - [ ] they get a bell notification (and an e-mail) linking to the lead's comments.
- [ ] The Timeline tab lists what happened with the lead, newest first: added, changed, assigned (with the previous owner), its steps, its comments as notes, and its files.
- [ ] With Settings → CRM on Opportunities Only, a lead's comments and files can be read but not added.

**Phase C1: converting a lead**

- [ ] Only a Qualified lead offers Convert, and only to a role with Convert Leads. Settings → Users & Roles lists Convert Leads under Leads.
- [ ] The Convert dialog:
  - [ ] lists the contacts matching the lead's e-mail, phone, business name or name first, each saying what matched;
  - [ ] offers Another contact (search any contact) and A new contact from this lead;
  - [ ] hides A new contact for a role without Create Contacts.
- [ ] The opportunity starts from the lead: its business or name, its estimated value and currency, its expected purchase date, at the first open stage. A closed (won or lost) stage is never offered.
- [ ] Converting to an existing supplier makes them both a customer and a supplier.
- [ ] Converting to a new contact makes one with the lead's business (or name), person, job title, e-mail, phone, address, language and tags.
- [ ] After converting:
  - [ ] the lead is Converted, with no buttons, and can no longer be edited;
  - [ ] its page shows the contact (a link to it) and the opportunity's name, stage, value and closing date;
  - [ ] the Leads page's Converted filter finds it;
  - [ ] its files, comments and timeline are still there, the timeline saying what it was converted into.
- [ ] A contact with an opportunity cannot be deleted ("This contact has CRM opportunities…"), nor can a location with one.
- [ ] Settings → CRM: removing a pipeline stage that holds an opportunity is refused ("Opportunities are in … switch it off instead").
- [ ] Admin panel → Privacy Requests: the business's data export holds `opportunities.csv`.

**Phase C2: possible duplicate leads**

- [ ] Add two leads with the same business name (in any case), e-mail or phone. The Leads page marks both "Possible duplicate"; a lead matching nothing is not marked.
- [ ] A lead whose business name, person's name, e-mail or phone matches a contact is marked too.
- [ ] A lead's Possible Duplicates card lists each match with what matched, and opens it (the lead, or the contact).
- [ ] Not a Duplicate needs a reason. Afterwards the match shows "Not a duplicate" with the reason and who decided, on both leads when it is another lead.
- [ ] Link marks a match Linked. A linked contact comes first, "linked to this lead", when the lead is converted.
- [ ] Once every match is decided, the lead is no longer marked on the Leads page. Take Back flags the match again.
- [ ] A member who sees only their own leads is not shown other people's leads as matches. Without Contacts View, no contacts are shown.
- [ ] A role without Create and Edit sees the card but no buttons.
- [ ] The Activity Log keeps each decision ("Linked lead … to contact …", "Marked … as not a duplicate of …", "Took back the decision about …").

**Phase C3: merging contacts**

**Before you start:** two copies of one contact sharing an e-mail or phone: one a customer with an address, notes and a tag; the other a supplier with a website, notes, another tag, an attachment and, if Sales is on, a draft invoice. CRM on, with a lead converted into the second.

- [ ] More → Merge Contacts on the first lists the second first, with what matched. Another contact can be chosen instead; one already merged is not offered.
- [ ] Review Merge shows what the kept one gains (only what it lacks), its role becoming Both, the tags and notes, what moves (the lead, the opportunity, the attachment) and what stays (the invoice).
- [ ] After the merge, the kept contact has those details, both notes, the attachment, a Merged Contacts card, and no Delete. The lead and its opportunity now name the kept contact.
- [ ] The merged contact's page says "Merged into …" with the date and who did it. It has no Edit, Archive, Merge, Delete or Upload, and still lists its invoice.
- [ ] The Contacts list shows it as Merged, without Edit or Delete. Adding a contact with its name gives no duplicate warning about it.
- [ ] Keeping the other contact instead opens the other's page after the merge.
- [ ] A role with Contacts Edit but not Merge has no Merge Contacts in the menu.
- [ ] The Activity Log has the merge on both contacts ('Merged contact "…" (C-…) into "…" (C-…)').

**Phase D1: opportunities themselves**

**Before you start:** CRM on, a second currency with today's rate (Settings → Currencies), a supplier contact, and a member whose role has Opportunities View, Create and Edit and Change Stages, but not Change Probability.

- [ ] Add Opportunity starts at the first open stage with you as owner. Only open stages are offered, and only active contacts.
- [ ] Saved for the supplier, the contact becomes Both. The page shows the expected value, the probability and the weighted value, also in the base currency at today's rate.
- [ ] A closing date already past shows Overdue, on the page and in the list.
- [ ] Change Stage offers the other open stages. Afterwards the probability is the new stage's, and the stage history shows each stage with when, by whom and how long.
- [ ] As the member: no Probability box on the form or in Change Stage. Moving is refused without Change Stages.
- [ ] Giving it to someone else tells them (bell and e-mail, under Opportunities). Someone who may not view opportunities cannot be chosen.
- [ ] Members see their own opportunities, their teams' with View Team Records, or all with View All CRM Records, only at their locations.
- [ ] The list searches by name or contact, and filters by status, stage and owner.
- [ ] Converting a qualified lead gives its opportunity the lead's interested items and a first stage. The lead's Converted card links to it.
- [ ] Settings → CRM will not remove a stage an opportunity is at or has been at; switching it off works.

**Phase D2a: won, lost, reopening and archiving**

**Before you start:** an open opportunity with a competitor, and a member whose role has Opportunities View and Mark Won or Lost, but not Reopen.

- [ ] Mark Won starts at today and the expected value. A later day is refused. Saved with another value and a reason, the opportunity is Won at 100%, its value is the final one, and a Won card shows the day, who marked it and the reason.
- [ ] Once won or lost it cannot be edited, moved or marked again. Only Reopen and Archive are offered.
- [ ] Reopen goes back to the last open stage it was at, with that stage's probability. The Won or Lost card is gone, and the stage history keeps every step.
- [ ] Mark Lost needs a lost reason and offers the opportunity's competitors. A follow-up day before the lost day is refused. Lost at 0%, its card shows the reason, competitor, notes and follow-up day.
- [ ] As the member: Mark Won and Mark Lost work, Reopen is not offered.
- [ ] Archive (under More) takes it out of the Open, Won, Lost and All lists. The Archived filter shows it. On its page only Restore is offered, and Restore brings it back as it was.
- [ ] A lost reason an opportunity was lost for cannot be deleted in Settings → CRM; switching it off works.
- [ ] The Activity Log has each step ('Marked opportunity "…" won', '… lost: …', 'Reopened … at …', 'Archived …', 'Restored …').

**Phase D2b: an opportunity's files, comments and timeline**

**Before you start:** an open opportunity owned by you, a member who sees every opportunity (View plus View All CRM Records), and one who sees only their own.

- [ ] Upload a file on its page. It is listed, it downloads, and Storage & Usage counts it. Remove takes it away again.
- [ ] As the member who sees every opportunity: the file opens, but there is no Upload or Remove without Create and Edit.
- [ ] As the member who sees only their own: the opportunity, its files and its comments are not found, even through a file's link.
- [ ] A comment mentioning both members tells only the one who sees the opportunity (bell and e-mail), with a link to its comments.
- [ ] Timeline lists, newest first, its adding, each move ("Stage: … → …", "Probability: … → …"), won or lost, reopening, archiving, its files, and its comments as notes.
- [ ] Archived: no Upload or Remove, but comments still work. Won or lost: files can still be added.
- [ ] Under a legal hold, removing its file or comment is refused.

**Phase D3a: stale stages and the fields a stage needs**

**Before you start:** CRM on, a qualified lead with no interested items, and an open opportunity with no requirements or competitors.

- [ ] Settings → CRM's pipeline has Stale After, days, and Needs for each stage. Won and lost stages take no stale period. A stale period of 0 or over 365 is refused.
- [ ] Give one open stage a stale period of 1 day and another stage two needs, such as Customer Requirements and Competitors. Save, reload: both are kept.
- [ ] Change Stage to the stage with needs marks the missing ones and keeps Move greyed out. Fill them in with Edit: the list ticks them and the move works.
- [ ] Adding an opportunity at that stage without them is refused, naming what is missing, under Stage.
- [ ] Give the won stage a need, such as Interested Items. Mark Won marks it missing and stays greyed out until it is filled in. The same goes for Mark Lost and the lost stage, and for Reopen and the stage it goes back to.
- [ ] Give an open stage Interested Items as a need. Converting the lead into that stage is refused, naming them, under Stage; into a stage that needs nothing, it converts.
- [ ] After more than a day at the stage with the 1-day period, the opportunity shows Stale with its days, in the list and on its page ("At … for 2 days, stale after 1 day."). The Stale filter lists only stale ones. Moved to another stage, it is no longer stale.
- [ ] Emptying a detail a stage needs, once the opportunity is there, is saved and leaves it where it is.

**Phase D3b: several pipelines, each for chosen teams or locations**

**Before you start:** CRM on, two active locations, a sales team, a contact, a qualified lead of that team, and a member whose role adds opportunities (View, Create and Edit) at one location only.

- [ ] Settings → CRM has a Pipelines header with Add Pipeline. The first pipeline's card has Switched On, Sales Teams and Locations, and no Delete.
- [ ] Narrowing the first pipeline to a team, or switching it off, is refused under Switched On: one for every team and location must stay on.
- [ ] Add a pipeline for the team. It comes last, switched on, with the six starter stages and Delete. Another of the same name is refused.
- [ ] Add one for one location, and one for everyone. The first pipeline gains Delete while that other for everyone is there; delete it, and the first loses Delete again.
- [ ] Adding an opportunity, Pipeline offers those for its team and location, each with its own stages. Choosing the team adds the team's pipeline. Moving the location off a location's pipeline moves the form to the first that fits, at its first stage.
- [ ] Added in the team's pipeline, its page says it stands in that pipeline. Editing it shows no Pipeline field.
- [ ] Converting the lead offers the pipelines for its team and location, and it converts into the one chosen.
- [ ] The Opportunities page has a Pipeline filter, names the stages with their pipeline until one is chosen, and shows each row's pipeline under its stage. Choosing a pipeline clears a stage of another.
- [ ] Switch the team's pipeline off. It shows Off, has no Delete once an opportunity is in it, and the form and the filter no longer offer it. Its opportunities stay, and still change stage.
- [ ] As the member, the form offers only the pipelines for their location (and for every location), and adding a pipeline is refused.

**Phase D4: the board and calendar views**

**Before you start:** CRM on, opportunities at several stages - one past its closing day, one won and one lost in the last month, one won longer ago - and a member whose role views opportunities and changes stages, but does not mark them won or lost, with one of their own.

- [ ] Opportunities has List, Board and Calendar. Reloading keeps the one chosen.
- [ ] The board has a column per stage. Each says how many it holds and what they are worth in the base currency: weighted while open, whole in Won and Lost. The one won longer ago is not there.
- [ ] Cards in an open column are the soonest to close first, with Overdue and Stale where they apply. Won and Lost show the day each closed.
- [ ] Drag a card to another open stage: "Moved to …", and the totals change. To a stage that needs a detail it lacks: refused, naming it, and the card stays.
- [ ] Drop a card on Won: Mark Won opens with today and its value. Mark it, and it leads the Won column. Drop one on Lost: Mark Lost opens.
- [ ] A card's ⋯ menu offers Change Stage, Mark Won and Mark Lost, and each works as on the opportunity's page.
- [ ] With a second pipeline, the board's Pipeline choice shows its columns; a switched-off one is marked (off). Switch a stage off while one is at it: its column stays, marked Off.
- [ ] Search and the owner filter narrow the board as they narrow the list.
- [ ] The calendar opens on this month, its weeks starting as Settings → Regional says. Each opportunity is on its closing day, the overdue one amber. Previous, Next and Today move between months.
- [ ] The calendar's filters work as the list's: All opportunities adds the won (green) and lost (grey) ones.
- [ ] As the member: only their own on the board and the calendar, no Owner filter, and their card's menu offers only Change Stage. Won and Lost do not take their card.

**Phase E1: activities**

**Before you start:** CRM on, a lead, an opportunity and a contact, and a member whose role views opportunities and plans activities (Activities: View, Create and Edit) but sees only their own records. Give the owner's role Activities too if it is a custom one.

- [ ] CRM's sidebar has Activities after Opportunities. The page opens on Planned, My activities.
- [ ] Plan Activity on the Activities page: pick an opportunity by name, a call at a time already past today, outgoing. It is listed, Overdue.
- [ ] The opportunity's page has an Activities card with it. Plan Activity there has For fixed to the opportunity; a meeting shows no Direction.
- [ ] Complete the call: it waits for an outcome. With one, and Plan a follow-up ticked, the card lists the follow-up planned and the call Completed with its outcome.
- [ ] Edit a planned one's time: "Activity saved." Settings → Activity Log shows each step, the edit in the business's time.
- [ ] Cancel one from the Activities page: it asks, then shows Cancelled under All activities. A completed or cancelled one has no menu.
- [ ] The status filter's Due today and Overdue, the type filter and the search narrow the list.
- [ ] Settings → CRM's Activity Types: switch Task to Need Outcome, and completing a task waits for one. Add a type with "Completing one needs an outcome" ticked; once an activity is of it, it cannot be deleted.
- [ ] Plan one for a lead, then convert the lead: it shows on the new opportunity's card and still on the lead's, which has no Plan Activity now.
- [ ] A contact's page has the card. A contact with activities cannot be deleted; merged into another, its activities move.
- [ ] As the member: only activities given to them, no Assigned to filter; Plan Activity offers only what they may see; completing theirs works.

**Phase E2: reminders, and telling someone given an activity**

**Before you start:** the scheduler and the queue running (see Deploying to a server), CRM on, an opportunity, and a member whose role views and plans activities, with an e-mail address you can read.

- [ ] Settings → Notifications & Email and My Profile's notifications list Activities, on in the bell and by e-mail.
- [ ] Plan Activity starts with Reminder "15 minutes before". Plan a call two minutes from now with "When it is due": its line says "Reminder when it is due".
- [ ] When its moment comes, the bell shows "Reminder: Call …, due …" leading to its record, and the e-mail arrives. It comes once.
- [ ] Move it to a later time: it reminds again then. A completed or cancelled one, and one with No reminder, never reminds.
- [ ] Give an activity to the member: their bell and inbox have "… gave you an activity: …". Saving it unchanged tells them nothing; giving it to yourself tells no one.
- [ ] Complete one with a follow-up given to the member: they hear of the follow-up.
- [ ] As the member, switch Activities' e-mail off in My Profile: the next notice reaches only the bell.

**Phase E3: the timeline of a lead, an opportunity and a contact**

**Before you start:** CRM and Sales on, a customer with an opportunity, and a member whose role views contacts and opportunities (their own only), but not activities or sales documents.

- [ ] On the opportunity, complete a call with a direction, a duration and an outcome, cancel a meeting, post a comment, move it to another stage and attach a file. Its Comments card's Timeline tab lists them newest first, each with its icon: the call with "Outgoing · 15 min" and its outcome, the cancelled meeting, the note, the move with its changes, the file. What is still planned is not there.
- [ ] Activities, Notes, Changes and Files each show only that part.
- [ ] Lose it, reopen it, win it: each shows marked, the win and the loss in green and red.
- [ ] The customer's page has a Timeline card: their sales documents, each opening its page, with its status and total; their own activities; and the opportunity's activities and notes, naming it with a link. Documents shows only the documents.
- [ ] Convert a lead into the customer: the lead's adding, qualifying and converting show on the customer's timeline.
- [ ] A lead's Timeline tab shows its activities done, notes, changes and files.
- [ ] As the member: the customer's Timeline card has no Activities or Documents, and none of the owner's opportunity; their own opportunity's entries show.

**Phase F1: sales documents made from an opportunity**

**Before you start:** Sales and CRM on, an item, an opportunity interested in it and in something typed in, and a member whose role sees all opportunities and views invoices, without Sales' values.

- [ ] The opportunity's page has Sales Documents, "None made from it yet.", and Create with Quotation, Proforma Invoice and Invoice - and Sales Order on full Sales.
- [ ] Create → Quotation: the form says it is made from the opportunity, with its customer, currency and interested items as lines - the item at its price, the typed name unpriced. Its back link leads to the opportunity.
- [ ] Save it: its page's History names the opportunity, and the card lists it with its total and status.
- [ ] Convert it to an invoice: the invoice names the opportunity too, and both are on the card. Issue the invoice: the opportunity's Timeline, under Documents, shows it issued above its making.
- [ ] Choose another customer on a new document made from it: "A document made from an opportunity is for its contact."
- [ ] Lose the opportunity: Create goes and the card says to reopen it. Archived, it says to restore it.
- [ ] As the member: only the invoice, with no total and no Create; the invoice's page names the opportunity.
- [ ] On a test business without Sales, the card says to turn it on, linking to the Apps Center.

**Phase F2: an opportunity won when its order is confirmed**

**Before you start:** the full Sales app and CRM on, an open opportunity, and a member whose role views and confirms sales orders, with no CRM access.

- [ ] Settings → CRM shows "Win an opportunity when its sales order is confirmed", ticked. Untick it and save ("CRM settings saved."), then tick it again.
- [ ] From the opportunity's Sales Documents, Create → Sales Order, then Save and Confirm: the opportunity is Won, 100%, today, at the order's value before tax, with "Sales order SO-… confirmed" as its reason. Its timeline shows the win above the confirmation.
- [ ] With the setting off, confirming such an order leaves its opportunity open.
- [ ] Lose an opportunity after making its order, then confirm the order: the order is confirmed, and the opportunity stays lost.
- [ ] An opportunity in another currency than its order is won at its own value.
- [ ] As the member, confirm an order of another opportunity: it is won, marked by them, though they cannot open it.

**Phase F3a: e-mail from a lead, an opportunity or a contact**

**Before you start:** CRM and Sales on, the queue running (see Deploying to a server), a contact with an e-mail address you can read, an opportunity for them with a sent quotation and a file, and a member whose role sees opportunities but has no Send E-mail.

- [ ] The opportunity's Timeline tab has E-mails and Send E-mail. The form starts with the contact's address and "Dear …", and offers the quotation (PDF) and the file - not a draft document.
- [ ] Send it with a subject, a copy address and both attached: "E-mail to … is on its way." It arrives under your business's name, with replies to its address, carrying both.
- [ ] On the timeline it goes from Sending to Sent, with whom it went to, what it carried and its message. Changes does not list it.
- [ ] The contact's Timeline card has Send E-mail, and the opportunity's e-mail names it.
- [ ] A lead's goes to its own address, with no documents. A converted or archived lead, an archived opportunity and a merged contact have no Send E-mail.
- [ ] A contact with e-mails is deactivated, not deleted; merged, its e-mails move.
- [ ] As the member: no Send E-mail, but the e-mails show.

**Phase F3b: e-mail templates**

**Before you start:** CRM on, an opportunity whose contact has a person's name and an e-mail address you can read, and a member whose role sends e-mail but does not manage CRM settings.

- [ ] Settings → CRM has E-mail Templates, "None yet." Add one using every placeholder chip: "Template added." Another by the same name is refused.
- [ ] On the opportunity, Send E-mail offers it under Template. Choosing it fills the subject and message: the person, their business, the opportunity, your name and your business's.
- [ ] Send it: it arrives as filled in. Edit or delete the template: the e-mail on the timeline stays as it went.
- [ ] From a lead, `{opportunity_name}` is empty and `{company_name}` is the lead's business.
- [ ] As the member: no CRM tab in Settings; their form offers the template, signed with their name.

**Phase F4: the CRM part of a contact's profile**

**Before you start:** CRM on, a contact with a lead converted into them and opportunities open, won and lost - one in another currency - and a member whose role views contacts and the opportunities they own.

- [ ] The contact's page has Opportunities above Activities: the Open, Won and Lost counts, the open ones' value and weighted value, and the won ones' value, in the base currency.
- [ ] It names the lead source, linking to the lead converted into them.
- [ ] The list has the open ones by closing day, then the won and lost, the latest first; an archived one is not there.
- [ ] Add Opportunity opens the form with the contact chosen.
- [ ] As the member: only their own opportunities, no lead source, and no Add Opportunity.

**Phase G1: the CRM Overview**

**Before you start:** CRM on, leads added this month and last (one qualified, one converted), opportunities open at several stages - one expected to close within 30 days - one won and one lost this month, an overdue activity, and a member whose role views opportunities but not leads.

- [ ] CRM's sidebar starts with Overview. It opens on This Month, with Leads, Opportunities (marked "Estimates, not sales") and Activities.
- [ ] The figures match what you added: new leads this month, those awaiting follow-up (New or Assigned), qualified ones, the conversion rate, open opportunities with their pipeline value and weighted forecast, closing soon, won and lost this month, and overdue activities.
- [ ] Last month's period counts last month's new leads, conversion rate, wins and losses; the open ones and the overdue activities stay as they are now.
- [ ] Each filter narrows the figures. Pipeline and stage narrow the opportunities only. A currency shows its own values.
- [ ] Overdue Activities opens the Activities page on its overdue ones.
- [ ] Settings → CRM: Closing Soon, days refuses 0 and 366. Set to 120, Closing Soon on the Overview counts those closing within 120 days.
- [ ] Working with opportunities only, the Leads figures go.
- [ ] As the member: the Overview has no Leads figures, and counts only their own opportunities. A role without View Opportunities has no Overview.

**Phase G2: the Forecast page**

**Before you start:** CRM on, open opportunities closing this month, in a later month, past their closing day and more than a year away, one won this month, and a member whose role views opportunities, with one of their own.

- [ ] CRM's sidebar has Forecast after Activities. It opens by closing month: Overdue, this month and the next 11, then Later, with a Total.
- [ ] Each row's count, expected value and weighted value match the opportunities closing then; this month's row has this month's win under Won This Month.
- [ ] By owner, by sales team (no team last) and by stage (the won stage holding this month's win), the totals stay the same.
- [ ] The filters narrow it as they narrow the Overview; its total matches the Overview's open opportunities, pipeline value and weighted forecast.
- [ ] As the member: only their own. A role without View Opportunities has no Forecast.

**Phase G3a: response tracking on a lead's and an opportunity's page**

**Before you start:** CRM on, a lead added to the queue (no owner), an open opportunity, and a member whose role views opportunities and sees all CRM records, but not activities.

- [ ] The lead's page has a Response and Follow-up card after its details: Added with when and by whom, First Assigned "Not yet: the lead is in the queue", First Contacted "Not yet", Last Contact "None yet", Next Activity "None planned" and Days Without Activity "under a day", since it was added. The card before it is called Source and Owner.
- [ ] Plan a call for the lead on its Activities card: Next Activity shows the call and when, without reloading the page. Once its time has passed, it has an Overdue pill.
- [ ] Complete the call: First Contacted is that moment, with its response time from when the lead was added; Last Contact says "An activity done"; Next Activity is "None planned" again.
- [ ] Assign the lead: First Assigned is that moment. Assign it to someone else: First Assigned stays, and "Given to … " shows the new owner and when.
- [ ] Mark it contacted, or send it an e-mail from its timeline: Last Contact moves ("Marked contacted", "An e-mail sent"), First Contacted stays.
- [ ] A lead e-mailed before anything else: First Contacted is when the e-mail was sent.
- [ ] Archive the lead: Days Without Activity no longer shows.
- [ ] The opportunity's page has a Follow-up card after its stages: Last Contact, Next Activity (overdue or not) and Days Without Activity. Its time at its stage stays on its Stages card.
- [ ] As the member: the opportunity's Next Activity shows when it is due, not what it is.
- [ ] Mark the opportunity won: Days Without Activity no longer shows.

**Phase G3b: the follow-up warnings on the CRM Overview**

**Before you start:** CRM on and using leads, with:
- a lead in the queue and one with an owner, both added more than two days ago and never contacted;
- an open opportunity closing within 30 days, added more than 14 days ago, with no activity or e-mail since;
- an opportunity with a call planned;
- a stage with a stale period that an opportunity has been at for longer;
- a member whose role views leads and opportunities, with one of their own.

On a fresh business, set the days in Settings → CRM to fit what you have, rather than waiting for them.

- [ ] Settings → CRM has Contact New Leads Within, days (2) and Recent Activity, days (14) beside Closing Soon, days (30). Switched to Opportunities Only, the leads' one hides. 0 or 366 is refused.
- [ ] The Overview's last group is Follow-up: Unassigned Leads, Leads Not Contacted, Overdue Activities, No Next Activity, Stale Opportunities, Closing Soon, Gone Quiet. Each is amber while it counts any.
- [ ] Unassigned Leads opens the Leads page on New (the queue); Leads Not Contacted on "Not contacted within 2 days", with the same leads it counted.
- [ ] No Next Activity, Stale Opportunities and Closing Soon, Gone Quiet open the Opportunities list on their status, with the same opportunities. The one with a call planned is not under No Next Activity.
- [ ] Plan an activity for the quiet one: No Next Activity drops by one. Complete it: Closing Soon, Gone Quiet drops by one.
- [ ] Contact New Leads Within set to more days than the leads' age: Leads Not Contacted is 0, and its note says the new days.
- [ ] An owner chosen on the Overview: the counts are theirs, and the links open the lists on that owner.
- [ ] As the member: only their own in each count and list.

**Phase G4: the Dashboard's CRM figures**

**Before you start:** CRM on and using leads, with:
- a lead in the queue and one with an owner, added this month and not contacted;
- an open opportunity closing within 30 days and one closing later;
- an activity due later today and one overdue;
- a member whose role views the Dashboard and opportunities, but not leads or activities, with an opportunity of their own.

- [ ] The Dashboard has a CRM card after the summary cards (and the Sales Pipeline, on full Sales): New Leads, Leads Awaiting Follow-up, Open Opportunities, Activities Due Today, Overdue Activities, Opportunities Closing Soon, Pipeline Value and Weighted Forecast. Its subtitle says they are not sales.
- [ ] Its figures match the CRM Overview's: new leads, awaiting follow-up, open, closing soon, pipeline value, weighted forecast and overdue activities.
- [ ] Each opens its list with the same records: Leads on All leads and on "Awaiting follow-up"; Opportunities as it opens and on "Closing within 30 days"; Activities on Due today and on Overdue, Anyone; the Forecast, whose total shows both values.
- [ ] The Dashboard's range set to last month: New Leads counts last month's leads, and the rest stay.
- [ ] With two locations, choosing one narrows every figure.
- [ ] Switched to Opportunities Only, the lead figures go. With CRM switched off, the card goes.
- [ ] As the member: the card shows only their own open opportunities and their values, without the lead and activity figures. The CRM Overview has no Overdue Activities card, and typing `/crm/activities` takes them to their first page.

**Phase H1: the lead reports**

**Before you start:** CRM on and using leads, with:
- leads added this month from two sources: one in the queue, one contacted within two days, one contacted later, one qualified and one converted;
- one lead added last month;
- a member whose role has View Reports, View CRM Reports and View Leads, with a lead of their own.

- [ ] The Reports page ends with a CRM group: Leads by Source, Leads by Status, Leads by Owner, Lead Response Time, Lead Qualification Rate and Lead Conversion Rate.
- [ ] Leads by Source counts this month's leads by source. Being worked, ready to convert, converted and closed add up to each row's leads. Last month's lead is not there until the range takes it in.
- [ ] Leads by Owner shows each owner and No owner (the queue). Leads by Status shows every status with its share.
- [ ] Lead Response Time shows contacted, within 2 days, not contacted, the average in hours and the share in time.
- [ ] Its Group By takes Lead Source, Owner and Sales Team.
- [ ] Change Contact New Leads Within in Settings → CRM, and the report's column follows the new days.
- [ ] Lead Qualification Rate and Lead Conversion Rate for This Year have a row for every month.
- [ ] The Owner and Lead Source filters narrow every report. The CSV, Excel and PDF hold the same rows.
- [ ] Settings → Users & Roles lists CRM Reports: View CRM Reports. A role saved with it has View Reports too.
- [ ] As the member: only their own leads in each report. Without View CRM Reports, the CRM group is gone.
- [ ] With Opportunities Only in Settings → CRM, or with CRM off, the lead reports are gone.

**Phase H2: the opportunity reports**

**Before you start:** CRM on, with:
- open opportunities at two stages, one closing within 30 days, one moved on from an earlier stage;
- one won and one lost this month, the lost one with a reason and a competitor;
- one archived;
- a member whose role has View Reports, View CRM Reports and View Opportunities, with an opportunity of their own.

- [ ] The CRM group lists Opportunities by Stage, Pipeline Value, Weighted Forecast, Stage Duration, Opportunities Closing Soon, Won and Lost Opportunities, Win Rate and Lost Reasons after the lead reports.
- [ ] Opportunities by Stage shows every open stage, empty ones too, with the open ones' values, days at the stage and stale count. The archived one is in none of the reports.
- [ ] Pipeline Value opens by stage, its shares adding up to 100%. Weighted Forecast opens by closing month and matches the Forecast page.
- [ ] Both group by owner and sales team too.
- [ ] Stage Duration shows the earlier stage's visit with its days, and the current stage's days so far.
- [ ] Opportunities Closing Soon lists the one closing within 30 days, with its days left, and the sums above.
- [ ] Each list row opens its opportunity.
- [ ] Won and Lost lists both, the lost one with its reason and competitor, and the won and lost values above.
- [ ] Win Rate shows the share won by number and by value. Set to This Year, it has every month.
- [ ] Lost Reasons shows the reason, its share and the competitor.
- [ ] The Owner, Lead Source and Stage filters narrow them, and Pipeline does too while there are several. The CSV, Excel and PDF hold the same rows.
- [ ] As the member: only their own opportunities in each.

**Phase H3: people and activity**

**Before you start:** the data of Phase H2, with:
- a sales team holding the member;
- leads and opportunities of the member's in that team;
- activities done this month for both people, one completed after its due time, one cancelled, and one overdue;
- an open opportunity with no activity planned.

- [ ] The CRM group ends with Sales Team Performance, Salesperson Performance, Activity Completion, Overdue Activities and Opportunities Without Follow-up.
- [ ] Salesperson Performance has a row for each person: leads added and converted, won and lost, the win rate, the value won, the open pipeline and the activities done. The most won come first.
- [ ] Sales Team Performance shows the team and No sales team the same way. An activity counts for its record's team.
- [ ] Activity Completion shows due, completed, on time, cancelled, still planned and overdue, with both rates. Its Group By takes Assigned To and Activity Type.
- [ ] Overdue Activities lists the overdue one, with what it is for and its days overdue.
- [ ] Opportunities Without Follow-up lists the open one with nothing planned, with its last contact and days without activity.
- [ ] On the activity reports, the owner filter reads Assigned to.
- [ ] A role without View Leads sees Salesperson Performance without its lead columns; without View Activities, without Activities Done.
- [ ] A role without View Activities has no Activity Completion or Overdue Activities.

**Phase I1: assignment rules**

**Before you start:** CRM on and using leads, with:
- two members whose roles view and add leads, both in a sales team;
- a third member who may use one location only;
- a member whose role has Settings' View and Manage Assignment and Scoring Rules, but not Manage CRM Settings.

- [ ] Settings → CRM has Assignment Rules after the CRM card. Add a rule giving Referral leads to one member: "Rule added.", and its row says so.
- [ ] Add a rule giving leads in your country to the team by turns. Add a lead without an owner (owner "Nobody yet: the assignment rules, else the queue"), from Referral: it goes to that member.
- [ ] Add three more leads in your country: they go to the team's members in turn, by name.
- [ ] Change that rule to Fewest open leads: the next lead goes to whoever has the fewest open leads.
- [ ] Add a rule for a location the third member may not use, giving leads to them: a lead there passes on to the next rule.
- [ ] Switch a rule off: it shows Off and is passed over. Move one up: "Order saved.", and the first that fits is now that one.
- [ ] A lead that fits no rule waits in the queue. Its Assign dialog's "Use the Assignment Rules" says no rule fits; add one that fits, and it takes the lead.
- [ ] A lead added with an owner chosen keeps that owner. The Activity Log names the rule behind each assignment.
- [ ] As the member with only Manage Assignment and Scoring Rules: the CRM tab shows the Assignment Rules alone. Without the permission, the card is not there.
- [ ] With Opportunities Only, the card is gone.

**Phase I2: lead scoring**

**Before you start:** CRM on and using leads, with a member whose role has Settings' View and Manage Assignment and Scoring Rules, but not Manage CRM Settings.

- [ ] Settings → CRM has Scoring Rules after Assignment Rules: "None yet: leads are not scored." The Leads page has no Score column.
- [ ] Add "Website leads" (Comes from a lead source, Website, 20): "Rule added. The open leads are being scored again." Leads from the website already there now score 20.
- [ ] Add rules for an e-mail (10), an estimated value of at least an amount (25) and buying this month (20). Each row says what it asks, with its points in green; add one with a minus and they show in red.
- [ ] Add a website lead with an e-mail, a value above that amount and This month. Its page shows its score and band by its status, and the Lead Score card lists the rules that gave it.
- [ ] A value in another currency counts at today's rate. With no rate today, it gets no value points.
- [ ] The Leads page has a Score column. The Score filter's Hot, Warm and Cold each show only theirs.
- [ ] With a rule for being contacted, or for activities done and e-mails sent: mark a lead contacted, complete an activity or send an e-mail for it, and its score moves.
- [ ] Edit a lead: its score follows. Points above 100 stay at 100, and the card says what they add up to.
- [ ] Switch a rule off: it shows Off, and the leads lose its points. Delete one: a confirmation first, then the same.
- [ ] Archive a lead: it keeps its score. Switch off or delete every rule: no lead has a score, and the Score column goes.
- [ ] A score never qualifies or rejects a lead by itself.
- [ ] As the member with only Manage Assignment and Scoring Rules: the CRM tab shows the two rules cards alone.

**Phase I3: marketing consent**

**Before you start:** CRM on and using leads, with a member whose role views leads and contacts but cannot edit either.

- [ ] Settings → CRM has Consent Purposes after Activity Types, with Promotions and Offers, Newsletters, Product Updates, Events and Invitations, and Feedback and Surveys.
- [ ] A lead's page has Marketing Consent: "None recorded: they have not been asked yet."
- [ ] Record Consent: Newsletters, E-mail, Consent given, Website Form, an earlier day and time, an expiry, a reference. "Consent recorded.", and its row shows them.
- [ ] Record a refusal for another purpose by SMS: the expiry field goes, and the row reads Refused.
- [ ] A day or time still to come, or an expiry not after the day, is refused.
- [ ] Withdraw the consent given, with how: "Consent withdrawn." It reads Withdrawn, and History keeps both answers, with the withdrawal.
- [ ] Record the same purpose and channel again: the new answer stands, and History marks the old one replaced.
- [ ] Record a consent given that expired yesterday, or wait out an expiry: it reads Expired.
- [ ] Archive the lead: it still takes a refusal. Its timeline shows each answer.
- [ ] Qualify and convert it: its contact's page shows the same answers. The lead's card says they went to its contact, with no Record Consent.
- [ ] On the contact, record and withdraw as on the lead. Merge another contact with answers into it: the preview counts the consent records, and they move.
- [ ] Deleting a contact with consent records is refused: deactivate it. A consent purpose answered about cannot be deleted: switch it off, and it is no longer offered.
- [ ] As the member who cannot edit: both cards show, without Record Consent or Withdraw.

**Phase I4: custom fields**

**Before you start:** CRM on and using leads, with a member whose role has Settings' View and Manage Assignment and Scoring Rules, but not Manage CRM Settings.

- [ ] Settings → CRM has Custom Fields after E-mail Templates: "None yet: the forms ask only izSuite's own fields."
- [ ] Add a required text field for leads: "Field added." Add a choice for leads, with options one to a line; blank lines are dropped.
- [ ] A choice without options, an option twice, or a name the record's fields have already is refused.
- [ ] Add a number, a date and a yes or no field for opportunities. Move a field up or down: "Order saved.", among its own only.
- [ ] Add Lead shows More Details after Qualification, in that order. Save it without the required field: it says to fill it in.
- [ ] Type letters in a number field, or leave a required yes or no Not answered: each is refused under its field.
- [ ] Saved, the lead's page shows each value, a date in the business's format and yes or no in words. Edit it: the values are filled in, and changes are kept.
- [ ] Add Opportunity asks only the opportunity fields, and its page shows them.
- [ ] Take an option away from the choice: the lead keeps its own, marked "no longer offered" on its form. Another lead cannot pick it.
- [ ] Switch a field off: it leaves the forms and pages. Switch it on: the values come back.
- [ ] A field with values cannot be deleted, nor its type changed. One without values is deleted.
- [ ] As the member without Manage CRM Settings: the CRM tab has no Custom Fields card.

### Doc 18: Point of Sale

**Phase A1: POS comes on, with Settings → Point of Sale**

**Before you start:** a test business on Standard or above, with a cash payment account that takes customer payments, and a member whose role has Settings' View but not Manage POS Settings.

- [ ] The Apps Center shows Point of Sale, needing Sales Lite. Turn it on: the sidebar shows Point of Sale.
- [ ] Settings → Point of Sale opens with a Cash method on your cash account, giving change. Opened again, nothing is added twice.
- [ ] Choose a walk-in customer, a return policy and a footer: "POS settings saved." A supplier or an archived customer is refused; None clears it.
- [ ] Add a card method on a bank account: the form ticks "enters a reference" and offers no change. Saved, the row shows its account and currency.
- [ ] An account that takes no customer payments is not offered, and refused. Two methods cannot share a name.
- [ ] Switch a method's account off: the method says it cannot be used. Switch a method off: it shows Off.
- [ ] Delete a method through its confirmation. The Activity Log names each change.
- [ ] As the member without Manage POS Settings: no Point of Sale tab.
- [ ] Turn POS off: the settings can be read, not changed, and come back as they were when it is on again.

**Phase A2: registers**

**Before you start:** POS on, with a Cash and a Card method in Settings → Point of Sale; two locations; a member who may use one of them only; and, for the price list, the full Sales app with an active price list for one location.

- [ ] Point of Sale → Registers opens: "No registers yet."
- [ ] New Register starts at the location picked in the top bar, in the base currency, taking the methods in it. Typing the code shows the prefix it will get: the code and a dash.
- [ ] Add one with a cashier: "Register added." The row shows its code, location, currency, methods, cashiers, prefix and Active.
- [ ] A second register with the same name, or the same code in any case, is refused under that field.
- [ ] Change the currency: methods in another currency come off. Change the location: cashiers who may not use it come off; the member with one location is offered only at theirs.
- [ ] Choose an account for the cash: a cash method in another currency can then be taken. An account of another location is not offered.
- [ ] With full Sales: a price list for another location, or in another currency, is not offered. Without full Sales there is no Price List field.
- [ ] Edit it: another prefix, then Inactive or Maintenance: "Register saved."
- [ ] In Settings → Point of Sale, deleting a method the register takes is refused, naming it. A location with a register cannot be deleted.
- [ ] As the member with one location: only its registers show. Without Manage Registers: no New Register, Edit or Delete. Without Registers' View: no Registers in the sidebar.
- [ ] Delete a register through its confirmation: "Register deleted." The Activity Log names each change.

**Phase B1: opening and closing sessions**

**Before you start:** POS on with a Cash method; two registers at different locations, with you as a cashier of one and another member as a second cashier of it; a member with Sessions' View alone; and a member limited to the other location.

- [ ] Point of Sale → Sessions opens: "No sessions yet." Open Session offers the registers you are a cashier of; the others say why not (switched off, under maintenance, not a cashier).
- [ ] Open one with an opening cash and a note: "Session S-00001 opened." on its page, Open, with the cash expected equal to the opening cash.
- [ ] The Sessions page now offers your session instead of Open Session. The other cashier of that register cannot open one there: "This register has a session open."
- [ ] While it is open, the register cannot be switched off, moved or given another currency: "Session S-00001 is open on this register: it is closed first."
- [ ] Close it with the count matching: no reason is asked; "Session S-00001 closed." The Z report shows the cash expected and counted, and no variance.
- [ ] Open another and count less: the form says how much is short and asks why, and refuses to close without a reason. With one, the Z report shows the variance and the reason, and the list shows it in red.
- [ ] Only its cashier closes a session: the other cashier sees no Close Session on it.
- [ ] The register that held sessions has no Delete on the Registers page; it can be switched off.
- [ ] As the member with View alone: the sessions show, with no Open Session. As the member at the other location: only its sessions show, and another's session page is not found.
- [ ] The Activity Log names each opening and each closing, with its variance.

**Phase B2a: the supervisor's side of sessions**

**Before you start:** POS on with a register and two of its cashiers; a member who approves variances and is not a cashier; and a member with Reopen Closed Sessions.

- [ ] Settings → Point of Sale: set Variance Needing Approval to 5: "POS settings saved."
- [ ] As a cashier, close a session $10 short with a reason: "Session … closed. Its variance waits for approval." It shows Closing, with the warning, and the register can open its next session meanwhile.
- [ ] As the approver: the session offers Approve Variance. Approved: "Variance approved: session … is closed.", its Z report naming you.
- [ ] As the approver, close a cashier's open session: the form asks why you close it, naming its cashier, and the Z report shows the reason. The other cashier sees no Close Session on it.
- [ ] As the approver, close your own session beyond the limit: it closes at once, approved by you. Empty the limit: no closing waits.
- [ ] As the member who reopens: Reopen Session asks why. While the register or its cashier has another session open, or the register is not active, it is refused, saying which.
- [ ] Reopened, the session is open again, and its Z report shows under Earlier Closings with the reason. Closed again, it has a new Z report.
- [ ] The Activity Log names each approval, each supervisor's closing with its reason, and each reopening.

**Phase B2b: counting by notes and coins**

**Before you start:** POS on with a register in the base currency, and a second currency in the business if it has one.

- [ ] Settings → Point of Sale → Closing Sessions shows Variance Needing Approval, moved there. Tick counting by notes and coins: each currency shows its usual notes and coins, or none for one izSuite does not know.
- [ ] Change a list, out of order and with one twice: saved, it reads to the cent, once each, the largest first. A note of 0 is refused under its currency.
- [ ] Close a session: the form lists the notes and coins, with each line's amount and their total as the cash counted, over, short or matching as you type.
- [ ] Closed, the Z report shows Notes and Coins: how many of each, with their amounts.
- [ ] Empty a currency's list, or switch counting off: the close form asks for one amount again.

**Phase C1: the selling screen**

**Before you start:** POS on with a register you are a cashier of, a walk-in customer in Settings → Point of Sale, items in a category or two (one with a barcode and a SKU), and - with full Sales - a price list for the register and a customer's own.

- [ ] Point of Sale's sidebar has Open POS first. Without a session it says so; Open Session opens one and stays there.
- [ ] Before any search: the most-sold items at the register's location, and the category tiles. A tile shows its items.
- [ ] Type a name, SKU or category: the items to sell, priced at the register. Scan or type a barcode or SKU with Enter: it goes into the cart, and again adds one more.
- [ ] Each line says where its price comes from: the register's price list, else the item. Choose a customer with a list of their own: theirs, by quantity too.
- [ ] Quantities step and type to 3 decimals; 0 is refused under its line. A line note and the order note stay.
- [ ] With taxes on, the totals show each tax; with prices including tax, the total is the shelf price.
- [ ] Reload: the cart comes back, priced. Cancel Order asks first, then empties it.
- [ ] As a member without Open POS: no Open POS in the sidebar, and the page says their role does not allow it.

**Phase C2: payment and the completed sale**

**Before you start:** POS on, a register taking Cash (giving change) and Card (needing a reference), a walk-in customer, priced items, and a member whose role holds Open POS but not Sell Items.

- [ ] Fill the cart and Pay: Cash, with more handed over than is due, shows the change; Complete Sale says the sale's number and the change.
- [ ] Split a sale: Card for part - Complete Sale waits for its reference - and Cash for the rest.
- [ ] Sales → Invoices lists the sale marked POS, issued and paid; its page shows its payments and Made In Point of Sale. Payments Received lists each payment.
- [ ] The session's page counts the sales, the payments by method and the cash expected; its Z report keeps them.
- [ ] Change an item's price while its payment is open: Complete Sale says the total now, and the cart shows it.
- [ ] As the member: Open POS builds a cart, but there is no Pay.

**Phase C3: discounts, price overrides and cash rounding**

**Before you start:** POS on, a register taking Cash and Card; in Settings → Point of Sale → Selling, a 10% discount limit and cash rounding to 0.05; and a member whose role may sell and give discounts, but not override prices or go beyond the limit.

- [ ] On a line, + Price or Discount: 10% off shows what it takes off; a discount on the whole sale shows under Discount.
- [ ] Pay in cash: the total rounds to 0.05 (Cash Rounding), and the invoice has a Cash rounding line; paid by card alone, it does not round.
- [ ] Type a price over an item's: the line says the price it replaced, and Pay waits for why.
- [ ] Go beyond the limit: Pay waits for why; the order and the invoice keep the reason.
- [ ] As the member: no Price field; beyond the limit, the cart says someone with Discount Beyond the Limit gives it, and Pay stays greyed out.

**Phase C4: the receipt**

**Before you start:** POS on, a register taking Cash and Card; in Settings → Point of Sale, a return policy and a receipt footer; a customer with an e-mail address; and a member whose role may sell, but not reprint receipts.

- [ ] Complete a cash sale: the dialog has Receipt, E-mail Receipt and New Sale. Receipt opens the receipt on a narrow roll: the business, the receipt number, date and time, register, cashier, the items, taxes, total, cash handed over, change, the return policy and the footer.
- [ ] Close it: under the cart, Last sale says Reprint Receipt. Its copy says REPRINT.
- [ ] E-mail Receipt to an address: it says it is on its way, and the e-mail comes with the receipt attached.
- [ ] Sell to the customer with an address: the dialog's e-mail starts with it, and the receipt names them.
- [ ] As the member: the first copy prints; Last sale then offers E-mail Receipt alone.

**Phase D1: holding and resuming orders**

**Before you start:** POS on, a register taking Cash; in Settings → Point of Sale → Selling, Keep Held Orders For 4 hours; a customer; and a member whose role may sell, hold and resume orders, but not cancel them.

- [ ] Fill the cart - a line note, the order note, the customer - and Hold Order: it says the order is held, the cart empties, and Held Orders counts 1.
- [ ] Change an item's price on the Items page, then Resume the order from Held Orders: it says the price changed, and the total then and now, before it loads.
- [ ] Resumed, the cart has its customer, lines and notes; it sells as any sale.
- [ ] Cancel a held order: it asks why, and it is gone from the list.
- [ ] As the member: a held order shows when it expires, with Resume and no Cancel. Held at another register, it is not in the list.

**Phase D2: pending payments**

**Before you start:** POS on, a register taking Cash and Card; in Settings → Point of Sale, Card's payments waiting to be confirmed; and a member whose role may sell, but not confirm pending payments.

- [ ] Pay part of a sale by card: its line says it waits; the sale's dialog says the card is pending, and the cart has Pending Payments (1).
- [ ] On the session's page, Waiting to Be Confirmed lists it. Confirm it: the session counts it under Card, and the invoice is paid.
- [ ] Sell by card alone and Decline it with a reason: the invoice still owes it.
- [ ] As the member: no Pending Payments in the cart, and no list to confirm on the session's page.

**Phase D3a: credit sales**

**Before you start:** POS and full Sales on, a register taking Cash; a customer with payment terms and a small credit limit that warns; and a member whose role may sell, but not create credit sales.

- [ ] Sell to the customer, pay part in cash, and tick "Leave what is still to pay on the customer's account": the sale completes, saying what is on account; its invoice owes the rest, due by their terms.
- [ ] Sell to them again on account, over the limit: it completes with a warning. Make the limit block: the next one asks why, and completes once given.
- [ ] To the walk-in customer, the box is not there.
- [ ] As the member: no box in the payment dialog.

**Phase D3b: the customer's credit as a payment**

**Before you start:** POS and full Sales on, a register taking Cash, and an advance recorded for a customer (Sales → Advances).

- [ ] Sell to that customer: the payment dialog offers their credit. Tick it: Credit to Use starts with as much as it can, and Still to Pay is the rest.
- [ ] Complete the sale: it says what the credit paid; the invoice shows the credit applied, and the advance has that much less left.
- [ ] The walk-in customer is offered no credit.

**Phase D4: the formal invoice**

**Before you start:** POS on, a register taking Cash, and a customer other than the walk-in one.

- [ ] Sell to that customer; under the cart, Last sale's Formal Invoice opens an A4 invoice with the business's invoice number, naming the POS receipt.
- [ ] The row then names the invoice; Sales' Invoices list it once, by its invoice number, marked POS.
- [ ] Sell to the walk-in customer: no Formal Invoice.

**Phase E1: returns and their refunds**

**Before you start:** POS on, a register taking Cash and Card; a cash sale to the walk-in customer and a card sale to a named one; and a member whose role may process returns, but not refunds.

- [ ] Return, and the cash sale's receipt number: its items show what is still returnable. Bring one back with a condition and a reason: it says what it credits, at the sale's prices and taxes.
- [ ] Pay it back in cash: the return is recorded; the session's Cash Refunds count it. The walk-in customer is offered no credit.
- [ ] The card sale: pay it back by its own method - or keep it as the customer's credit, and nothing is paid out.
- [ ] Bring back more than is left: it is refused.
- [ ] As the member: no way to pay back a walk-in customer's return - only, from Phase E3, to exchange it.

**Phase E2: refund receipts and other refund methods**

**Before you start:** POS on, a register taking Cash and Card, Card's payments waiting to be confirmed, and a cash sale.

- [ ] Return from the sale, By another method - Card, with why: it says the refund waits to be confirmed.
- [ ] Refund Receipt: headed so, naming the return and its sale, the card refund pending.
- [ ] On the session's page, Refunds Waiting to Be Confirmed lists it: Confirm pays it out of the card's account; Decline, with why, keeps the credit on the credit note.
- [ ] A member without Use Alternative Refund Methods is not offered another method.

**Phase E3: exchanges**

**Before you start:** POS on, a register taking Cash, and a cash sale of a mug to the walk-in customer.

- [ ] Return the mug, Exchanged: it says its credit pays for the replacement. Start the Exchange Sale: the cart says so.
- [ ] Add something cheaper: Pay shows nothing left to pay, and Take Payment the exchange and what is paid back in cash. Completed, the receipt shows the exchange and Paid back; the session's Cash Refunds count it.
- [ ] Return another sale as an exchange, and add something dearer: the payments pay the rest.
- [ ] As a member without Process Refunds: a cheaper replacement is refused; a dearer one is sold.

**Phase F1: cash movements**

**Before you start:** POS on, a register taking Cash, a safe (a cash account) and a bank account, and an open session with a float and a cash sale.

- [ ] Cash In or Out: it says what the drawer should hold. A Safe Drop into the safe is recorded; the session's Cash Out and Cash Expected count it.
- [ ] A Bank Deposit offers the bank accounts; more than the drawer holds is refused.
- [ ] Settings → Point of Sale: a Cash Movement Needing Approval. As a member who may not approve: one above it waits, and their session will not close; approved on the session's page, it counts.

**Phase F2: paying an expense from the drawer**

**Before you start:** Phase F1's, with the Expenses app on and an expense category.

- [ ] Cash In or Out, Paid Out: who it is paid to, the category, the amount and why. Recorded, Expenses lists it, paid from the drawer's account; the session's Cash Out counts it.
- [ ] The session's page names its payee and the expense.

**Phase F3: the X and Z reports**

**Before you start:** POS on, a register taking Cash, and an open session with a cash sale and a cash movement.

- [ ] The session's page: X Report opens its totals so far on the receipt's roll, "Not a closing".
- [ ] Close the session with a variance and why: the page now offers Z Report - the cash counted, the variance and why.

**Phase G1: the POS pages of records**

**Before you start:** POS on, and a session with a couple of sales, a return and a cash movement.

- [ ] Point of Sale's menu: Orders, Returns and Refunds, and Cash Movements after Sessions.
- [ ] Orders lists the sales, with what returns credited; the search finds a receipt number. Returns and Refunds lists the return and opens its refund receipt. Cash Movements lists the movements; Kind filters them.
- [ ] A session's page: "Its sales" opens Orders for that session alone.
- [ ] As a member with Orders' View alone: the menu shows Orders, and not the others.

**Phase G2: the POS Overview and the Dashboard's POS card**

**Before you start:** POS on, and today a cash sale, a card sale and a return.

- [ ] Point of Sale's menu starts with Overview: today's sales, average, returns, open sessions and variances; what each method took; the top items; the open sessions.
- [ ] The Dashboard shows a Point of Sale card with the same figures.
- [ ] Close the session short: the Overview lists it under Cash Variances.

**Phase G3: POS documents on the Documents page**

**Before you start:** POS on, a cash sale, another formally invoiced, and a return of the first.

- [ ] Documents, Source app Point of Sale: the POS Sales Receipt, the POS Invoice, the Credit Note and the Refund Receipt, each with its order, register and session.
- [ ] The Refund Receipt opens on Returns and Refunds, searched for it.
- [ ] As a member who may not view POS's returns: no refund receipts.

**Phase H1: the POS sales reports**

**Before you start:** POS on, taxes on, two registers, cash and card sales on two days, and a return.

- [ ] Reports: a Point of Sale group of twelve reports.
- [ ] POS Sales Summary by day: every day of the range, each sale once, its return taken off, the headline figures and the chart.
- [ ] The Register and Cashier filters narrow it, and show in its period line.
- [ ] Grouped by hour, item and cashier, and the "Sales by" reports, agree with it.
- [ ] Sales by Payment Method: each method's payments, and what it paid back.
- [ ] Average Order Value: the averages with and before tax, and items per order.
- [ ] CSV, Excel and PDF.
- [ ] As a member without View POS Reports: no Point of Sale group.

**Phase H2: discounts, taxes, returns, voids and items**

**Before you start:** POS on, taxes on, a card method confirmed later, an item with a purchase price, and sales with a line discount, a sale discount and a price override; a return, a held order cancelled, and a card payment declined.

- [ ] Discounts and Price Overrides: each such sale, its discounts and overrides, and why.
- [ ] Taxes Collected: by rate, less the returns' tax.
- [ ] Returns and Refunds: each return, how it was paid back, and the headline figures.
- [ ] Void and Cancellation Report: the cancelled held order and the declined payment, with who and why.
- [ ] Top-Selling Items by quantity, and narrowed to an item category.
- [ ] Estimated POS Margin: only items with a cost in its totals.
- [ ] As a member without POS's returns, tax information or item costs: none of those three reports.

**Phase H3: sessions and cash**

**Before you start:** POS on, a variance limit, a session with a cash sale and a safe drop closed short beyond the limit and approved by a supervisor, and another session still open with a sale.

- [ ] Session Summary: both sessions, the closed one's count and variance, the open one's figures now.
- [ ] Cash Variance Report: the short session, why, and who approved it.
- [ ] Cash Movement Report: the safe drop, its account, and who recorded it.
- [ ] As a member who may view sessions but not cash movements: no Cash Movement Report.

**Phase I1: the cashier's PIN and the register's lock**

**Before you start:** POS on, Settings → Point of Sale → Selling's Lock a Register After at 1 minute, and a session open.

- [ ] Open POS: Lock covers the screen; the password opens it while there is no PIN.
- [ ] Set PIN: 4 to 6 digits, twice, with the password; the button reads Change PIN.
- [ ] Left alone a minute, the screen locks; a wrong PIN is refused, the right one opens it.
- [ ] While locked, nothing sells: a search brings the lock up, and runs again once it is open.
- [ ] Another cashier's register does not open with your PIN.

**Phase I2: a manager's approval at the register**

**Before you start:** POS on, a discount limit and a cash movement limit, a manager with a PIN, and a cashier who may not override prices, go beyond the limit, approve cash or refund by another method.

- [ ] A price typed over: Ask a Manager to Change the Price; a wrong PIN is refused; once approved, the price field shows, and the sale names the approver.
- [ ] A discount beyond the limit: Ask a Manager, then the reason under "Approved by …".
- [ ] A cash movement above the limit: Ask a Manager Now, and it moves at once.
- [ ] A return paid back by another method: approved as it is recorded.

**Phase I3: registered devices**

**Before you start:** POS on and a register.

- [ ] Registers → Devices: add a receipt printer and a barcode scanner.
- [ ] The scanner's Test reads a code; the printer's opens its test page.
- [ ] A receipt printed, and an item scanned, show as their last use.
- [ ] Switched off, a device has no Test; Remove takes it off.
- [ ] As a member who may view registers but not manage them: the list, without changes.

**Phase A1: eCommerce on, with its storefronts**

**Before you start:** eCommerce on (Premium, with Sales Lite).

- [ ] The Apps Center shows eCommerce, needing Sales Lite; its menu has Storefronts.
- [ ] Settings → eCommerce: untick guest checkout, show prices without tax and change the order prefix; save, and reload.
- [ ] Storefronts → New Storefront: a name, a code, the location, currencies, languages and countries; it is added as Draft.
- [ ] A code izSuite keeps (www), or another storefront's, is refused.
- [ ] Edit it: Live, and another default language; the row shows them.
- [ ] Deleting the location it sells from is refused; Delete removes the storefront.
- [ ] As a member who may view eCommerce alone: the list without New, Edit or Delete, and no Settings → eCommerce.

**Phase A2: the plans' storefronts**

**Before you start:** eCommerce on, on a plan with a storefront limit (Billing Starter has one).

- [ ] Admin → Packages → a plan: Storefronts per Business saved; the plan's row and its versions show it.
- [ ] Settings → Storage & Usage: Storefronts, against the limit.
- [ ] At the limit, New Storefront is refused, naming the plan.
- [ ] An archived storefront is not counted; opening it again needs room.
- [ ] Pricing: each plan with eCommerce says its storefronts.

**Phase A3: the storefronts' domains**

**Before you start:** eCommerce on, a storefront, and a domain whose DNS you can change.

- [ ] Storefronts → Domains: the izSuite address, as Primary.
- [ ] Add your domain, and give it the TXT and CNAME records shown.
- [ ] Verify before the records are in: refused, saying why. Once they are: Verified.
- [ ] Make it Primary; the izSuite address then offers Make Primary.
- [ ] Remove it: asked once more, then the izSuite address is Primary again.
- [ ] As a member who may view eCommerce alone: the domains, without changes.

**Phase B1: items published online**

**Before you start:** eCommerce on, items for sale (one without a sales price), and a storefront.

- [ ] Catalog: your items for sale, each Not online; an item only bought is not there.
- [ ] Open one: its listing starts from the item. A full description with formatting, keywords and Published: Put in the Catalog.
- [ ] Add pictures, and remove one. The list shows a picture when the item has no image of its own.
- [ ] Limited without a quantity is refused; with one, the list says how many are left.
- [ ] An item without a price, at its price and Published, is refused; by quotation it is saved.
- [ ] Scheduled from tomorrow: the list says Scheduled, and from when.
- [ ] Take Out of the Catalog: asked first, then Not online.
- [ ] As a member who may view the catalog alone: read only.

**Phase B2: categories, collections and attributes**

**Before you start:** eCommerce on, brands on in Settings → Items, two item categories, a brand, and three items in the catalog, two of them the brand's.

- [ ] Catalog → Categories: each item category, with how many of its items are in the catalog. Give one a link, a place in the menu, a description and a picture.
- [ ] Give another category the same link: refused.
- [ ] Catalog → Collections: a promotion with two products in your order, then a brand's, which counts the brand's listed products.
- [ ] A collection whose last day is before its first: refused.
- [ ] Rename a collection with its link left blank: its link stays.
- [ ] Delete a collection: asked first.
- [ ] An item's page: the attributes Material: Stoneware and Capacity: 350 ml saved; one without a value refused.
- [ ] Delete a brand that has a collection: the collection says its brand was deleted.
- [ ] As a member who may view the catalog alone: Categories and Collections without changes.

**Phase B3: options and variants**

**Before you start:** eCommerce on, and a T-shirt for sale in four sizes, each its own item of one type - S, M, L and XL, XL without a price - with S in the catalog.

- [ ] S's page → Options and Variants → Add an option: S becomes the first variant. Name the option Size, with S, M, L, XL.
- [ ] Add M and L as variants, each with its size, L Limited with 5 left: saved. The Selling card says each variant has its own availability.
- [ ] Two variants with the same size: refused.
- [ ] Catalog: M and L show as variants of the listing, which says "3 variants". M's page points to the listing.
- [ ] Add XL, then publish the listing: refused, as XL has no price. Hide XL: published.
- [ ] Remove the option: the variants go, and the Availability field is back.
- [ ] As a member who may view the catalog alone: the variants without changes.

**Phase C1: the storefront opens**

**Before you start:** eCommerce on, a live storefront and one in maintenance, two categories in the storefront's menu, a brand, and listings: one with pictures, one out of stock, one sold by quotation, one with variants, and a draft.

- [ ] Open `/store/{code}`: the store's name, menu, categories and newest products - not the draft.
- [ ] Search for a keyword: the products that match, with the search shown as a filter to remove.
- [ ] Narrow to a category and to in stock only; sort by price, low to high: those without a price come last.
- [ ] A product with variants: choosing one shows its price, stock and SKU.
- [ ] A product with pictures: choose another to see it large. A product sold by quotation offers the store's e-mail and phone.
- [ ] The store in maintenance says so; a made-up address says there is no such store.
- [ ] At phone width, the shop and a product's page fit.

**Phase C2: categories, collections and search suggestions**

**Before you start:** Phase C1's store, a category with a description and a picture, and three collections: one of products chosen in an order, one of a brand, and one hidden.

- [ ] The home shows the collections - not the hidden one.
- [ ] A collection's page: its products in its order (Featured), then sorted by price.
- [ ] A category from the menu: its page, with its description, picture and products.
- [ ] Type two letters or more in the search: products, categories and collections to choose from.
- [ ] The hidden collection's address: "There is no such collection in this store."

**Phase C3: filters by price, options and attributes**

**Before you start:** Phase C2's store, with a product in sizes and products with attributes.

- [ ] The shop offers a price range, each option's values and each attribute's values.
- [ ] A size, then an attribute, then a price range: only the products that pass, each filter shown as a chip.
- [ ] A category's page offers only what its products have.
- [ ] At phone width the filters fit.

**Phase C4a: the storefront's theme and branding**

**Before you start:** Phase C2's store, a wide picture for a logo and a square one for a favicon, and a member whose role may only view the catalog.

- [ ] eCommerce → Storefronts → Theme: "Not published yet: customers see the default theme."
- [ ] Upload the two pictures and choose them as the logo and the favicon.
- [ ] A social link starting http://: "Give the link starting https://."
- [ ] Change the colours, the font, the header, the cards and the footer, then Save Draft: "Draft saved." and "Draft not published".
- [ ] Preview: the store in the draft, under the preview's bar. Signed out, in another browser, the store as it was.
- [ ] Publish: "Theme published.", and the store in the theme for everyone.
- [ ] Change the font, then Discard Draft: the published font back.
- [ ] Preview a storefront not yet Live: "Its customers see it closed."
- [ ] As the member: no Theme on the Storefronts page.
- [ ] At phone width the Theme page and the themed store fit.

**Phase C4b: the home page's sections and banners**

**Before you start:** Phase C2's store, and a wide picture, at least 1600 by 600 pixels.

- [ ] eCommerce → Storefronts → Theme → Home Page: a banner, Collections, Categories and New in, as the home was.
- [ ] The banner: upload the picture and choose it; give a heading, a line, and a button to a collection.
- [ ] Add a Products section of a collection, 4 of them, and move it up to second. Add a Text section.
- [ ] Point a banner's button at A category, choose none, and Save Draft: "Choose where it goes."
- [ ] Save Draft, then Preview: the sections in their order, the banner over its picture. Signed out, the home as it was.
- [ ] Publish: the new home for everyone. Hide the collection: its section leaves, and the banner's button goes to the shop.
- [ ] At phone width the home and the Home Page card fit.

**Phase C5a: the storefront's pages**

**Before you start:** Phase C2's store, with its support e-mail and phone.

- [ ] eCommerce → Storefronts → Pages: six pages, each "Not written yet".
- [ ] Write the Privacy Policy with a heading and a subheading, and save it as a draft: "Page saved as a draft." Signed out, its link is not found; Preview shows it.
- [ ] Publish it: "Page saved and published.", and the footer links to it.
- [ ] Publish the contact page with no words: it shows the store's e-mail, phone, address and opening hours.
- [ ] Publish the About page with no words: "Write the page before publishing it."
- [ ] Untick "Linked from the footer" on a published page: its link still opens it, and the footer leaves it out.
- [ ] At phone width the editor and the store's pages fit.

**Phase C5b: pages of the business's own**

**Before you start:** Phase C2's store, the business on Billing Starter.

- [ ] eCommerce → Storefronts → Pages: "Pages of Your Own", 0 of 3.
- [ ] New Page: a title, words and Published, then Add Page: "Page added.", its link made from its title.
- [ ] Set its link to privacy-policy: "This link is kept for one of its standard pages: choose another."
- [ ] Add two more: New Page is gone, and the list says all three are in use.
- [ ] Signed out, the footer links to them after the standard pages, by their places.
- [ ] Delete one: "Page deleted.", and New Page is back.
- [ ] Admin → Plans: Pages per Storefront, saved on a plan and shown in its list.

**Phase C6a: the storefront's SEO**

**Before you start:** Phase C2's store, a picture for the mug, and a wide picture (1200 by 630) to share the store by.

- [ ] Theme → Search and Sharing: a title, a description and the sharing picture. Save Draft, then Preview: the tab shows the draft's title.
- [ ] Publish. Signed out, view the home's source: its title, description, canonical link, og: tags with the picture, and robots "index, follow".
- [ ] A product's source: its Product data (application/ld+json), with its price.
- [ ] Search the shop: robots "noindex, follow", and the canonical link is the shop's.
- [ ] /store/{code}/sitemap.xml lists the home, shop, categories, collections, products and published pages.
- [ ] Untick "Let search engines list the store" and publish: every page is "noindex", and the sitemap is empty.

**Phase C6b: the cookie notice**

**Before you start:** Phase C2's store, opened in a private window.

- [ ] Settings → eCommerce: Cookie Notice, on, with izSuite's words as the placeholder. Give words of your own and save.
- [ ] The store: the notice at its foot, with those words.
- [ ] Publish the Privacy Policy page: the notice links to it.
- [ ] Only Essential: the notice goes, and stays gone on the store's other pages.
- [ ] Switch the notice off: a new visitor sees none.
- [ ] At phone width the notice fits.

**Phase D1: shipping zones and methods, and pickup**

**Before you start:** eCommerce on; a member with Manage Shipping alone, and one with eCommerce Settings alone. Its main screens were driven in a headless browser after Phase D5; the rest are tested here.

- [ ] Settings → eCommerce shows Shipping and Pickup under the general settings. Shipping says "No zones yet".
- [ ] Add Zone "Inside Dhaka": Bangladesh, the city Dhaka, postal codes 12* and 1000. Add "Bangladesh" after it. Each row shows its place and where it covers.
- [ ] A second zone of the same name is refused, and a postal code *12 is refused under its field.
- [ ] Add Method to Inside Dhaka: Local Delivery at 2.50, delivered in 0 to 1 days. The row shows its price and "0-1 days".
- [ ] Add a method by weight to Bangladesh with two rates, the higher one first: they are saved from the lowest. Edit it: the rates come back as saved.
- [ ] A method by quantity with no rate, or a lightest weight above the heaviest, is refused under its field.
- [ ] With taxes on, the Tax field lists the sales tax rates that are on.
- [ ] Carries: choose a category. The row says "1 category".
- [ ] Switch a method off: it shows Off. Delete a zone: its methods go with it.
- [ ] Pickup: Edit Main Location, switch it on with instructions and "Ready in 2 hours": "Pickup saved." and Offered.
- [ ] Make a location inactive: it leaves the list unless it is still offered, and switching it on is refused.
- [ ] As the member with Manage Shipping alone: Shipping and Pickup, without the general settings.
- [ ] As the member with eCommerce Settings alone: no Shipping or Pickup.
- [ ] At phone width the cards and the method's dialog fit.
- [ ] The Activity Log names each change.

**Phase D2: payment methods without a gateway**

**Before you start:** eCommerce on; two payment accounts taking customer payments, one in another currency; a member with Manage Payment Methods alone. Its dialog was driven in a headless browser after Phase D5; the rest is tested here.

- [ ] Settings → eCommerce shows Payment Methods: "None yet".
- [ ] Add a bank transfer without instructions: refused under Instructions. With them, for Bangladesh, up to 5,000: "Payment method added.". The row shows its account and currency, "Shipped or picked up · Bangladesh · up to …" and Optional.
- [ ] Add bKash as mobile money: its reference turns Required.
- [ ] Add Cash on Delivery: the form has no reference or delivery fields, and the row says Shipped only. Pay on pickup says Picked up only.
- [ ] Two methods cannot share a name, and amounts from 50 to 10 are refused.
- [ ] Switch a method's account off in Payment Accounts: the method says it cannot be used. Switch a method off: it shows Off.
- [ ] Delete a method through its confirmation.
- [ ] As the member with Manage Payment Methods alone: the Payment Methods card alone.
- [ ] At phone width the card and its dialog fit. The Activity Log names each change.

**Phase D3: the cart**

**Before you start:** a live storefront with a product sold at its price, one with variants (one out of stock, one hidden, one Limited with 3 left), and one sold by quotation. Open the store in a private window.

- [ ] The product's page has Add to Cart; the quotation's has "Ask us for a quotation" instead. The header shows a cart.
- [ ] Add 2: "Added to your cart. View your cart", and the header counts 2. Add 2 more: the cart shows 4 on one line.
- [ ] The product with variants: options with no variant say they are not sold; the one out of stock cannot be added; 4 of the Limited one: "Only 3 left."
- [ ] The cart page: each line's picture, options, price and amount; − and + change the quantity; Remove takes it out. The totals change with them.
- [ ] Change a product's price in Items, then reload the cart: the new price, "was" the old one; reload again: the "was" is gone.
- [ ] Lower the Limited product's quantity in the catalog below the cart's: its line says how many are left, is left out of the total, and Checkout is greyed out until it is lowered.
- [ ] Take a product out of the catalog: its line says it is no longer sold here.
- [ ] With taxes on and prices shown with tax: "Includes … tax." under the total. Shown without tax: a Tax line.
- [ ] Settings → eCommerce: Keep Carts For 0 is refused; save 14.
- [ ] With full Sales: give the storefront a price list with a quantity break. The product's page shows the list's price, and the cart takes the break.
- [ ] Close the private window and open the store again: the cart is still there. Another browser has its own.
- [ ] At phone width the header, the product's Add to Cart and the cart page fit.

**Phase D4: checkout and the order**

**Before you start:** Phase D1's shipping (a zone with a flat rate and a quoted method), pickup at one location, and Phase D2's payment methods (a bank transfer, mobile money with its reference required, cash on delivery and pay on pickup); a live storefront with a physical product and a digital one. Open the store, through a link with `?utm_source=newsletter`, in a private window.

- [ ] Cart → Checkout: Your Details, Addresses, Delivery, Payment, and Review and Place beside them. The store's single country is chosen for the addresses.
- [ ] Before an address, Delivery offers only pickup. Give a city in the zone: its methods appear with their prices and days; the quoted one says "Quoted later".
- [ ] Choose a method: the total gains it. Choose pickup: the delivery is free, and Pay on pickup replaces Cash on delivery.
- [ ] Untick "Deliver to this address": an address of its own, among the store's countries. A billing address abroad, delivered there, is refused under its country.
- [ ] Mobile money: Place Order without its reference is refused under it. A bank transfer: its instructions show, and the reference is optional.
- [ ] Place Order without accepting the terms: refused under the box. With the terms and privacy pages published, the box links to them.
- [ ] Place it, with marketing ticked and a note: "Thank you for your order", its number (EC-00001), how to pay, how it reaches you, what was ordered.
- [ ] The header's cart is empty. Go back and press Place Order again in the browser's history: no second order.
- [ ] In Contacts: a new customer with the details and address given, its note naming the order; its Marketing Consent card (with CRM on) shows Promotions and Offers by e-mail, from the website form.
- [ ] A second order with the same e-mail in other letters: the same contact. A contact you add yourself with an e-mail is never taken by a guest's order.
- [ ] A digital product alone: no Delivery step, and only the ways to pay for either delivery.
- [ ] Change a product's price between the review and Place Order: "The total is now …: review your order, then place it again."
- [ ] Settings → eCommerce: Phone at Checkout Optional, then Not asked; untick notes. The checkout follows. Guest checkout off: Place Order is greyed out, saying the store takes orders from signed-in customers only.
- [ ] Deleting the storefront, the order's contact or its item is refused; Take Out of the Catalog archives the listing instead.
- [ ] The Activity Log shows the order placed, the contact added and the consent, by no user.
- [ ] At phone width the checkout's steps stack, the review last, and the confirmation fits.

**Phase D5: the confirmation**

**Before you start:** Phase D4's set-up, the queue worker running, and a support e-mail and phone on the storefront. Dev mail goes to `storage/logs/laravel.log`.

- [ ] Place an order: the confirmation links to "see your order". Open it: Order EC-…, placed today, Received, "Payment: not paid yet", "Delivery: not sent yet", how to pay, and what was ordered.
- [ ] The log holds the e-mail: from the store's name, replies to its support e-mail, "Your order … at …", what was ordered, the total, how to pay and the button to the order's page. Its plain-text part reads without HTML codes.
- [ ] Open the order's link in another browser, signed out: it opens. Change one character of its key: "There is no such order."
- [ ] Put the store in Maintenance: the order's page still opens.
- [ ] Pay by mobile money with a reference: the page and the e-mail say the payment will be matched to it.
- [ ] Pick up an order: the page and the e-mail name the location, its instructions and when it is ready.
- [ ] At phone width the order's page fits.

**Phase E1: the customer's account**

**Before you start:** a live storefront with a support e-mail, and Settings → eCommerce's Verify Customer E-mail on. Dev mail goes to `storage/logs/laravel.log`: the codes are there. Open the store in a private window. Its main flows were driven in a headless browser; the rest are to try.

- [ ] The header shows Sign in beside the cart. Sign in → Make an account.
- [ ] Sign up with the two passwords differing, then with a password without a symbol: each refused. Then your name, your e-mail in capitals and a phone: the verify page, "We sent a 6-digit code to …" in small letters, "A new code in 60s".
- [ ] The log holds the e-mail: from the store's name, replies to its support e-mail, "Your code to verify your e-mail at …", the code, "It holds for 30 minutes." Its plain-text part reads without HTML codes.
- [ ] A wrong code: "That code is not right." and the boxes empty. The right one: Your Account, with your name, e-mail and "A customer since" today. The header's icon is now Your account.
- [ ] In another window, sign up with the same e-mail: "An account uses this e-mail already".
- [ ] Change your name and clear the phone; Save: "Your details are saved." The e-mail cannot be changed.
- [ ] A new password with a wrong current one: refused. With the right one: saved, and another browser signed in to the account is signed out.
- [ ] Sign out: the store's home, the header says Sign in. Open the account page: it asks you to sign in, then brings you back.
- [ ] A wrong password five times: the sixth try waits ("Too many tries: try again in … seconds.").
- [ ] Forgot your password? → your e-mail → "Choose a new password": the code from the log and a new password sign you in. The old password no longer works.
- [ ] A forgotten password for an e-mail with no account: the same answer, and no e-mail in the log.
- [ ] Sign up a second account and leave before verifying. Sign in with it later: the verify page; its code signs you in.
- [ ] Settings → eCommerce: Verify Customer E-mail off. A new sign-up signs in at once; its account page says the e-mail is not verified yet, with Verify it.
- [ ] Close your account with a wrong password: refused. With the right one: the store's home, signed out, and signing in with it is refused. Sign up again with its e-mail: it opens again, to be verified.
- [ ] A second storefront of the business signs the same account in; another business's store does not know it.
- [ ] The Activity Log shows the sign-ups and the closing, by no user. Settings → Users & Roles has no new user.
- [ ] At phone width the sign-in, sign-up, verify and account pages fit.

**Phase E2: the account's contact, orders and addresses**

**Before you start:** Phase E1's set-up, with Phase D4's shipping and payment methods. Its main flows were driven in a headless browser; the rest are to try.

- [ ] As a guest, place an order with an e-mail. Then sign up with that e-mail and verify it: Orders lists the guest order, and in Contacts the account's link is in the order's contact's Activity.
- [ ] A contact of your own (a customer) with another e-mail, then a sign-up with it, verified: linked to your contact. Two customers with one e-mail: a new contact instead. A supplier alone: a new contact too.
- [ ] Signed in, Checkout: your name, phone and e-mail filled - the e-mail greyed - and your addresses for billing and delivery. "Your addresses" fills either address from another of yours.
- [ ] Place it: the order is in Orders, with Received, "Payment: not paid yet", "Delivery: not sent yet", its products and total; open it.
- [ ] Order to a new address: Addresses keeps it. The same address in other letters or spaces: not kept twice.
- [ ] Addresses: add one, tick "Use it for delivery": it takes that over. Change one; delete one - asked first. The 21st cannot be added.
- [ ] Settings → eCommerce, guest checkout off. Signed out, Checkout: "This store takes orders from its signed-in customers.", Place Order greyed. Sign in from it: back at checkout, and the order is placed.
- [ ] Guests again, signed out: "Have an account? Sign in" at checkout.
- [ ] Close the account: its addresses go. With Verify Customer E-mail off, sign up again with its e-mail: no orders until it is verified, then they come back.
- [ ] At phone width the account's tabs, orders, an order and the addresses fit, and so does checkout signed in.

**Phase E3: what a signed-in customer is offered**

**Before you start:** Phase E2's set-up, with the store in two languages and a second product whose catalog page says Who Sees It: Signed-in customers. For the price lists, the full Sales app and a price list (Sales → Price Lists) for one customer contact only, with a quantity break. Its main flows were driven in a headless browser; the rest are to try.

- [ ] Signed out: the product kept for customers is not in the shop, the search's suggestions, a category's page or the filters, and its link says "There is no such product in this store." The sitemap does not list it.
- [ ] Sign up with the e-mail of the contact with the price list and verify it: the shop shows the product kept for customers, and the contact's prices on the shop, the product's page and the price filter. Its page's source says noindex.
- [ ] Add the quantity of the list's break: the cart prices each at the break, and so does checkout. Place the order.
- [ ] Add the product kept for customers to the cart, then sign out: the cart says it is no longer sold here, and checkout waits until it is removed.
- [ ] Sign out and in again from the header, without reloading the page: the shop changes with it.
- [ ] Your details: Language lists the store's languages; choose one and save. In Contacts, the contact's Preferred Language is it.
- [ ] Tick "Send me news and offers by e-mail" and save: the contact's Marketing Consent shows Promotions and Offers given by e-mail, from the website form, "Store account on …", and its Activity says so. Untick it: withdrawn, with its note. Save again unchanged: nothing new is recorded.
- [ ] Checkout while it is given: "You get our news and offers by e-mail. Change it in your account." instead of the box, its link opening the account.
- [ ] Settings → eCommerce, Verify Customer E-mail off: a new account's details say marketing waits for the e-mail to be verified. Choose a language, then verify the e-mail with its code: the contact made has that language.
- [ ] A second storefront of the business without that language: the account's details still show it, and save.
- [ ] At phone width the account's details fit.

**Phase F1: the Orders page and an order's page**

**Before you start:** Phase D's set-up with a few orders placed - one shipped with a payment reference, a note and a link with `?utm_source=…`, one picked up, one of a digital product alone, one placed signed in - and two storefronts if you can. A member whose role has Orders: View Orders at all locations, and one with it at another location only. Its main flows were driven in a headless browser; the rest are to try.

- [ ] eCommerce's menu shows Orders after Catalog. A member without View Orders does not see it, and `/ecommerce/orders` refuses them.
- [ ] Orders lists them newest first: number, when, customer and e-mail, total and how many products, and the three statuses, with how each is delivered. With two storefronts, each row says its storefront and a Storefront filter shows.
- [ ] Each filter (order status, payment, fulfilment, storefront) and the search (a number, a name, an e-mail in other letters) narrows the list; "No orders match these filters." when none does. Over 15 orders, the pager.
- [ ] An order's page: its three statuses apart; the customer as given and "A guest" or "A customer signed in to their account"; its contact, opening the contact for a member who may view contacts, and plain text for one who may not; both addresses (only billing when nothing is shipped); the delivery (a shipping method's price and days, "Price quoted after the order", or the pickup point and when it is ready); the payment method and the customer's reference; the products, with a price list's name under a price that came from one; the totals with each tax; the note, where the visit came from, the terms and the marketing asked for; the order-received e-mail with whether it went.
- [ ] Its Timeline: "Online order … placed on … by …", by The customer (orders placed before Phase F1 say izSuite).
- [ ] The member at another location: no orders, and an order's address says "There is no such order."
- [ ] Other records' timelines (an invoice, an expense, a lead) still read as before: the list on the left, not in the middle of the card with a line through it.
- [ ] At phone width the Orders page (the filters one under another) and an order's page fit.

**Phase F2: confirming an order - its invoice**

**Before you start:** Phase F1's set-up, with one product listed as Limited (say 10 left), taxes on with a rate on a product and on the shipping if you can, and a few pending orders: one shipped and paid by bank transfer with a reference, one by cash on delivery, one placed signed in. A member whose role has Orders: Confirm Orders, and one with View Orders alone. Its main flows were driven in a headless browser; the rest are to try.

- [ ] A pending order's page shows Confirm Order; the member with View Orders alone does not see it, and a confirmed order shows it no more.
- [ ] Confirm Order's dialog: the invoice it issues - dated today, to the order's contact, for the order's total - the e-mail to the customer, and each Limited product counted down ("10 left, then 8").
- [ ] Change the product's price in Items after the order: the dialog says what it sells at now and that the order keeps its price; the invoice has the order's price.
- [ ] Confirm: "Order … confirmed. Invoice … issued."; the order Confirmed, "Confirmed … by …" and the invoice's link; its payment status as it was (Pending with a reference, Unpaid for cash on delivery); the timeline's entry, and Order confirmed among its e-mails.
- [ ] The invoice in Sales → Invoices: issued, numbered as the business's invoices are, today's date, the order's products and its "Shipping: …" line with their taxes, Your Reference the order's number, the billing address as the order gives it; its own timeline "Issued invoice … for online order …". The Dashboard and Reports count it once.
- [ ] Set what is left below what an order holds, make its item inactive, or make its contact inactive: the dialog says why, and Confirm Order waits. Out of Stock set by hand, or the store in Maintenance, stops nothing.
- [ ] The customer's e-mail (in `storage/logs/laravel.log` on dev): "Your order … at … is confirmed", its invoice's number and See your invoice, which opens the invoice.
- [ ] The order's own page from the first e-mail, and the account's page of an order placed signed in: "Your invoice, …: see or download it". Revoke the invoice's link in Sales: the line goes.
- [ ] At phone width the dialog and a confirmed order's page fit.

**Phase F3: an order's payment received or failed**

**Before you start:** Phase F2's set-up, with a few pending orders - by bank transfer with a reference, by mobile money, by cash on delivery and by paying on pickup - and one confirmed. A member whose role has Confirm Orders, with and without Sales → Payments → View, and one with View Orders alone. Its main flows were driven in a headless browser; the rest are to try.

- [ ] An order owing something shows Record Payment, and Payment Not Received while nothing is paid; the member with View Orders alone sees neither.
- [ ] Record Payment on a pending order: the dialog says it confirms the order first, with the invoice, the count-down and any price changed since; recording it confirms the order and pays its invoice. An inactive item stops both.
- [ ] Record Payment on a confirmed order: the account its method takes money into, what it owes, today's date and the customer's reference to start. Record part of it: Partially Paid, the Payments card with what is left; more than is left is refused under the amount; the rest: Paid, and no more actions.
- [ ] The receipt in Sales → Payments Received: numbered as receipts are, into the method's account (Cash Box's for cash on delivery), by bank transfer, mobile money or cash, with the reference; the account's history has it.
- [ ] Payment Not Received: refused without why; with it, Failed, counted on the Payments card with the reason, in the timeline. Twice: "Marked failed 2 times".
- [ ] The customer's e-mails (in `storage/logs/laravel.log` on dev): "Payment received for your order …" with the amount, reference and what is left; "Your payment for order … was not received" with how to pay and no reason.
- [ ] Record a payment of the order's invoice in Sales instead: the order is Partially Paid or Paid all the same (no e-mail). Reverse it in Sales: the order is Unpaid again, its payment struck through as Reversed.
- [ ] Make the method's account inactive: Record Payment says to record it in Sales.
- [ ] Settings → eCommerce → Payment Methods lists each method's type, and Add Method's Type offers all four (broken from F1 to F3).
- [ ] The customer's order page by its link, and on their account: the payment's status in words (partly paid, paid, failed).
- [ ] At phone width the dialogs and the Payments card fit.

**Phase F4: holding, releasing and cancelling an order**

**Before you start:** Phase F3's set-up, with a few orders - pending, confirmed with a Limited product, one part-paid, one by pickup - and two or three from one e-mail on one day. Members whose roles have Place Orders On Hold alone, Cancel Orders alone, and neither. Its main flows were driven in a headless browser; the rest are to try.

- [ ] The order's More menu offers Put On Hold and Cancel Order only to roles that have them; a held order offers Release instead of its other actions.
- [ ] Put On Hold: refused without why; then On Hold, its notice with why and where it goes back to, its timeline entry; Confirm Order and Record Payment are gone, and the API refuses them. Release: back to Pending, or Confirmed when it was.
- [ ] An order being fulfilled (a later phase; set its fulfilment by hand to try) is not held, and one shipped or delivered is not cancelled.
- [ ] Cancel a pending order: Cancelled, nothing given back, no invoice. Cancel a confirmed one: its Limited quantities given back in the Catalog, its invoice void in Sales with its link gone, the Payments card saying so.
- [ ] Cancel one with a payment: refused, saying to refund through a credit note or reverse it in Sales; reverse it in Sales, then it cancels.
- [ ] The customer's e-mail (in `storage/logs/laravel.log` on dev): "Your order … is cancelled", no reason, no link; "If you have paid for it, we will be in touch about your money." when they gave a reference.
- [ ] Worth a look on an order's page: different billing and delivery countries, a total over Review Orders Above, two failed payments, an e-mail's third order in a day - and never on the customer's pages or in an e-mail.
- [ ] Settings → eCommerce: Review Orders Above saved and cleared; with holding on, an order over it is On Hold as placed, its reason in the timeline by izSuite; with it off, it is not.
- [ ] The customer's page of a held order says On hold; of a cancelled one, Cancelled alone, without how to pay; of a paid one, no "We will match your payment".
- [ ] At phone width the actions, the More menu, the notices and the dialog fit.

**Phase F5a: an order's customer, addresses and delivery edited**

**Before you start:** Phase F4's set-up, with a second shipping method (say Express) in a zone, one priced after the order, the business's location a pickup point, taxes on with a rate on the shipping if you can, and a few orders: pending, confirmed with its invoice, on hold, part-paid, by cash on delivery, and one with nothing to deliver. A member whose role has Edit Orders, and one with Confirm Orders alone. Its main flows were driven in a headless browser; the rest are to try.

- [ ] The order's More menu offers Edit Customer and Edit Addresses and Delivery only to roles with Edit Orders, and only while nothing is paid or fulfilled and the order is not cancelled. The member with Confirm Orders alone confirms as before.
- [ ] Edit Customer: the name, e-mail, phone and company saved, and the timeline's entry with each change ("Phone: … → …"); the contact in Contacts as it was; the order's next e-mail (mark a payment failed, say) goes to the new address.
- [ ] Edit Addresses and Delivery: as the address changes, the methods offered there with their prices and days, and the pickup points; a country the store does not sell to says so, and saving it is refused.
- [ ] A confirmed order moved to another method: its shipping at the new method's price now, the totals and taxes worked out again; its invoice in Sales with the same number and date, the new shipping line, total and amount owed, and the new address on it.
- [ ] A method priced after the order: no shipping line, "Price quoted after the order". Picked up: the pickup point and no shipping address.
- [ ] Taxes on the shipping: its tax and the order's taxes worked out again, its prices shown with tax or without as the order shows them.
- [ ] Its own shipping address (untick "Deliver to the billing address"): the methods follow that address's country, not the billing one's.
- [ ] Cash on delivery changed to a pickup, or a payment method kept to some countries moved to another: refused, saying to keep them or cancel the order.
- [ ] An order on hold is edited and stays on hold. A part-paid, cancelled or fulfilled one offers no edits, and the API refuses them ("… can no longer be edited: …").
- [ ] The timeline: one entry an edit, its changes folded under "N changes". A hold or a cancelling with a reason near 500 characters saves, its timeline words cut with "…".
- [ ] At phone width both dialogs fit.

**Phase F5b: an order's products and discount edited**

**Before you start:** Phase F5a's set-up, with a few products listed - one Limited, one with variants, one for signed-in customers only, one by quotation, a digital one - taxes on with the store showing prices with tax if you can, a shipping method priced by quantity or weight, a payment method with a most it takes, and a few orders: pending, confirmed, one placed signed in with the customer's own price list, one with nothing to deliver. A member whose role has Edit Orders, and one with Confirm Orders alone. Its main flows were driven in a headless browser; the rest are to try.

- [ ] The order's More menu offers Edit Products and Give a Discount (Change Discount once it has one) only to roles with Edit Orders, while nothing is paid or fulfilled and the order is not cancelled.
- [ ] Edit Products: each product with its quantity and Remove - not for the last one; Add a Product lists what the store sells the customer now, searched as you type, each variant on its own, with its price and what is left; one picked twice is one more.
- [ ] Saved on a confirmed order: an added product at its price now, the others at the order's price; the Limited listing counted down for more and given back for fewer in the Catalog; the invoice in Sales with the same number and the new lines and total; the timeline's "Changed the products of …" with each change.
- [ ] On a pending order nothing is counted down until it is confirmed, and more than is left is refused either way.
- [ ] Refused: a draft, quotation-only or out-of-stock listing; for a guest, one for signed-in customers; more of an inactive item; a delivered product on an order with nothing to deliver; taking off the last product to deliver of a shipped order.
- [ ] An order placed signed in: an added product at the customer's own price list's price, and products for signed-in customers offered.
- [ ] The shipping by quantity or weight re-priced as products change; a method that would no longer take them refuses them; one quoted after the order stays quoted.
- [ ] Give a Discount: a percentage of the products, an amount no more than they come to, or none - why required each time; the order's page shows "Discount (10%)" and why; a percentage follows the products as they change.
- [ ] With taxes and prices shown with tax: the lines show as before the discount, the discount with its tax, and the order adds up; the invoice in Sales takes the discount off before tax, to the same total.
- [ ] The customer's order page and the order's next e-mail (in `storage/logs/laravel.log` on dev) show the discount, never why.
- [ ] A payment method's most: products or a discount that would take the total past it are refused, saying so.
- [ ] At phone width both dialogs fit.

**Items: an item's attachments**

**Before you start:** a physical product, a digital one and a service, and a member whose role has View Items but not Edit Items. Its main flows were checked in the browser; the rest are to try.

- [ ] Add Item: the Attachments card at the foot of the form takes files picked or dropped on it - PDFs, pictures, Office documents, CSV or text files and ZIP archives, up to 10 MB each and ten at a time - and lists them with their sizes; Remove takes one off before saving.
- [ ] Saved: the item's page says it was added, and its Attachments card lists the files; each downloads, and Remove deletes it after asking.
- [ ] A file attachments do not take (an .exe dropped on the card, say): the item is still added, and its page says "The attachments were not uploaded: …" - they can be added from there.
- [ ] Edit Item: the Attachments card uploads and removes files at once, and Save Changes leaves them as they are.
- [ ] A digital product: its Digital Files and its Attachments are kept apart, neither listing the other's.
- [ ] The member without Edit Items sees and downloads the files, with no Upload or Remove.
- [ ] Deleting an item never used deletes its attachments, and Settings → Storage & Usage goes down by their size.

**Phase F6a: payment links sent, and their page**

**Before you start:** a live storefront with a bank transfer and a mobile money method (each with instructions) and cash on delivery; a few orders - pending, confirmed, part-paid, one with nothing to deliver, one placed in another currency if the store has one - and members whose roles have Confirm Orders, and View Orders alone. Its main flows were driven in a headless browser; the rest are to try.

- [ ] The order's More menu offers Send Payment Link only to roles with Confirm Orders, while the order owes something and is neither cancelled nor on hold.
- [ ] The dialog: all the order owes, not to be changed while paying in part is off; the days from Settings → eCommerce and the day it works until; the store's bank transfer and mobile money methods for the order's currency, country and total, its own first - never cash on delivery or paying on pickup.
- [ ] No method paid from afar for an order (outside their countries or amounts, or none in its currency): the dialog says to add one, and Send Link waits.
- [ ] Sent: the e-mail (in `storage/logs/laravel.log` on dev) with what it asks, until when, the methods and the link; the Payment Links card and the timeline.
- [ ] The link's page signed out, and in another browser: what it asks and until when, each method's instructions, the store's contact, the order. A made-up token: "There is no such payment link …".
- [ ] Paying in part on: less than is owed is sent, more refused. A second link replaces the first, whose page says it is no longer in use.
- [ ] A part-paid order: its link's page asks what is left, never more.
- [ ] The order paid in full - on its page, or in Sales: the link Paid, its page "Order … is paid", and no new link offered.
- [ ] The order cancelled, or its total changed by an edit (products, discount or delivery): the link revoked, saying why. An edit that leaves the total (the customer's phone) leaves the link.
- [ ] Revoke, asked first: revoked by hand, in the timeline, its page no longer offering to pay.
- [ ] A link past its last day (set its `expires_at` back in the database): Expired on the card, "This link has expired" on its page.
- [ ] A method made inactive after the link was sent is no longer offered on its page.
- [ ] The member with View Orders alone sees the card, without Revoke or Send Payment Link.
- [ ] At phone width the dialog, the card and the link's page fit.

**Phase F6b: a payment link's customer saying they paid**

**Before you start:** Phase F6a's set-up, with one method paid from afar whose reference is required, one optional and one not asked (Settings → eCommerce → Payment Methods), and links sent for a pending order, a confirmed one and a part-paid one. A member whose role has Confirm Orders. Its main flows were driven in a headless browser; the rest are to try.

- [ ] The link's page: each way to pay as a choice with its instructions; the reference asked as the chosen way asks it - required, optional, or not at all; I Have Paid waits for a choice.
- [ ] A required reference left out is refused under it; told once, the page says what they told, and again on reloading or in another browser.
- [ ] The order's page: Payment Pending (Partially Paid stays so), the link Reported with what they told; no Revoke, no Send Payment Link, no edits in the More menu - and the API refuses each, saying why; the timeline names the customer.
- [ ] Record Payment starts from the report: the amount the link asks (no more than is owed), the reference, and the account of the way they paid - a mobile money report going into that method's account, not the order's own method's. Recorded, the link is Paid and keeps the report; the customer is e-mailed the payment received.
- [ ] Payment Not Received clears the report: the link Active again, the customer e-mailed, and the link's page taking another report. On a part-paid order too, which stays Partially Paid.
- [ ] Expired, revoked or paid links refuse a report, each saying why; a cancelled order's link reads as no longer in use.
- [ ] A way to pay made inactive after the report: Record Payment still uses its account while that account takes customer payments; an account made inactive stops it, pointing to Sales.
- [ ] The customer's order page after a report: "We will match your payment, reference …".
- [ ] At phone width the link's form, what was told, and the order's card fit.

## How we work

Each document goes the same way:

1. **"Start Doc N":** read the doc, compare it with the code, and list what is built and what is missing.
2. Split it into phases (A, B, C...) with suggested defaults written down. Saying "Start Phase X" accepts them. Say so if you want something different.
3. Build the phase: backend, frontend and tests, following `CLAUDE.md`.
4. Check it: the test suite, then in the browser, including as a limited member when roles or locations are involved. Remove any test data from the dev database.
5. Update this file and report back, adding the phase's manual tests to the [Testing checklist](#testing-checklist).
6. **"Commit and push":** only when asked, never before.

### Rules to keep

- **Commit and push only when asked.** Fetch first. Use the same subject in both repos, such as `Doc 12 Phase D: sensitive values`, with short bullets in the body. Push admin to `origin main` and frontend to `origin nuxt`.
- **Pull before starting.** Another developer (shuvo) also pushes to these repos, and you may have worked from the other PC.
- **Never run tests in the live `izsuite-api` container.** Running them there once wiped the dev database. Use only this throwaway container, which needs the `izsuite-api` container to exist (add `--filter 'A|B'` for a subset):

  ```bash
  MSYS_NO_PATHCONV=1 docker run --rm --network none --volumes-from izsuite-api -w /app -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: --entrypoint php izsuite-api-app:dev vendor/bin/phpunit
  ```

- **Back up the dev database before writing a migration.** Migrations run by themselves every time the app container starts, so a new file hits the database on the next restart.

  ```bash
  docker exec izsuite-mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --no-tablespaces "$MYSQL_DATABASE"' > ../izsuite-dev-before-doc-12-phase-d.sql
  ```

- **No passwords or keys in chat or in files.** Test logins are in `database/seeders/UserSeeder.php`. Since Doc 15 Phase A1 the admin also asks for an authenticator app's code. After a browser check, reset the seeded admin's two-factor columns to null so its owner sets up their own.

### Things that catch you out

- After changing jobs or listeners: `docker exec izsuite-api php artisan queue:restart`. Web requests pick up PHP changes by themselves.
- A new frontend component can show up as an unknown tag, with no error. `docker restart izsuite-frontend` fixes it. The same restart is needed after changing `nuxt.config.ts` or removing a component's `<style>` block.
- Install npm packages inside the container: `docker compose exec -T frontend npm install ...`.
- The frontend has no `vue-tsc` or `eslint`, and Pint is not installed despite `pint.json`. So the browser check is what catches frontend mistakes.
- Dev mail is `MAIL_MAILER=log`, so invitation links are in `/app/storage/logs/laravel.log`.
- Blade: a one-line `@php(...)` in a view that also has a `@php ... @endphp` block further down is read as the start of that block, and everything between them is printed raw. Use the block form in such a view - and keep the word out of its comments, which Blade reads before it strips them.
- A support session's link works for a minute. Right after `docker restart izsuite-frontend`, the dev app can take longer than that to build the page it opens, so the first link expires; enter again. In the Claude browser pane, Enter in the App's new tab does not open: open the link (`/admin/support-sessions/{id}/open`) in a tab of its own.
- Nuxt: in a layout, `useRoute()` moves to a new page only once that page has rendered. Where the layout itself decides whether the page renders (the dashboard's status panel), read `useRouter().currentRoute` instead.
- Eloquent's strict mode is off, so a model loaded with a column list (`with('workspace:id,name')`) reads every other column as null, with no error. Load whole models for anything handed on to another service: Doc 13's billing reads a business's `package_id`, for one. Likewise an invoice's `source` (its sales order) comes with a few columns, so its total read as null and every deposit looked over budget until `SalesOrderDeposits` read the total afresh (Doc 16 Phase D2).
- An admin form shows its message, then reloads the page 300 ms later, which takes the message with it. Where the page does not change (Resend Invoice), give the form `data-no-reload` and the message stays.
- The Subscription Invoices buttons ask with the browser's own `confirm()`, which the Claude browser pane cannot answer. Confirm a transfer there with `SubscriptionInvoiceService::confirm()` in tinker instead.
- Billing's notices in the bell go to the business's members with Manage Subscription (`NotificationAudience`). In a test, set the business up with `SetUpNewWorkspace`, or its owner is no member and nothing is sent.
- The app reads a business's `setup_completed` from its cached navigation. After changing `setup_completed_at` by hand, call `WorkspaceRepository::bumpNavVersion()`, or the app keeps sending you to Set Up. Change it with a query (`Workspace::whereKey(1)->update(...)`): it is not fillable, so `$workspace->update()` leaves it as it was, without a word.
- In the Claude browser pane, navigating to the same address with only a different `#part` does not reload the page: reload it, or a check reads the page as it was.
- `sed` in Git Bash here does not match a backslash (`Tests\TestCase`) in its pattern; change such lines with PowerShell's `.Replace()` or the edit tool.
- If `docker` cannot reach its engine (`dockerDesktopLinuxEngine` not found), Docker Desktop has stopped. On the second PC it is installed per user, under `%LOCALAPPDATA%\Programs\DockerDesktop`. Starting it brings the containers back by themselves, but the admin's sign-in is lost with Redis.
- A model attribute cast to an enum (a sales document's `status`) matches nothing in a loaded collection's `->where('status', ...)` or `->whereIn('status', [...->value])`: compare the enum itself (`in_array($document->status, [...], true)`). Queries compare as strings and are fine.
- The dev MySQL caches `information_schema` table figures for a day, AUTO_INCREMENT included. To read them as they are, start with `SET SESSION information_schema_stats_expiry = 0`, or take them from the phase's backup.
- A full-only Sales page needs the dev business on full Sales, which its plan (Billing Starter) does not allow from the Apps Center. Point its `workspace_apps` Sales row at the full app (`app_id` 2) with a query and bump `nav_version`; put back `app_id` 1 afterwards.
- While the Claude desktop window is minimized, the browser pane cannot be clicked or screenshotted. `form_input`, `get_page_text` and `javascript_tool` still work: fill fields with `form_input`, and click buttons from a script. An ActionMenu item needs a `mousedown` before its `click`.
- MySQL refuses a name over 64 characters, and Laravel's own name for an index over two long columns can pass that; the tests' SQLite does not mind, so only the dev database finds it. A migration that fails there leaves the tables it made before failing, as MySQL does not roll back a table's creation. Name such an index yourself (`'sales_delivery_lines_line_unique'`), and drop the half-made tables before running it again.
- Short of memory (the second PC, with another project's containers, PhpStorm and Chrome open: 1 GB of 16 free), Claude Code stops a long command running in the background while the session waits - the full suite included (Doc 19 Phase F5b). Run the suite in two halves in the foreground instead, each about four minutes, and stop izSuite's own frontend and Gotenberg meanwhile (the tests need neither; `docker compose up -d --wait` and `docker start izsuite-frontend` bring them back): add `--filter '/Tests.(Unit.|Feature.[A-I])/'`, then `--filter '/Tests.Feature.[L-Z]/'`, to the throwaway container's command. The `.` stands for the namespace's backslash: Git Bash halves `\\` on its way to `docker`, so a pattern with backslashes matches nothing ("No tests executed!").
- MySQL refuses text longer than its column ("Data too long for column 'description'"), where the tests' SQLite keeps it whole: an Activity Log entry's description is 255 characters, so an order's long reason put into it failed only on the dev database (Doc 19 Phase F5a). `ActivityLogService` cuts a description now; anything else written from what a user types is to be kept within its column by its request's rules.
- A record whose files go through `RecordFileService` (a `RecordFilesPanel` in the frontend) needs the `HasFiles` trait on its model. Without it, listing its files fails (`filesIn()` is undefined); the tests catch it only if one lists them.
- The sidebar is cached in Redis for a day under the business's `nav_version`. A check that puts `nav_version` back leaves the sidebars of the versions it went through cached, so the next check that reaches the same versions reads an old sidebar (Sales Lite's, say). Forget those keys (`nav:{business}:{user}:{version}:{catalog}`) when putting the data back, or bump past them. The cache is Redis database 1: `docker exec izsuite-redis sh -c 'redis-cli -n 1 --scan --pattern "*nav:1:*" | xargs -r redis-cli -n 1 del'` (without `-n 1` the scan finds nothing). A check's throwaway users get the same ids as the last check's, since the restore puts AUTO_INCREMENT back: Doc 19 Phase F2's members read F1's members' cached permissions until the keys were gone.
- A PHP script that edits a file must check each step's result before writing: a failed `preg_replace()` returns null, and writing that empties the file (it happened to `AccountLedger.php`, restored from git).
- Docker Desktop can stop in the middle of a session (twice on the first PC on 2026-11-15). Containers that were running come back with it, and the app migrates at once - a new migration file included. Run `until docker info >/dev/null 2>&1; do sleep 1; done; docker stop -t 1 izsuite-api izsuite-queue izsuite-scheduler` in the background before starting Docker Desktop, back up, then `docker compose up -d --wait`.
- Coming back to a PC after the other has pushed migrations: its containers run every waiting migration as the app starts. Start only `docker compose up -d --wait mysql redis`, take the backup, then `docker compose up -d --wait`, and run `AppCatalogSeeder` if it changed. A stack stopped by hand stays stopped when Docker starts; one that was running comes back by itself, migrating at once.
- `last_value` is a reserved word in MySQL 8: quote it (`` `last_value` ``) when putting `workspace_sequences` back by hand.
- A `WorkspaceDropdown` option is picked by clicking the `button` inside its `li`, not the `li` itself.
- Another project's dev server on the host can take `localhost:3000` (Node on `0.0.0.0:3000`), and the browser then opens that site instead. Open izSuite at `http://127.0.0.1:3000`, which Docker holds; the API takes it, as no CORS origin list is set. Its sign-in is separate from `localhost`'s.
- A collection's `each()` stops at the first callback that returns false. An arrow function returns its assignment's value, so `->each(fn ($row) => $row->in_use = …)` stops at the first row given false and leaves the rest unset. Use a block closure (Doc 17 Phase D3b's tests caught it).
- A notice's e-mail goes through the queue, and a reminder through the scheduler: on dev both run in their own containers (`izsuite-queue`, `izsuite-scheduler`), and `MAIL_MAILER=log` puts the e-mails in `storage/logs/laravel.log`. Run `php artisan crm:remind-activities` in `izsuite-api` to send due reminders at once.
- A new export added to a file in `utils/` or `composables/` is not auto-imported by the running dev server: the page fails at run time with "… is not defined". `docker restart izsuite-frontend`, as for a new component.
- In a PHP edit script, a JavaScript template literal (`` `${x}` ``) inside a double-quoted PHP string is read by PHP as interpolation and stops the script part-way, after the edits before it were written. Put such text in single-quoted strings.
- In the Claude browser pane, `left_click_drag` does not start an HTML5 drag, so a dragged card never drops. Check a drop from a script instead: dispatch `dragstart` on the card, then `dragover` and `drop` on the column, with one `DataTransfer` (Doc 17 Phase D4's board).
- `ActivityLogService::record()` puts an entry down to whoever is signed in, even in a test that acts as someone across requests. Something no one in the business did (a customer through a link) goes through `recordCustomer()`.
- A page that loads on mount and again when another business is picked (`watch(() => navigation.value?.workspace?.id, …)`) also reloads when the navigation first arrives, a moment after the page. Skip a change from nothing: Doc 18 Phase C1's Open POS loaded, and priced its cart, twice.
- Without a browser (Doc 19 Phase D was checked so): call the API with a token made in tinker (`$user->createToken('check')`), and a storefront's API as a visitor with its cart's `X-Cart-Token`. A page rendered on the server comes from `curl http://127.0.0.1:3000/...` with an `izsuite_token` cookie, or a store's `izsuite_cart_{code}` cookie. The first page after `docker restart izsuite-frontend` can take more than two minutes on the second PC - longer while the test suite runs - so fetch it in the background.
- On the second PC the dev business has never finished its setup wizard (`setup_completed_at` is empty): its owner is sent to /setup from the app's pages. A member without Manage Business Settings is not.
- A headless browser check, where no browser can be driven (Doc 19 Phase D): Playwright's image, `mcr.microsoft.com/playwright/python:v1.49.0-jammy` (3.3 GB, with the browsers but without the Python package: `pip install playwright==1.49.0` first), run on the `izsuite-public` network. Inside it, the script forwards 127.0.0.1:3000 to `izsuite-frontend:3000` and 127.0.0.1:8010 to `izsuite-api:8000`, so the pages - and their scripts' calls to `http://localhost:8010` - work as on the PC. Sign in with the `izsuite_token` and `izsuite_workspace` cookies. Wait for `networkidle` after each page loads: a click before hydration does nothing. Phase D's script is `../doc-19-phase-d-ui-check.py`; its screenshots can be read back to check the layout.
  - A code e-mailed during the check (Doc 19 Phase E1's script, `../doc-19-phase-e1-ui-check.py`): mount the API's logs into the container, read only (`-v D:/projects/izsuite/izsuite-backend/storage/logs:/logs:ro`). The log mailer writes the plain-text part, where the code is a line of its own; read only what was written after the step began.
- OtpInput sends `complete` on every keystroke while all its boxes are filled. A page that submits on `complete` must empty the code after a wrong one, or the next code's first digit sends a half-changed one.
- A Vue component's event sent after it is unmounted is dropped. When an action empties what the page shows (closing an account clears the customer), the component cannot tell its page afterwards: it acts itself (navigates), or the page does the action.
- MySQL spends an id on an upsert that only updates: making an item, its number's sequence row is upserted, and `workspace_sequences`' AUTO_INCREMENT moves on with no new row. Put it back after a check that numbers something.
- The dev API is `php artisan serve` - PHP's built-in server - which answers one request at a time, so a page's calls queue behind each other. In a headless check, a save and the reload after it can outlast Playwright's 5-second wait: Phase E2's script sets `expect.set_options(timeout=15000)`.
- Putting the dev data back after a check from a dump taken just before it (Doc 19 Phase E3): stop `izsuite-api`, `izsuite-queue` and `izsuite-scheduler`, load the dump, forget business 1's cached sidebars (`redis-cli -n 1 --scan --pattern "*nav:1:*"`, then delete them), then `docker compose up -d --wait`. The dump re-creates every table, and from then on mysqldump prints their text columns with `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` where it printed `COLLATE utf8mb4_unicode_ci` alone - the same character set, so nothing changes but the words. Compare a dump from before such a restore with one after it only once that is evened out (`sed -E 's/ CHARACTER SET utf8mb4 COLLATE/ COLLATE/g'`).
- mysqldump reads AUTO_INCREMENT in a session of its own, so `SET SESSION information_schema_stats_expiry = 0` does not reach it. To compare dumps with AUTO_INCREMENT as it is, `SET GLOBAL information_schema_stats_expiry = 0` before the dump and `= DEFAULT` (86400) after it (Doc 19 Phase F6a).
- Before a browser check right after `docker restart izsuite-frontend`, fetch the pages it opens with curl first (`curl -s -o /dev/null --max-time 280 http://127.0.0.1:3000/...`): Doc 19 Phase F6a's first run's first page took more than five minutes to build, and the steps after it failed.
- In a Playwright script, `get_by_text(re.compile(...))` meets the element's whole text as it is, where a plain string meets it with its spaces evened out: Vue leaves a space before a `v-if` that renders nothing, so a pattern ending `\.$` misses - end it `\.\s*$` (Doc 19 Phase F6b).
- A `docker run` cut short by `timeout` (or stopped by Claude Code) leaves its container running, still clicking through the dev data: name a check's container (`--name f6a-check`), and after a cut-short run read its `docker logs` and `docker rm -f` it before putting the data back.

### Checking the app as a limited member

1. Copy a small script into the container with `docker cp`, and run it with `docker exec izsuite-api php artisan tinker --execute="require '/tmp/x.php';"`. It creates a throwaway verified user (with `role_id` 6, the app's user role), a role in the business and a `workspace_members` row, and writes `$user->createToken(...)->plainTextToken` to a file in the container. No password is needed. (Login as, which earlier phases used, became support sessions in Doc 15 Phase B4.)
2. In the browser, copy the owner's `izsuite_token` cookie into `localStorage`, then set the cookie to the member's token (URL-encoded) and reload. Afterwards write the owner's back as the cookie and reload.
3. Delete the user, member, role and token when finished. If the plan's limits were raised for the test, put them back.

### Look and feel

- **Nuxt frontend: dense and professional.** 14px text, 13px sidebar and table cells, 11px uppercase table headers, 20px page titles, 20px card padding, 38px buttons and inputs, one-row 56px top bar. Build pages from the shared classes in `assets/css/main.css`. Each list page has one header with its main action.
- **Admin panel: matches the `wwp-docker` project** (`D:\projects\wwp-docker` on the first PC), keeping izSuite's own blue brand. List pages keep filters and the Add button in one row, with no separate cards. `.card` beats Tailwind utilities, so `card p-0` does nothing. Every `<select>` is a Select2, and a page script must listen with `$(select).on('change', ...)`.

## Setting up a new PC

**Not in git, so bring these across:**

- **The requirements PDF:** `izSuite Software Product Requirements  Version 1.0.pdf` (311 pages, two spaces before "Version"). It was at `D:\` on the first PC.
- **The dev database and uploaded files**, if you want the same test data (below).
- **Your SSH key**, with access to both repos.
- **Optional, Claude's longer notes:** `C:\Users\Qbits\.claude\projects\D--projects-izsuite-izsuite-admin\memory\`. Copy them to the same place under your user folder. The folder name follows the project path, so keep the project at the same path. This file holds what matters without them.

**Needs:** Docker Desktop, Git for Windows (includes Git Bash and `pdftotext`).

```bash
mkdir -p /d/projects/izsuite && cd /d/projects/izsuite
git clone git@github.com:tah33/izsuite-admin.git
git clone -b nuxt git@github.com:ramdhani-org/izsuite-antigravity.git
docker network create izsuite-public

cd izsuite-admin
cp .env.example .env
docker compose run --rm --no-deps --entrypoint php app -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
# Put that value in APP_KEY in .env. Also set FRONTEND_URL=http://localhost:3000 and MAIL_MAILER=log
docker compose up -d
docker compose exec app php artisan db:seed

cd ../izsuite-antigravity
cp .env.example .env
# Set NUXT_API_BASE=http://api:8000 and NUXT_PUBLIC_API_BASE=http://localhost:8000
docker compose up -d
```

- These are the remotes this work is pushed to. The README shows a different clone URL.
- The first PC reaches GitHub through an SSH alias, `github-personal`. Either add the same alias or use the `github.com` addresses above.
- Keep the two repos in sibling folders. The rules in `CLAUDE.md` refer to `../izsuite-antigravity`.
- Admin: http://localhost:8000 (`/login`). Frontend: http://localhost:3000.
- **The second PC** (set up 2026-11-12) differs in three ways:
  - The folders are `D:\projects\izsuite\izsuite-backend` and `D:\projects\izsuite\izsuite-frontend`.
  - Other projects hold port 8000, so the API is on 8010. The admin's `.env` has `APP_PORT=8010` and `APP_URL=http://localhost:8010`, and the frontend's has `NUXT_PUBLIC_API_BASE=http://localhost:8010`.
  - The requirements PDF is `D:\project docs\izSuite Software Product Requirements  Version 10_261030_202704.pdf`.

**To bring the same test data,** run the first command on the old PC and the second on the new one. Uploaded images and attachments are not in the dump, so some may be missing.

```bash
docker exec izsuite-mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --no-tablespaces "$MYSQL_DATABASE"' > izsuite-dev.sql
```

```bash
docker exec -i izsuite-mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < izsuite-dev.sql
```

**To read the requirements,** turn the PDF into text and search for `Document NN:`. A page break can leave a few lines of one doc after the next doc's heading, so check both sides.

```bash
pdftotext -enc UTF-8 -layout "/d/izSuite Software Product Requirements  Version 1.0.pdf" requirements.txt
```

## What is done

The full detail of each phase is in its commit message: `git log -1 --format=%B <hash>`.

| Work | Admin | Frontend | Migrations |
|---|---|---|---|
| App system: apps, plans, sidebar, Apps Center | `f494fbc` | `49d5725` | |
| Business data kept per workspace | `d2a2897` | `2954b75` | |
| Doc 02 Dashboard: quick actions, account summary | | `b707f98` | |
| Doc 03 Contacts: first pass | `d78f4e7` | `0569956` | |
| Doc 04 Items: Phase A | `33c0737` | `4b2f8d9` | |
| Docs 03 and 04: Phase B, files, storage, CSV | `d8e0744` | `69ff735` | |
| Sales Lite: Phases A and B | `539a7cf` | `1a5119b` | up to 000083 |
| Sales Lite: Phase C, payments, credit notes, refunds | `e309c1d` | `f151dad` | 000084 to 000086 |
| Sales Lite: Phase D, PDFs, email, links, templates | `f6d97f2` | `b8aea15` | 000087 |
| Sales Lite: Phase E, Documents, Reports, Dashboard | `e6da47a` | `ae7ae0a` | |
| Docs 01 to 04: gaps closed | `f5d27a2` | `99d43d0` | 000088, 000089 |
| Doc 05: gaps closed | `4f53dc6` | `9807d12` | 000090 |
| Doc 06: gaps closed | `700e6c9` | `1641686` | 000091 |
| Doc 07: gaps closed | `c08af57` | `dcf78ae` | |
| Doc 08: gaps closed | `f4e1ede` | `873d3ad` | 000092 |
| Doc 09: Phase A, recording purchases | `4fc7f78` | `33389b7` | 000093, 000094 |
| Doc 09: Phases B and C, paying, shared pages | `eda3a7f` | `0fd6704` | 000095 |
| Doc 10: Phases A to C, Expenses Lite | `2ae24fe` | `24fdcaa` | 000096, 000097 |
| Doc 11: Phase A, business profile, locations | `7a6c111` | `9dfccb5` | 000098 |
| Doc 11: Phase B, regional formats, rate precision | `6aead31` | `886f799` | 000099 |
| Doc 11: Phase C, setup wizard | `de3cb15` | `690d136` | 000100 |
| Doc 11: Phase D, payment terms, tax registration, item settings | `6a06d3b` | `0823e61` | 000101, 000102 |
| Doc 11: Phase E, Activity Log, Settings order | `96b4d64` | `9bf79dc` | 000103 |
| Doc 12: Phase A, roles and permissions | `e2de580` | `68944c1` | 000104 |
| Doc 12: Phase B, users and invitations | `c1c5dc6` | `85047a2` | 000105 |
| Doc 12: Phase C1, the role in the screens | `4295998` | `c1c5f81` | |
| Doc 12: Phase C2, location access | `58945f6` | `b6d733b` | |
| Doc 12: Phase D1, sales, purchase and expense values | `8135b5b` | `5e62e3a` | 000106 |
| Doc 12: Phase D2, costs, balances, account details, tax | `812e7b1` | `7039f47` | 000107 |
| Doc 12: Phase E1, personal settings and sessions | `1a3e195` | `73cf6ca` | 000108 |
| Doc 12: Phase E2, multi-factor authentication | `e727463` | `7de8bd9` | 000109, 000110 |
| Doc 13: Phase A, Apps Center and app pages | `e0d062c` | `cdcdf12` | 000111 |
| Doc 13: Phase B1, prices, subscription page, branding | `1dc0642` | `1ca411f` | 000112 |
| Doc 13: Phase B2, lower plans, keeping within limits, warnings | `d7ec7c8` | `5156025` | |
| Doc 13: Phase C1, subscribing, invoices, bank transfer | `beaf887` | `1324945` | 000113 |
| Doc 13: Phase C2a, renewals, grace, trials, cancelling | `000444f` | `15d9d58` | 000114 |
| Doc 13: Phase C2b, upgrades and downgrades | the commit after `000444f` | `e7d6f59` | 000115 |
| Doc 13: Phase D1, subscription tax | the commit after `8e261db` | `0810b65` | 000116 |
| Doc 13: Phase D2, paying by card | the commit after `aec6aa5` | `faf1211` | 000117 |
| Doc 13: Phase D3, automatic card charges | the commit after `cca298d` | `b41141a` | 000118 |
| Doc 14: Phase A, safe saving | the commit after `28761a3` | `17afdd5` | 000119 |
| Doc 14: Phase B1, notifications and the bell | `b1b7656` | `44dfae2` | 000120 |
| Doc 14: Phase B2, notification preferences and usage limits | `395c349` | `bbc981d` | 000121 |
| Doc 14: Phase C, global search and the header | the commit after `395c349` | `3fcc76d` | |
| Doc 14: Phase D, notes, comments and activity | the commit after `4ae5865` | `24085c5` | 000122, 000123 |
| Doc 14: Phase E1, who and where from | the commit after `4bfb9c7` | `76b3aa3` | 000124 |
| Doc 14: Phase E2, numbers, files and names | the commit after `8e38b43` | `b29f546` | |
| Doc 15: gap check, and Phase A1, signing in to the admin | `addce5a` | | 000125 |
| Doc 15: Phase A2, the audit log, roles and critical actions | `15e0a32` | | 000126 |
| Doc 15: Phase B1, businesses and their statuses | `1f6dfb2` | `706dd4a` | 000127 |
| Doc 15: Phase B2, users and their accounts | `ff7538e` | `6b298ec` | 000128 |
| Doc 15: Phase B3, support cases | `1f15b4b` | `9259e26` | 000129 |
| Doc 15: Phase B4, support sessions | `bab54e5` | `d807b88` | 000130 |
| Doc 15: Phase C1, subscriptions in the admin | `debb680` and `9bd9fb5` | | 000131 |
| Doc 15: Phase C2a, payments and refunds | `c33db1a` | `e681985` | 000132 |
| Doc 15: Phase C2b, credits and billing reports | `5b923ed` | `d40ee05` | 000133 |
| Doc 15: Phase C3a, prices in several currencies | `e290028` | `128f85f` | 000134 |
| Doc 15: Phase C3b, promotions | `e49c71e` | `4bd877b` | 000135 |
| Doc 15: Phase D1, the Overview | `5313faf` | | none |
| Doc 15: Phase D2, usage and its history | `80c94fc` | | 000136 |
| Doc 15: Phase D3a, service health | the commit after `80c94fc` | | 000137 |
| Doc 15: Phase D3b, incidents | the commit after `80c94fc` | | 000138 |
| Doc 15: Phase E1, feature flags | the commit after `80c94fc` | the commit after `4bd877b` | 000139 |
| Doc 15: Phase E2, apps in depth | the commit after `80c94fc` | the commit after `4bd877b` | 000140 |
| Doc 15: Phase E3a, plan details | the commit after `80c94fc` | the commit after `4bd877b` | 000141 |
| Doc 15: Phase E3b, plan versions | the commit after `80c94fc` | | 000142 |
| Doc 15: Phase F1, announcements | the commit after `80c94fc` | the commit after `4bd877b` | 000143 |
| Doc 15: Phase F2, e-mail templates | the commit after `80c94fc` | | 000144 |
| Doc 15: Phase F3a, countries | the commit after `80c94fc` | the commit after `4bd877b` | 000145 |
| Doc 15: Phase F3b, payment methods by country | the commit after `80c94fc` | | 000146 |
| Doc 15: Phase G1, privacy requests | the commit after `80c94fc` | | 000147 |
| Doc 15: Phase G2, legal holds | the commit after `80c94fc` | | 000148 |
| Doc 15: Phase G3, consent and retention | the commit after `80c94fc` | the commit after `4bd877b` | 000149 |
| Doc 16: gap check, and Phase A, Sales takes over from Sales Lite | `b2d436d` | `2c105da` | 000150 |
| Doc 16: Phase B1, sales orders | `4dc364c` | `32a787b` | 000151 |
| Doc 16: Phase B2, invoicing from orders | `4dc364c` | `32a787b` | 000152 |
| Doc 16: Phase C, deliveries | the commit after `4dc364c` | the commit after `32a787b` | 000153 |
| Doc 16: Phase D1, advances and credit | the commit after `4dc364c` | the commit after `32a787b` | 000154 |
| Doc 16: Phase D2, deposit invoices | the commit after `4dc364c` | the commit after `32a787b` | 000155 |
| Doc 16: Phase E, returns | the commit after `4dc364c` | the commit after `32a787b` | 000156 |
| Doc 16: Phase F1, price lists | the commit after `4dc364c` | the commit after `32a787b` | 000157 |
| Doc 16: Phase F2, discount limits | the commit after `4dc364c` | the commit after `32a787b` | 000158 |
| Doc 16: Phase G1, credit control | `86d14c2` | `2281c27` | 000159 |
| Doc 16: Phase G2, the customer's link | `a7bc602` | `23dc5f7` | 000160 |
| Doc 16: Phase H1, settings and the Dashboard | `ad0f5c0` | `6517bf4` | 000161 |
| Doc 16: Phase H2a, the "Sales by" reports | `48eba34` | `94742a7` | none |
| Doc 16: Phase H2b, discounts, price lists and margin | `d00a1c8` | `917c2a1` | none |
| Doc 16: Phase H3a, the quotation reports | `c681fbe` | `c3c426a` | none |
| Doc 16: Phase H3b, orders and deliveries | `0637123` | `0754657` | none |
| Doc 16: Phase H3c, returns, money and credit | `916916e` | `b79c75d` | none |
| Doc 17: gap check | `ed24509` | | none |
| Doc 17: Phase A1, CRM's menu, settings and lists | `5b1bf2e` | `41761f0` | 000162 |
| Doc 17: Phase A2, the pipeline and shared sales teams | `d60c15a` | `6f21d3a` | 000163 |
| Doc 17: Phase B1, leads themselves | `78c00df` | `7812131` | 000164 |
| Doc 17: Phase B2a, qualification and interested items | `78c00df` | `7812131` | 000165 |
| Doc 17: Phase B2b, a lead's files, comments and timeline | `78c00df` | `7812131` | none |
| Fixes: Doc 16's records at their own locations, and the data export's Doc 16 and 17 tables | `78c00df` | | none |
| Doc 17: Phase C1, converting a lead | `a4c0ab6` | `02e95eb` | 000166 |
| Doc 17: Phase C2, possible duplicate leads | `a36021e` | `0b6e9ef` | 000167 |
| Doc 17: Phase C3, merging contacts | `56ca093` | `cdb6659` | 000168 |
| Doc 17: Phase D1, opportunities themselves | `56ca093` | `cdb6659` | 000169 |
| Doc 17: Phase D2a, won, lost, reopening and archiving | `f052a9d` | `3f475e6` | 000170 |
| Doc 17: Phase D2b, an opportunity's files, comments and timeline | `2414d2c` | `b514ca8` | none |
| Doc 17: Phase D3a, stale stages and the fields a stage needs | `6059052` | `6b69a39` | 000171 |
| Doc 17: Phase D3b, several pipelines for chosen teams or locations | `11fc750` | `78b7464` | 000172 |
| Doc 17: Phase D4, the board and calendar views | `4254ba1` | `9927a2c` | none |
| Doc 17: Phase E1, activities | `ea90899` | `9642be7` | 000173 |
| Doc 17: Phase E2, reminders and telling someone given an activity | `66e50b2` | `8193f53` | 000174 |
| Doc 17: Phase E3, the timeline of a lead, an opportunity and a contact | `27fa34d` | `412898e` | none |
| Doc 17: Phase F1, sales documents made from an opportunity | `d6971af` | `2cd5594` | 000175 |
| Doc 17: Phase F2, an opportunity won when its order is confirmed | `9144e8a` | `2401e51` | 000176 |
| Doc 17: Phase F3a, e-mail from a lead, an opportunity or a contact | `7ec724c` | `6f7bea7` | 000177 |
| Doc 17: Phase F3b, e-mail templates | `e814d61` | `a58d4f2` | 000178 |
| Doc 17: Phase F4, the CRM part of a contact's profile | `b07c07c` | `9ff76c5` | none |
| Merge with the first PC's unfinished D3b1 (broken: see the fix below) | `8e07e8f` | `e72c322` | a second 000172, removed by the fix |
| Fix: the merge of 2026-11-18, D3b to F4 as the second PC built them | `84c441a` | `b691903` | none |
| Doc 17: Phase G1, the CRM Overview | `42ff146` | `e6140c6` | 000179 |
| Doc 17: Phase G2, the Forecast page | `32064b6` | `481bb34` | 000180 |
| Doc 17: Phase G3a, response tracking on a lead's and an opportunity's page | `81bc263` | `978548f` | 000181 |
| Doc 17: Phase G3b, the follow-up warnings on the Overview | `d7d5524` | `95ea5c7` | 000182 |
| Doc 17: Phase G4, the Dashboard's CRM figures | `19cb5ad` | `9fdefc0` | none |
| Doc 17: Phase H1, the CRM lead reports | `ff2f536` | `6171111` | none |
| Doc 17: Phases H2 and H3, the CRM opportunity, people and activity reports | `4b75b5c` | `26f0f0e` | none |
| Doc 17: Phase I1, the lead assignment rules | `9f37087` | `ce8c86b` | 000183 |
| Doc 17: Phase I2, lead scoring | `ced554d` | `8306872` | 000184 |
| Doc 17: Phase I3, marketing consent | `7404395` | `1119f22` | 000185 |
| Doc 17: Phase I4, custom fields | `e5cd080` | `e20fa70` | 000186 |
| Doc 18: the gap check, and Phase A1, POS on with its settings | `1c88f96` | `6c0cd28` | 000187 |
| Doc 18: Phase A2, registers | `0f35a06` | `9b9917b` | 000188 |
| Doc 18: Phase B1, opening and closing sessions | `620aebe` | `19499c7` | 000189 |
| Doc 18: Phase B2a, the supervisor's side of sessions | `4a0bd4d` | `0e3b89c` | 000190 |
| Doc 18: Phase B2b, counting cash by notes and coins | `3936732` | `7166223` | 000191 |
| Doc 18: Phase C1, the selling screen | `8c8f380` | `27ce76c` | 000192 |
| Doc 18: Phase C2, payment and the completed sale | the commit after `8c8f380` | `7933c6d` | 000193 |
| Doc 18: Phase C3, discounts, price overrides and cash rounding | the commit after `8c8f380` | `7933c6d` | 000194 |
| Doc 18: Phase C4, the receipt | the commit after `8c8f380` | `7933c6d` | 000195 |
| Doc 18: Phase D1, holding and resuming orders | the commit after `8c8f380` | `7933c6d` | 000196 |
| Doc 18: Phase D2, pending payments | the commit after `8c8f380` | `7933c6d` | 000197 |
| Doc 18: Phase D3a, credit sales | the commit after `8c8f380` | `7933c6d` | 000198 |
| Doc 18: Phase D3b, the customer's credit as a payment | the commit after `8c8f380` | `7933c6d` | 000199 |
| Doc 18: Phase D4, the formal invoice | the commit after `8c8f380` | `7933c6d` | 000200 |
| Doc 18: Phase E1, returns and their refunds | the commit after `8c8f380` | `7933c6d` | 000201 |
| Doc 18: Phase E2, refund receipts and other refund methods | the commit after `8c8f380` | `7933c6d` | 000202 |
| Doc 18: Phase E3, exchanges | the commit after `8c8f380` | `7933c6d` | 000203 |
| Doc 18: Phase F1, cash movements | the commit after `8c8f380` | `7933c6d` | 000204 |
| Doc 18: Phase F2, paying an expense from the drawer | the commit after `8c8f380` | `7933c6d` | 000205 |
| Doc 18: Phase F3, the X and Z reports | the commit after `8c8f380` | `7933c6d` | none |
| Doc 18: Phase G1, the POS pages of records | the commit after `8c8f380` | `7933c6d` | 000206 |
| Doc 18: Phase G2, the POS Overview and the Dashboard's POS card | the commit after `8c8f380` | `7933c6d` | 000207 |
| Doc 18: Phase G3, POS documents on the Documents page | the commit after `8c8f380` | `7933c6d` | none |
| Doc 18: Phase H1, the POS sales reports | the commit after `8c8f380` | `7933c6d` | none |
| Doc 18: Phase H2, discounts, taxes, returns, voids and items | the commit after `8c8f380` | `7933c6d` | none |
| Doc 18: Phase H3, sessions and cash | the commit after `8c8f380` | `7933c6d` | none |
| Doc 18: Phase I1, the cashier's PIN and the register's lock | the commit after `8c8f380` | `7933c6d` | 000208 |
| Doc 18: Phase I2, a manager's approval at the register | the commit after `8c8f380` | `7933c6d` | 000209 |
| Doc 18: Phase I3, registered devices | the commit after `8c8f380` | `7933c6d` | 000210 |
| Doc 19: Phase A1, eCommerce on, with its storefronts | `47257f3` | `e7782c7` | 000211 |
| Doc 19: Phase A2, the plans' storefronts | `47257f3` | `e7782c7` | 000212 |
| Doc 19: Phase A3, the storefronts' domains | `47257f3` | `e7782c7` | 000213 |
| Doc 19: Phase B1, items published online | `47257f3` | `e7782c7` | 000214 |
| Doc 19: Phase B2, categories, collections and attributes | `47257f3` | `e7782c7` | 000215 |
| Doc 19: Phase B3, options and variants | `47257f3` | `e7782c7` | 000216 |
| Doc 19: Phase C1, the storefront opens | `47257f3` | `e7782c7` | none |
| Doc 19: Phase C2, categories, collections and search suggestions | `47257f3` | `e7782c7` | none |
| Doc 19: Phase C3, filters by price, options and attributes | `47257f3` | `e7782c7` | none |
| Doc 19: Phase C4a, the storefront's theme and branding | `47257f3` | `e7782c7` | 000217 |
| Doc 19: Phase C4b, the home page's sections and banners | `47257f3` | `e7782c7` | none |
| Doc 19: Phase C5a, the storefront's contact and about pages and its policies | `47257f3` | `e7782c7` | 000218 |
| Doc 19: Phase C5b, pages of the business's own | `47257f3` | `e7782c7` | 000219 |
| Doc 19: Phase C6a, the storefront's SEO | `47257f3` | `e7782c7` | none |
| Doc 19: Phase C6b, the storefronts' cookie notice | `47257f3` | `e7782c7` | 000220 |
| Doc 19: Phase D1, shipping zones and methods, and pickup | the commit after `47257f3` | `b4befbb` | 000221 |
| Doc 19: Phase D2, payment methods without a gateway | the commit after `47257f3` | `b4befbb` | 000222 |
| Doc 19: Phase D3, the cart | the commit after `47257f3` | `b4befbb` | 000223 |
| Doc 19: Phase D4, checkout and the order | the commit after `47257f3` | `b4befbb` | 000224 |
| Doc 19: Phase D5, the order's page and its confirmation e-mail | the commit after `47257f3` | `b4befbb` | 000225 |
| Doc 19: Phase E1, the customer's account | `569d679` | `a4df0be` | 000226 |
| Doc 19: Phase E2, the account's contact, orders and addresses | `569d679` | `a4df0be` | 000227 |
| Doc 19: Phase E3, what a signed-in customer is offered | begun in `569d679`, the rest `06f4a77` | `597c17e` | 000228 |
| Doc 19: Phase F1, the Orders page and an order's page | `46725ba` | `4679f08` | 000229 |
| Doc 19: Phase F2, confirming an order - its invoice | `630466f` | `82421c5` | 000230 |
| Doc 19: Phase F3, an order's payment received or failed | `e289e75` | `f7cb13b` | 000231 |
| Doc 19: Phase F4, holding, releasing and cancelling an order | `ecfd145` | `52a0d1c` | 000232 |
| Doc 19: Phase F5a, editing an order's customer, addresses and delivery | `dfbffc3` | `18b9bfe` | none |
| Doc 19: Phase F5b, an order's products and discount | `430cb30` | `316c273` | 000233 |
| Items: an item's attachments | `38f2469` | `61188d1` | none |
| Doc 19: Phase F6a, payment links sent and their page | begun in `7d6d2f8`, the rest `0ba3372` | `a2c30e1` | 000234 |
| Doc 19: Phase F6b, a payment link's customer saying they paid | the commit after `0ba3372` | the commit after `a2c30e1` | 000235 |

Admin Clients and Login as came from shuvo (`1c91dbc`, frontend `b4abf92` and `04ebe7b`). Between I1 and I2, shuvo added rich text for payment instructions and the Sales settings' notes and terms (`a5e46cb` and `9bf4525`, with no frontend commit yet). After F5b, shuvo added an item's attachments (above), a local Gotenberg script for running the API without Docker (`a9c753c`, "issue fixed"), admin profile pictures (`03080c0`, "image issue") and purchases fixes (frontend `bbfdd94`, "fixed some issue").

### Decisions worth knowing

These are easy to undo by mistake.

**Foundation**

- The plan belongs to each business, not to the account. A new business starts on Billing Starter with its Lite apps on.
- All business data carries `workspace_id`. Controllers use `currentWorkspace()`, and every new business table follows the same pattern.
- The sidebar comes from `/navigation`, cached under the business's `nav_version` and the catalog version. It is never cleared by hand: bump the version instead.
- Limits live on packages (`business_limit`, `user_limit`, `location_limit`, null means none; Starter is 1/1/1). `business_limit` means how many businesses on that plan one account may own.

**Contacts, Items, files**

- A customer line's tax: a non-standard treatment or exemption number first, then the item's tax, then the business default.
- Imports are CSV only and only add records. Combos are not imported.
- Files use `workspace_files` and a `FILES_DISK=local|spaces` switch. Private files use short-lived signed links.

**Money**

- `account_transactions` is the ledger. Only completed rows count. The running balance is worked out over the whole history, so filters never change it.
- A payment account with no location belongs to the whole business. There is one default receiving and one default payment account per currency and location.
- A settlement class recalculates a document after every change (`InvoiceSettlement`, `PurchaseSettlement`, `ExpenseSettlement`). Mistakes are reversed or voided, not deleted.
- `SalesCalculator` (bcmath) does every total, and purchases and expenses reuse it.
- An expense's amount is its net plus any tax that cannot be claimed back.

**Documents and reports**

- One `sales_documents` table holds all kinds, by `type`. A credit note points to its invoice with `source_id`.
- Expired and Overdue are worked out on read, never stored.
- Business and customer details are saved on a document when it gets its number.
- Numbers come from `workspace_sequences` through `DocumentNumbers::next`, and never repeat.
- PDFs use Gotenberg. One Blade set in `resources/views/documents` draws the PDF, the print and the secure-link page.
- The Documents page never changes data. Its row actions send you to the record. Its list is one query per app joined together, and a new app joins by adding its own.
- Reports return a generic `ReportTable`, and all filters pass through `ReportService::filters`.

**Settings**

- A business's formats live on `workspaces` and reach the frontend in the sidebar payload.
- Payment terms are a list, but contacts and documents still store days.
- The Activity Log records `workspace_id` and previous and new values, with account numbers masked. It never records a secret, not even hashed: passwords, sign-in codes, the authenticator app's secret and recovery codes are left out (`SettingChanges`). Before E2 a password change wrote its old and new hash there; migration 000110 took those out.

**Users, roles, locations (Doc 12)**

- `App\Support\Permissions` lists every permission. `RoutePermissions` maps every route to what it needs, and a test fails if a route is missing. The `access` middleware enforces it. The page buttons only follow it.
- Record actions are filtered on the API by `AllowedActions`, so a page shows what it receives.
- Lists across several kinds hold only the kinds the role may view (`WorkspaceAccess::viewable`), so every row opens.
- Location access is separate from the role. A full-access Administrator can still be limited to some locations; only the owner always has all.
- A record route is refused at the wrong location by `RouteRecords` and `LocationAccess`, including its PDF, email, link and attachments. Lists take `location_ids` in their filters.
- A record with no location is only for users of every location.
- GET `/locations` returns only the user's own, so pickers and filters follow by themselves.
- Each app's values have their own View: `sales.values.view`, `purchases.values.view`, `expenses.values.view`. Without it, the app's records still show, but their amounts come back as `null` (`HidesValues` in the Resources), and the screens show "—" or "Amounts are hidden for your role."
- Anything more than viewing a record needs its values: creating, editing, sending, paying, exporting (`VALUED` in `Permissions.php`). A PDF needs them too.
- A report that draws on several apps opens only if the role sees all of them, values included, so no report is ever half shown. The Documents page search matches amounts only for apps whose values the user may see.
- Purchase prices, balances, full account details and tax information each have their own permission: `items.costs`, `payment_accounts.balances`, `payment_accounts.details` and `taxes.view`. Without it the API sends `null`, and account, sort and merchant numbers only their last four characters (`hideWithout` and `maskWithout` in `HidesValues`). The screens show "—", and the account page says "Balances are hidden for your role."
- Changing what holds them needs them. Creating, editing or importing items needs `items.costs`. Creating or editing an account needs balances and details, and transfers and the account export need balances. Creating, editing or importing contacts, and Currency and Tax Settings, need tax information.
- Tax information covers a contact's tax ID, country, treatment and exemption number, the business's tax registration (Settings > Taxes, and on documents), the tax reports, and the contact export's tax columns. Line taxes follow the app's values. PDFs and emails stay whole, because they are what the customer receives.
- The Dashboard's accounts card and the Payment Account Transactions report need balances. The Sales User template has no balances; the Finance User holds balances, details and tax information; the Purchase User holds purchase prices.
- The Activity Log writes down amounts, prices, balances, numbers and tax details as they were. So View Activity Log needs all seven: the three apps' values and the four above.
- A user's name, phone, preferred language, personal time zone and profile image are theirs in every business (section 22). Saving copies only the fields they changed onto their record in each business (`workspace_members`), so each Users list shows them. A business's own edit stays until the user changes that field again, and the job title is always the business's.
- An empty personal language or time zone means each business's own. Times of day follow the personal time zone in the browser (`plugins/regional.client.ts`); document dates never move. The screens are English only, so the language is kept for later.
- A new account from an invitation takes the invitation's phone, language and time zone.
- Each sign-in keeps its IP address and browser (`ip_address` and `user_agent` on `personal_access_tokens`), named by `App\Support\DeviceName`. Sign-ins from before E1 show as "Unknown device". Support sessions in the user's business are listed too, marked "izSuite support".
- Nobody changes the role they hold, not even its permissions (`RoleService::update`), and the role page shows it read-only. Changing one's own role or locations was already refused.
- Two-factor authentication is an authenticator app (TOTP: six digits, 30 seconds, one step either side), worked out in `App\Support\Totp` with no package. The secret is kept encrypted, the ten recovery codes only as hashes (`App\Support\RecoveryCodes`), and each code works once (`two_factor_last_step`). The QR code is drawn in the browser by the `qrcode` npm package.
- Setting it up, new recovery codes and turning it off each need the password (`TwoFactorService`), so an open session cannot switch it on against its owner.
- With it on, the password step answers with a five-minute challenge instead of a session (`SignInChallenges`, in the cache); `POST /auth/login/two-factor` takes the app's code or a recovery code. Five wrong codes end the challenge. A support session from the admin panel does not ask.
- The `reset-password` rate limit counts a signed-in request by its user's e-mail. It used to put every request without an e-mail - all password changes - in one bucket shared by everybody.

**Apps and plans (Doc 13)**

- An app's page (`/apps-center/{family}`) takes its full description, features and support link from the admin app form. What it adds comes from the code: sidebar pages from `menu_items`, reports from `ReportCatalog::namesFor`, permissions from `Permissions::groupLabelsOf`, and Dashboard sections and quick actions from the frontend's `utils/appContributions.ts`, which the Dashboard itself reads.
- `AppCatalogSeeder` writes the Lite apps' descriptions only where they are empty, so an admin's words are kept. Run it after migrating, as for any deploy.
- Each edition names the lowest active plan that includes it (`PackageRepository::lowestNames`).
- Turning apps on and off needs Manage Apps (`settings.apps`); the owner always may. Turning one off shows its impact first (`GET /apps/{app}/deactivation`). The apps that need it can go with it (`with_dependents`), each fully off, the furthest first; without that it is refused, as before.
- The public Explore Apps and Pricing pages read `GET /catalog`, which needs no sign-in. The hard-coded catalog and price calculator are gone.
- A plan's prices were on `packages` (`price_currency`, `price_monthly`, `price_yearly`) until Doc 15 Phase C3a moved them to `package_prices` (see below); `packages` keeps `price_currency` as the main currency, with `trial_days`, `grace_days` and `removes_branding`. No price in force means not announced, and the plan then says "Price to be announced". Billing Starter is free (0). Migration 000112 set Standard and Premium to remove the branding. Trial and grace days are kept for Phase C and do nothing yet.
- "Powered by izSuite" follows the business's plan. A document saves `powered_by` in its business snapshot when it gets its number, and its PDF, print and secure link read that, so a later plan change never alters it; documents numbered before B1 have no such key and keep the branding. Drafts and report PDFs follow the plan as it is.
- `GET /subscription` and `PUT /subscription/billing-details` need Manage Subscription (`settings.subscription`). Until Phase C the status is Free on Billing Starter and Active on any other plan. Billing details are one `workspace_billing` row per business, and each save goes in the Activity Log.
- A plan's user limit counts active and invited users, the owner among them. Its location limit counts active locations only (changed in B2): an inactive one is outside it, so turning one back on needs room, as reactivating a suspended user already did.
- The admin's Move button opens a review (`GET /admin/workspaces/{id}/package?package_id=`, `PackageChangeImpact`) before the move. The review and the move share `SyncAppsWithPackage::changes`, so the review shows exactly what the move does to the apps.
- Over a user or location limit, only the Business Owner chooses what stays active (`GET` and `POST /subscription/over-limit`, `OverLimitService`). The owner and the Primary Location always stay. The others go through the usual services, so each suspension, withdrawn invitation and inactive location is logged as if done by hand, then one entry sums it up. `GET /subscription` says which limits are passed (`excess`) and whether this user chooses.
- Usage counts carry a state: `ok`, `warning` (from 80%), `full` or `over`. The Dashboard warns the roles with Manage Subscription, but not about a reached limit of one: every Billing Starter business is at its one user and one location from its first day.
- A business's subscription is one `workspace_subscriptions` row: plan, cycle and status (`trialing`, `awaiting_payment`, `active`, `cancelled`), with its trial and paid period as dates, both days included. The row is reused when it subscribes again, so `trial_starts_on` stays and a business gets one trial. While it has a current subscription it cannot start another; changing plans comes with C2.
- izSuite's invoices are `subscription_invoices`, never the business's own documents. They keep who they were billed to, izSuite's details and the payment instructions as issued (`SubscriptionBilling`), numbered from `billing_sequences`. The PDF draws izSuite's logo as it is now: the one uploaded in Admin > Settings > Branding, else `public/images/brand/izsuite-logo.svg`.
- Without a trial, the paid period starts on the day izSuite confirms the payment. After a trial, the invoice is due on the trial's last day and its period starts the next day. Confirming moves the business to the plan through `SyncAppsWithPackage`.
- Admin actions stay out of a business's Activity Log, as before, so a confirmed payment shows in its billing history (`paid_at`, `confirmed_by`) rather than in its log. The business's own steps (subscribing, withdrawing, the transfer reference) are logged.
- Billing e-mails (`SubscriptionBillingMail`) are queued after the transaction commits, and draw their PDF when sent, so a server needs the queue worker running.
- The hourly `subscriptions:advance` (`SubscriptionCycle`) takes each subscription by its business's own calendar day, so a business is seen soon after its midnight. Each step happens once: a renewal is issued once a period (found by its `period_starts_on`), and the daily e-mails go once a day (`last_notice_on`).
- `period_ends_on` is the last paid day. Grace starts the day after and lasts the plan's grace days: `grace_ends_on` is its last day, and the business moves to Billing Starter the day after. A renewal paid ahead waits for the period's end; one paid in grace starts at once, from where the period ended (`SubscriptionBilling::confirm`).
- Ending a subscription (`SubscriptionCycle::end`) cancels its open invoice and moves the business to Billing Starter through `SyncAppsWithPackage`, so Phase B2's over-limit choice follows. It is logged as the system's, and the business gets an e-mail saying why: trial, grace, cancelled or ended.
- Cancelling an active subscription sets `cancels_at_period_end` and cancels a renewal already issued; Keep My Plan issues it again if it is due. The notices are `SubscriptionNoticeMail` (trial ending, each grace day, ended); renewal invoices reuse `SubscriptionBillingMail`.
- Plan changes are `SubscriptionChanges`. An upgrade's credit is what the current period was paid before tax (its paid invoice) times the days left, today counted, over the period's days, rounded down to the cent. It is never more than the new price, and nothing is credited across currencies. From a yearly plan an upgrade must stay yearly, so no months are lost. The invoice's kind is `upgrade`, and paying it starts a full period that day, cancelling any open renewal, scheduled downgrade or cancellation.
- While an upgrade waits for payment no renewal is issued. At the period's end an unpaid upgrade is cancelled and the usual renewal and grace follow.
- A downgrade is `scheduled_package_id` and `scheduled_cycle` on the subscription. The renewal is issued for that plan, and the business moves to it as the period ends, paid or not, so a grace runs on the lower plan.
- izSuite's details on invoices are the `billing_*` settings (Admin > Settings > Billing): company name, address, e-mail, tax ID and the days to pay (7). `SettingService` adds them on first visit.
- Subscription tax rules are `subscription_taxes`, one per country and `starts_on`. `SubscriptionTaxes` picks the country's latest rule that has started and adds its rate to the price less any credit, half up to the cent. With `reverse_charge` and a tax ID in the billing details, the tax is 0% and the rule's note (or a standard one) prints instead. The invoice keeps `tax_name`, `tax_rate`, `tax` and `tax_note`, so later rule changes never touch it.
- `SubscriptionBilling::quote` is the one price breakdown (price, credit, tax, total). Issuing an invoice, the upgrade preview and `GET /subscription/quote` (the subscribe dialog) all use it.
- An upgrade's credit is worked out from what the period was paid before tax (`SubscriptionInvoice::net`), since the new invoice's tax is on its price less that credit.
- Card payments go through `CardGateway`, bound to `StripeGateway` in `AppServiceProvider`. It calls Stripe's REST API with Laravel's HTTP client, so there's no Stripe package and the tests fake it with `Http::fake`. `CardPayments` holds the logic and `SubscriptionCardService` the API side.
- Stripe's keys live in `payment_methods.credentials` as `encrypted:array`, shaped `{test: {...}, live: {...}}`; `is_sandbox` picks the mode. The gateway reads them on every call, so a change in the admin is never missed. A webhook counts only with the current mode's signing secret, signed within the last 5 minutes.
- Checkout uses payment mode with `setup_future_usage=off_session`, so the card is kept for D3. Its metadata names the business and invoice, and it expires after an hour. Stripe sends the business back to `/settings?tab=subscription&card_checkout={id}`, and the page settles it through `POST /subscription/card-checkout`.
- Settling locks the invoice row and records `subscription_payments`, unique per provider and reference, so the webhook and the return can't both pay it. A card-paid invoice has no `confirmed_by`; `paid_with` and `payment_reference` say how it was paid.
- One card per business (`workspace_cards`). Its Stripe customer is kept per mode, even after the card is removed. A new card replaces the old one, which is taken off Stripe once the change is saved.
- Automatic charges (`CardPayments::charge`) are Stripe PaymentIntents with `off_session` and `confirm`, keyed `izsuite-charge-{invoice}-{business day}`. A try the business asks for gets a fresh key. Every try is a `subscription_payments` row with `attempted_on`. A decline is `failed`, a bank wanting confirmation is `action_required`, and a charge not yet settled is `processing`, which `payment_intent.succeeded` later turns into `succeeded`.
- Stripe being unreachable is not counted as a try: it is reported, and the hourly job tries again. The status is `past_due` while the paid period still runs after a refused renewal, and `grace` once it has ended.
- Duplicate creates (Doc 14 Phase A): the `idempotent` route middleware (`IdempotentRequests`) is on the 14 create routes. Keys live in `idempotency_keys`, per user, and are pruned after a day by `model:prune`. A repeat gets the first answer with an `Idempotent-Replayed` header. A repeat still running is refused with 409, and the same key with different data with 422. A failed request frees its key.
- The frontend's `useApi` sends a fresh key with every POST, in the browser only. It reuses a key only while the identical request has no answer: the connection dropped, the server failed, or it is still being saved.
- Edit conflicts: contacts, items, payment accounts, locations, sales documents (and credit notes), purchases and expenses carry a `version`, their `updated_at` in UTC to the second. `useApi` remembers versions from what it reads, list rows included, and from saves, keyed by the address after `/api/v1`, and sends If-Match on PUT. `EditConflicts::ensureUnchanged` refuses a stale save with 409; a save without If-Match isn't checked. Replacing a document's lines touches it, so a change to lines alone counts.
- Payments re-read their invoice, purchase or expense with `RowLockRepository::lock` (SELECT … FOR UPDATE) inside the transaction and check what is still due there. Reversals lock the payment and check it is still completed.
- Notifications (Doc 14 Phase B1) all go through `Notifier::send($workspace, $users, Notice, $except)`. A `Notice` has a trigger, a key, a title, a body, a link, a subject and an e-mail flag.
  - Each user gets one `user_notifications` row per key (unique per user and key). Its `notification_deliveries` hold the in-app delivery, marked sent at once, and an e-mail delivery when asked for.
  - The e-mail goes through the queued `DeliverNotificationEmail`, tried 3 times with backoff. It reads the address when it runs and marks the delivery sent, or failed with the reason.
  - `NotificationAudience::withPermission` chooses who hears: active members holding the permission, and with access to the record's location when it has one. The user who did the thing is left out.
- Triggers so far:
  - `SubscriptionBilling::notice()` and the invoice and receipt e-mails, in-app to `settings.subscription`, keyed per day or per invoice.
  - `NotifyAppChange` on `AppActivated` and `AppDeactivated`, to `settings.apps`: in-app, and by e-mail where the business turns that on.
  - `members.joined` when an invitation is accepted, in-app and by e-mail to whoever invited, else the owner.
  - `UsageAlerts`, run hourly by `notifications:usage`, to `settings.subscription`, keyed `usage.{limit}.{state}:{business}:{limit value}`. So each state reaches a user once per business and limit, and a new limit is told afresh.
- Preferences (Doc 14 Phase B2): `workspaces.notification_preferences` and `users.notification_preferences` hold JSON by kind and channel, unset meaning the default. The kinds live in `NotificationKinds`, and a trigger's kind is the part before its dot (`apps.activated` is `apps`).
  - `Notifier::send` checks both for each user. In the app needs both to allow it; by e-mail needs the notice's e-mail flag as well. Sent by e-mail only, the row has `shown = false`, and the bell's queries leave it out. Allowed neither way, no row is made.
  - A locked kind (subscription) is always on in the app and never by e-mail here, whatever is saved. Saving it as off changes nothing and logs nothing.
  - `NotificationKinds::resolve($chosen, user: true)` gives a user's defaults: everything on. The business's are each kind's own.
  - The APIs: `GET`/`PUT /business-settings/notifications` (`settings.view` and `settings.business`) and `GET`/`PUT /profile/notifications`, each answering `{ kinds: [{ key, label, description, locked, in_app, email }] }`. A save sends every kind and both channels.
- Notification e-mails are sent with `Mail::locale()` in the user's language, English for now.
- The bell: `GET /notifications`, `GET /notifications/unread` (counted every minute and when the window comes back), `POST /notifications/{id}/read` and `POST /notifications/read`. They are open to every member, and each user sees only their own.
- `/profile` answers in the usual envelopes, and the signed-in user (`UserResource`) carries only their name, e-mail, phone, image, language and time zone.
- Global Search (Doc 14 Phase C) is `GET /search?q=`:
  - It is open to every member (`RoutePermissions::MEMBER`) and throttled to 120 a minute. `GlobalSearchService` keeps each kind to the role's View permission (sales kinds through `WorkspaceAccess::viewable`), and transactions to `WorkspaceAccess::locations()` (`atLocations`).
  - A family's records come while its app has a `workspace_apps` row (`WorkspaceApps::hasHistory`), on or off, just as `EnsureAppActive` lets them be read.
  - It sends no amounts, so values permissions play no part, and it never matches by amount.
  - The queries are `GlobalSearchRepository`. Each result is `SearchResultResource`: `group`, `type`, `title`, `subtitle`, `status`, `date`, `location`, `app`, `archived` and `link`.
- The navigation's `workspace.logo_url` is read on every `/navigation` (`NavigationBuilder::for`), outside its cache. A new logo replaces the old file without bumping `nav_version`, so a cached URL would break.
- Internal notes (Doc 14 Phase D) are `internal_notes` on `sales_documents`, `purchases` and `expenses` (migration 000122). No sheet, snapshot or e-mail reads them. `SalesConversionService` copies them, and `SalesSnapshots` leaves them out.
- Comments are `record_comments` (migration 000123), keyed by the record's morph class and id.
  - `mentions` keeps `[{id, name}]` as they were written: a later rename does not change an old comment.
  - `RecordCommentService` keeps only mentions of those `NotificationAudience::membersWithPermission` allows (the record's View permission at its location), and sends them `comments.mentioned`, keyed per comment.
  - Writing and deleting are logged on the record, so they appear in its timeline.
- `DiscussedRecords` finds the record for both features: the route's `family` default, a sales route's `{kind}`, and `{id}`. The routes are `.../activity` and `.../comments` (with `/people` and `/{commentId}`) under each app's own record routes. So `app`, `RoutePermissions` (the record's View) and `RouteRecords` (its location) guard them as they guard the record.
- The timeline is `RecordActivityRepository`, from `activity_logs` by `model_type` and `model_id`, up to 100 entries. `RecordActivityService::withoutAmounts` takes "of USD 100.00" and "USD 10.00" out of the text for a role without the app's values.
- Who added and last changed a shared record (Doc 14 Phase E1, migration 000124) is `created_by` and `updated_by`. They are on `contacts`, `items`, `payment_accounts` and `locations`, written by the `RecordsAuthors` model trait on every create and update.
  - The trait asks `App\Support\Authors::current()`: the signed-in user, or whoever `Authors::as()` names. `ImportService::runNext` runs each import row as the import's user.
  - Transactions kept their own `created_by` and `updated_by` already, set by their services. `HasAuthors` gives them the `creator` and `updater` relations.
- The resources add `authors` (`added_by`, `added_at`, `changed_by`, `changed_at`) only where the record was read with them (`whenLoaded`). That is each record page's `find()` (`RecordAuthorRepository::load`), and the Locations list. Other lists stay as short as before.
- The app that made a transaction is `source_app_id` on the three documents and three payments, set by the `MadeByApp` trait from the family's `workspace_apps` row. It is kept by catalog id, as apps are never named by `app_key` in records. It shows as `made_in` (and `made_by` on documents), loaded with the record's `SHOWN_WITH`.
- A document's `exchange_rate_date` and `exchange_rate_source` (`business` or `transaction`, `ExchangeRate::SOURCE_*`) come from `ExchangeRateService::originOf`. They are worked out in each header builder and when `SalesDocumentFlow::issue` swaps in the current rate. `SalesConversionService` and `CreditNoteService` copy them from their source.
- A number typed by hand (Doc 14 Phase E2) is checked by `DocumentNumbers::typed()`: the `documents.number` permission, then the type's `numberTaken` repository query. `SalesDocumentFlow::issue` (and `CreditNoteService::issue` through it), `PurchaseFlow::record` and `ExpenseFlow::record` put it on in place of `next()`. On the frontend, `useRecording` sends it again with a duplicate warning's answer.
- The attachment limits are the `files_attachment_types` and `files_attachment_max_mb` settings, read through `App\Services\Files\FileLimits`. It narrows `FileCollection::Attachments` only, never past the code's own list or 50 MB. `UploadFilesRequest` checks uploads by it, and `GET /files/limits` (`FileLimitsResource`) serves it to `useFileLimits()`. The settings tab needed `files` in `SettingRepository::getGrouped`'s list of shown groups.
- `FileRemovalRule` decides who may remove a settled record's file, from the record's class and status. `RecordFileService::remove` asks it first, so every attachment controller follows it. Pages pass `can-remove` to `RecordFilesPanel` with the same rule.
- An admin is any user whose role is Super Admin or not a website account's (`User::isAdmin`, `Role::FRONTEND_SLUGS`: user, recruiter, candidate, affiliate). Website roles never appear in Admin > Roles, and a role's slug never changes after it is made, since renaming a website role would make its holders admins.
- The admin sign-in (Doc 15 Phase A1) is `AdminLoginService`: the password with `RateLimiter` (key `admin-sign-in:` and a hash of the address and IP), then a code step held in the session (`admin.sign_in_challenge`) using `TwoFactorService::passes`. An admin's two-factor uses the same `users.two_factor_*` columns as the website's, under the issuer "izSuite Admin" (`AdminTwoFactorService`).
- An admin session is `AdminSessionService`, checked by `AdminMiddleware` on every admin request:
  - `admin.signed_in_at` against `users.sessions_valid_from`, which `endAll()` sets;
  - `admin.last_seen_at` for the 30 idle minutes;
  - `admin.setting_up_two_factor` until two-factor is on.

  Only sessions begun at the sign-in page carry them, so a test's `actingAs` is held to the account's status only. In a test that signs in for real, call `$this->app['auth']->forgetGuards()` before a request that must see a changed account: the guard keeps the user it loaded.
- Admin sign-ins are `admin_sign_ins` (migration 000125): `succeeded`, `failure` (password, switched_off, two_factor, throttled), IP address, browser, and `device`, a hash of the browser's `izsuite_admin_device` cookie (a random id, kept a year).
- The two-factor set-up's QR code is drawn by `public/vendor/qrcode/qrcode.min.js`: node-qrcode 1.5.4 (MIT, its licence beside it), bundled with esbuild from the frontend's own copy. To rebuild it: `docker exec izsuite-frontend node_modules/.bin/esbuild node_modules/qrcode/lib/browser.js --bundle --minify --format=iife --global-name=QRCode --outfile=/tmp/qrcode.min.js`, then `docker cp` it over.
- `PermissionCheckMiddleware` lets every admin open the Overview and their own profile, password and two-factor pages, whatever their role. A refusal is written to the audit log.
- The admin audit log (Doc 15 Phase A2, migration 000126) is `admin_audit_logs`, written by `AdminAuditLogService::record`. `ActivityLogService::log` sends it whatever an admin does, or whatever is done to an admin's account, so admin services keep calling `ActivityLogService::record` as before. Entries keep `admin_name` and `role_name` as they were. The model throws on update and delete, so cleaning dev test data takes `DB::table('admin_audit_logs')`.
- `SettingChanges::isSecret()` leaves out any setting whose name holds password, secret, token, api_key, credentials or recovery_codes, for business logs as well as the audit log.
- A critical action calls `CriticalActionService::confirm($admin, $password, $reason, $what)` before it changes anything. Its fields are `confirm_password` and `reason`, drawn by `admin/partials/critical-confirm.blade.php`, which `<x-confirm-modal>` takes in its slot. On success it puts the reason and approval on the request (`approvedWithPassword`), so every audit entry of that request keeps them.
- The starting admin roles are `App\Support\AdminRoles`, by route name: made by migration 000126 where roles exist already, and by `RoleSeeder` after the Super Admin, who must hold id 1.
- A business's status (Doc 15 Phase B1, migration 000127) is `workspaces.status`, named in `App\Support\BusinessStatus`, with each change in `workspace_status_changes`. Only `BusinessStatusService` changes it.
  - `ResolveWorkspace` asks its `refusal()` on every business request. Suspended or Closed: a 403 with `business_status` for everything but `api.navigation` and `api.notifications.*`. Restricted or Under Review: a 403 for anything but a GET, except the notifications'.
  - `/navigation` gives `workspace.status`: null while Active, and the reason and reactivation for the owner only. `NavigationBuilder` adds it after the cache, so a change shows at the next load.
  - In the frontend, `layouts/dashboard.vue` shows `BusinessStatusShut` in place of the page when `status.shut`, else `BusinessStatusBanner` above it. It asks a shut business for no lists.
  - `businesses:close-due` runs hourly (`routes/console.php`). It closes a business still Closure Scheduled whose latest change's `closes_on` is today or past. A date column is compared with `whereDate`, since SQLite keeps it as text with a time.
- izSuite's notices go through the notification kind `izsuite` (Messages from izSuite, locked on): `izsuite.status` for a status change, `izsuite.message` for Send a Message. The owner's e-mail, `BusinessNoticeMail`, is queued apart from them, as a locked kind never e-mails through `Notifier`.
- `BusinessRepository`'s "At or past a limit" filter puts HAVING on the counts' aliases, grouped by `workspaces.id` and `packages.id`: SQLite needs a GROUP BY for HAVING, and MySQL's full group-by mode accepts those keys.
- Support's actions on an account (Doc 15 Phase B2) are `AccountSupportService`, behind `ClientAccountController`; each is its own route, so a role is given them one by one.
  - A locked account is `users.status` "locked" (`User::LOCKED`). Every check for "active" refuses it already; `AuthService` gives it its own message.
  - A failed sign-in to a known website account is an activity entry, `login_failed` (`AuthService::FAILED_SIGN_IN`), against the account. `ClientRepository` counts them within `AccountAlerts::DAYS` as a subquery, beside `shared_phone`; `AccountAlerts` turns both into the alerts.
  - A reset ticket keeps its own end in `password_reset_tokens.expires_at` (migration 000128): 15 minutes when bought with the e-mailed code, 60 for support's link (`PasswordResetService::issueLink`). The website's `reset-password` page reads `email` and `token` from the link and then drops them from the address.
  - The user's notices are `AccountNoticeMail`, queued, with a button when there is a link.
- The admin-activity card on a business's and a user's page is `admin/partials/admin-activity`.
- Support cases (Doc 15 Phase B3, migration 000129) are `tickets`, with `workspace_id` for the business. The app reaches them through `/support-cases` (`SupportCaseController`, `SupportCaseService`, `SupportCaseRepository`), open to every member (`RoutePermissions::MEMBER`); `BusinessStatusService::ALWAYS_OPEN` keeps them open, with the notifications, whatever the status.
  - `ticket_messages.by_staff` says who wrote a message. Only staff's is printed as markup in the admin; everyone else's is escaped. `SupportCaseDetailResource` turns staff's markup into text with `App\Support\HtmlText`, and the app shows every message as text.
  - Staff's replies and a case resolved or closed reach its opener through `SupportCaseNotifier`: the bell (`izsuite.case`) and `AccountNoticeMail`.
  - Admin activity on a case keeps `workspace_id` in its properties, so the business's page shows it.
- The dashboard layout shows Support's pages (`/support...`) whatever the business's status, reading the path from `useRouter().currentRoute`.
- Support sessions (Doc 15 Phase B4, migration 000130) are `support_sessions`, worked by `App\Services\Shared\SupportSessionService`; what a session may do is `App\Support\SupportAccess`.
  - A session's API token belongs to the business's owner, named `izsuite-support:{id}`, and expires with the session. The link's one-time code is in the cache for a minute.
  - `SupportSessionGuard`, in the api group, reads the token through the sanctum guard before each route's auth. It answers 401 for a session ended or past its end, and 403 for what `SupportAccess` closes. It sets the session on the request: `SupportAccess::current()`.
  - With a session on the request: `ResolveWorkspace` keeps to its business; `WorkspaceAccess::allows()` refuses `SupportAccess::WITHHELD` (the full payment details), so they show masked; `WorkspaceService::list()` gives that business only; and `ActivityLogService::log()` writes the entry with no user and `properties.support` (session, admin), which the log resources show as "izSuite support (name)".
  - Entries written outside a session's own request - its start, its end - go through `ActivityLogService::recordSupport()`, straight into the business's log.
  - Signing out on a session (`AuthService::logout`) ends it, every token of it. `support-sessions:close-expired` records the end of those past their time, every five minutes.
  - In the frontend, `useAuth().supportSession` comes from the sign-in answer and `/auth/me`. `SupportSessionBanner` shows it; entering one sets the workspace pick cookie to its business.
- Admin > Subscriptions (Doc 15 Phase C1) is `Admin\SubscriptionController`, `SubscriptionAdminService` and `SubscriptionAdminRepository`. Each change goes through Doc 13's own billing (`SubscriptionChanges`, `SubscriptionBilling`, `SubscriptionCycle`, `CardPayments`), so it does what the business's own would.
  - `App\Support\SubscriptionStatus` names a subscription's status as the admin shows it: Cancellation Scheduled first, then Past Due, then Downgrade Scheduled, else the stored status. Past Due is active with its renewal open and the card's last try failed or waiting for the cardholder, as the app says it. The repository's status filter follows the same order.
  - `AdminAuditLogService::withReason($reason)` puts a reason on the request without a password, so every audit entry of that request keeps it, those Doc 13's services write included.
  - `admin/partials/reason-modal` is a dialog asking a reason, with optional fields above it; the users' page uses it too.
  - izSuite's notices about a subscription are `izsuite.subscription` in the bell, with `AccountNoticeMail` to the owner.
  - The trial's extension voids its invoice and issues it again (`SubscriptionBilling::issue`, the credit kept); `SubscriptionBilling::resend()` sends an invoice again, as its receipt once paid.
- Payments and refunds (Doc 15 Phase C2a, migration 000132):
  - `SubscriptionBilling::confirm()` keeps a confirmed transfer as a `subscription_payments` row (`SubscriptionPayment::TRANSFER`); a card's is kept by `CardPayments` as before. `SubscriptionPayment::method()` reads "Visa ending 4242" or "Bank transfer"; `notApplied()` is taken but not applied to its invoice.
  - `subscription_refunds` is worked by `App\Services\Billing\SubscriptionRefunds`. `refund()` holds the refund against the locked payment before asking the provider; `settle()` takes the provider's answer and its notifications once, finding a refund by Stripe's reference, else by `metadata.refund_id`, else keeping one made in Stripe's dashboard by its payment intent. `(provider, reference)` is unique; a transfer's bank reference is `transfer_reference`.
  - `CardGateway::refund()` and `RefundOutcome`: Stripe's `POST /v1/refunds` with an idempotency key `izsuite-refund-{id}`. `notified()` also answers a `RefundOutcome` for `refund.created`, `refund.updated`, `refund.failed` and `charge.refund.updated`, read back from Stripe.
  - The admin side is `PaymentAdminService` over `PaymentAdminRepository`, behind `PaymentController` and `RefundController`. Refunding goes through `CriticalActionService::confirm()`; the amount is checked first, so a refund that cannot be made never asks for the password.
  - `SubscriptionBilling::refunded()` tells the business: `SubscriptionNoticeMail` kind `refunded`, and the bell's `subscription.refunded`, one notice per refund.
  - Invoices carry `refunded` (`withSum` of the refunds done) in the admin list, the invoice's page and the app's Billing History (`SubscriptionInvoiceResource`).
- izSuite's credit (Doc 15 Phase C2b, migration 000133) is the ledger `subscription_credits`, worked by `App\Services\Billing\SubscriptionCredits`: `granted` and `returned` add, `used` and `removed` subtract, and the credit in a currency is their sum.
  - `SubscriptionBilling::quote()` takes the credit off before tax as `adjustment`, up to what is left after an upgrade's credit; `issue()` does it again with the credit locked, keeps it on the invoice (`subscription_invoices.adjustment`) and records it as `used`. A total of zero with an adjustment is confirmed at once, paid with `SubscriptionBilling::PAID_WITH_CREDIT`, and no payment row is made for it.
  - Every voided invoice goes through `SubscriptionBilling::cancelInvoice()`, which gives its adjustment back: `void()`, an upgrade paid, the cycle ending a plan, and an upgrade left unpaid at the period's end.
  - `SubscriptionInvoice::net()` is the price less both credits: an upgrade's credit for the unused days counts what was really paid.
- Admin > Billing Reports is `BillingReportService` over `BillingReportRepository`, behind `BillingReportController`. It groups by month with the database's own words (`DATE_FORMAT` in MySQL, `strftime` in SQLite, for the tests), over the times as stored (UTC).
- Plan prices (Doc 15 Phase C3a, migration 000134) are `package_prices`, worked by `App\Services\Billing\PackagePricing`: `currencyFor()`, `price()` (the catalog's, for a business on a day), `renewalPrice()`, `add()` and `remove()`. `packages.price_currency` is the main currency; `price_monthly` and `price_yearly` are gone.
  - `SubscriptionBilling::price()` and `quote()` take the business; `quote()` and `issue()` take a renewal's own price (`$price`). `PublicCatalog::packages($workspace)` prices the plans in the business's currency.
  - A subscription keeps `currency` and `price`, set from its first invoice at subscribing, from an upgrade or first payment confirmed (`SubscriptionBilling::pricePaid()`), and from the renewal when a move down takes effect. `SubscriptionCycle::renewal()` charges `renewalPrice()`. A new price applied is `next_price` from `next_price_from`, taken as `price` when the first renewal on or after that day is issued. `WorkspaceSubscription::paysIn()` is its currency, else its plan's.
  - Applying a price is `PackageService::applyPrice()`: `CriticalActionService`, then `WorkspaceSubscriptionRepository::payingOther()`, then `SubscriptionNoticeMail` kind `price_change` through `SubscriptionBilling::notice()`.
  - In the tests, a plan's prices come from `Tests\Concerns\PricesPlans::priced()`.
- Admin > Overview (Doc 15 Phase D1) is `OverviewService::dashboard()` over `App\Repositories\Admin\OverviewRepository`. Its business filters are `BusinessRepository::narrow()`, which Admin > Businesses uses too (with an app filter it does not show yet); Past Due is `SubscriptionAdminRepository::pastDue()`. Website accounts are those with `ClientRepository::ROLE`. Jobs waiting is `Queue::size()` on the default queue: "Queue not reached" when it cannot be asked.
- Admin > Service Health (Doc 15 Phase D3a) is `ServiceHealthService`, over `App\Services\Admin\Health\PlatformChecks` and `ActivityChecks`, which read through `ServiceHealthRepository` and `ServiceEventRepository`. Each check gives an `App\Support\ServiceStatus`.
  - A queued job may be run again there only when its class implements `App\Jobs\Concerns\SafeToRetry`. Mark a new job so only when running it twice cannot send, add or charge twice.
  - The heartbeats are in the cache (`Heartbeats`), so clearing the cache forgets them until the next minute.
  - `StripeGateway` records its failed calls and `CardPayments::notified()` each webhook, as `ServiceEvent`s.
- An app's statuses (Doc 15 Phase E2) are `Application`'s constants; `PUBLISHED` is stored as `available`. The Apps Center and the website list `Application::listed()` (Listed, and neither Draft nor Retired). `AppsCenter::edition()` and `ActivateApp` decide what can be turned on, the same way. Retiring is `AppService::retire()` with `WorkspaceAppRepository::deactivateEverywhere()`.
- Feature flags (Doc 15 Phase E1) are `App\Services\Shared\Features` (`Features::enabled()`, `for()`) over `FeatureFlagRepository`, run in the admin by `FeatureFlagAdminService`, which calls `Features::forget()` on every change. The share a business falls in is `Features::bucket()`: crc32 of the key and the business's ID.
- Where a plan is offered (Doc 15 Phase E3a) is `App\Services\Billing\PlanAvailability::ensure()`, which `SubscriptionService::payable()` (subscribing and the quote) and `SubscriptionChanges::preview()` (and so `apply()`) call. The billing country is `PlanAvailability::countryOf()`, from the billing details. `PublicCatalog::packages($workspace)` leaves out the plans not offered where the business is billed, keeping its own, and nulls the price of a cycle not offered. Renewals do not ask, so a subscriber keeps its plan and cycle.
  - The plan form sends each cycle's switch as 0 or 1 (a hidden 0 before the box), so a request without them leaves the cycles as they are. `UpdatePackageRequest::after()` keeps one at least.
- Plan versions (Doc 15 Phase E3b, migration 000142) are `package_versions`, worked by `App\Services\Billing\PlanVersions` (`add()`, `start()`, `startDue()`, `remove()`, `history()`) over `PackageVersionRepository`. `PackageService::update()` splits the form: `PackageVersion::TERMS` go into a version when `PackageVersion::differ()` finds a change, the rest update the plan at once. A version started today updates the plan in one change with the rest of the form, so the audit entry has every setting.
  - The plan's own columns are always its current version's terms; `applied_at` marks a version started.
  - `workspaces.package_version_id` holds a business to a version: set only by `PackageVersionRepository::holdSubscribers()` as a newer version starts, cleared by `WorkspaceRepository::setPackage()` when the plan changes. Null means the plan's terms as they stand.
  - Read a business's terms with `Workspace::terms()` (the held version, else the plan), or `WorkspaceRepository::termsOf()` afresh. In SQL, `App\Repositories\Concerns\JoinsPlanTerms` joins `packages` and the held version (`held`), and `term('user_limit')` reads one. Reading `packages.user_limit` or `$workspace->package->user_limit` for a business's limit is a bug now.
- Announcements (Doc 15 Phase F1, migration 000143) are `App\Services\Shared\Announcements` (`for()`, `forAdmins()`, `dismiss()`) over `AnnouncementRepository`, run in the admin by `AnnouncementAdminService`. The live ones are read in full and matched to the business in PHP: there are few at a time. The admin panel's banner comes from `App\View\Composers\AdminAnnouncementsComposer` on `layouts.admin`; `admin.announcements.dismiss` is open to every admin in `PermissionCheckMiddleware`. The frontend's banner is `AnnouncementBanner` with `useAnnouncements()`.
- E-mail templates (Doc 15 Phase F2, migration 000144): the e-mails that can be written are `App\Support\EmailTemplateKinds` (key, name, group, mandatory, variables with samples, the built-in button's words). A mailable joins by using `App\Mail\Concerns\UsesEmailTemplate` and giving `templateKey()` and `templateVariables()`; its `envelope()` and `content()` take `templated()` first and fall back to their own view. The words come from `App\Services\Shared\EmailTemplates::for()` and render in `emails.template`. To add an e-mail: an entry in `EmailTemplateKinds`, then the trait on its mailable.
- Countries (Doc 15 Phase F3a, migration 000145) are the `countries` table, still read through `App\Support\Countries`: `codes()`, `name()`, `all()` and `codeFor()` as before, and now `find()` and `addressFormat()`.
  - `App\Support\CountryDirectory` is scoped (once per request or queued job). It keeps the table in the cache (`countries:directory`) until a country changes. Before the table exists it reads `Countries::builtIn()`, the list the table started from, which the older migrations need.
  - `RegionalDefaults::for()` and `currency()` read the country's defaults. Their old rules are now `builtIn()` and `builtInCurrency()`, kept for the seed.
  - Versions are `country_versions`, worked by `App\Services\Admin\CountryVersions` as plan versions are. A country's own columns are always its current version's.
  - Addresses print through `App\Support\AddressFormat::lines()`, from `SalesSnapshots::address()`.
- Where a way to pay is offered (Doc 15 Phase F3b, migration 000146) is `payment_methods.countries`, read by `PaymentMethod::offeredIn()`: none chosen, or a business without a billing country, means everywhere.
  - `CardPayments::available($workspace)` and `offeredTo()` gate the overview's `card_payments`, card checkout, saving a card, paying now and the saved card's charges.
  - `SubscriptionBilling::instructions()` takes only the offline methods offered where the invoice's business is billed.
  - Refunds are not limited by country.
- Privacy requests (Doc 15 Phase G1, migration 000147) are `privacy_requests`, worked by `App\Services\Admin\Privacy\PrivacyRequestService` over `PrivacyRequestRepository`.
  - The export is `BusinessDataExport` over `BusinessExportRepository`: one CSV per table in its `OWN` and `CHILDREN` lists, plus `members`; a column whose name holds password, secret, token, credential or remember is never written. To export a new kind of record, add its table there; a child table without an `id` (one that only links two records, such as `sales_team_members`) also names the columns its rows are read in order of. It is built by the `BuildPrivacyExport` job and kept on the `local` disk under `privacy-exports/`.
  - The person asking downloads it through `/privacy-exports/{id}`, a signed link that lasts until the export expires (`URL::temporarySignedRoute`), so it points at the backend's own address (`APP_URL`).
  - An account closure sets `users.status` to `closed` (`User::CLOSED`) and closes each business it owns through `BusinessStatusService::closeNow()`.
- Legal holds (Doc 15 Phase G2, migration 000148) are `legal_holds`, worked by `App\Services\Admin\Privacy\LegalHoldService`. The API's deletions it refuses are its `DELETIONS` list of route names, checked in `ResolveWorkspace` after the business's status; `WorkspaceService::delete()` checks it too. A new route that permanently deletes a business's records belongs in that list.
- Consent records (Doc 15 Phase G3, migration 000149) are `consent_records`, written only by `App\Services\Shared\ConsentRecords` (`acceptedTerms()`, `setMarketing()`). `users.marketing_consent` is the choice as it stands; set it only through `setMarketing()`, so each change is recorded. Sign-up and invitation sign-up call it, and so does the profile when `marketing` is sent.
- Data retention (Doc 15 Phase G3) is `App\Services\Admin\Privacy\RetentionRules::rules()`: a list written to match the code. When a new prune or deletion is added, add its row there. The export's days are the `privacy_export_days` setting.
- Incidents (Doc 15 Phase D3b) are `IncidentService` over `IncidentRepository`. `ServiceStatus::NAMES` is the one list of services, for Service Health's checks and an incident's service. Who may be made responsible is `StaffRepository::active()`.
- Admin > Usage (Doc 15 Phase D2) is `UsageService` over `App\Repositories\Admin\UsageRepository`. The repository works each business's usage out in SQL (subqueries, and `greatest` or `max` by database), so the list sorts and filters by it. `App\Support\UsageState` gives the states in PHP, and the repository's filters ask the same in SQL. `BusinessRepository::search()` is Admin > Businesses' search, shared.
- Promotion codes (Doc 15 Phase C3b, migration 000135) are `promotions`, checked by `App\Services\Billing\Promotions::forSubscribing()` (refusing on the `code` field) and worked out by `discountOn()`. Only `SubscriptionService::subscribe()` and `quote()` take a code.
  - `SubscriptionBilling::quote()` and `issue()` take a `Promotion`; the quote has `discount`, `promotion` and `renews_at`. The discount comes off first, then credits, then tax on what is left. The invoice keeps `discount`, `promotion_id` and `promotion_code` (the code as it was), and `SubscriptionInvoice::net()` takes the discount off too.
  - A use is an invoice with the promotion that is not void (`PromotionRepository::uses()`). `subscribe()` checks the code before its transaction, then again inside it with the promotion row locked.
  - A total of zero with a discount and no credit is confirmed at once, paid with `SubscriptionBilling::PAID_WITH_PROMOTION`; with credit too, it is `PAID_WITH_CREDIT`. The receipt PDF words each.
- Dev's admin CSS is `public/build`, git-ignored and built only when missing (`docker/entrypoint.sh`), so it goes stale. After adding Tailwind classes to admin views, run `docker exec izsuite-api npm run build`; a server builds it fresh on deploy.
- The frontend's location pick is `useLocationPick()`. It is shared state behind the `izsuite_location` cookie, and `''` means All Locations. A pick no longer offered (an inactive location, another business's) counts as All Locations. `forNewRecord()` waits for the locations list, so a form opened right after a reload still gets the pick.
- The full Sales menu's order (Doc 16 Phase A) is `NavigationBuilder::FULL_ORDER`, a list of routes; an item not in it follows them by its `sort_order`. Sales Lite keeps `sort_order` alone. A phase adding a sales page adds its menu item in a migration (for existing installs) and in `AppCatalogSeeder` (for new ones).
- The full Sales app's document fields (Doc 16 Phase A, migration 000150) are `SalesDocument::FULL_FIELDS`: `salesperson_id`, `sales_team_id`, `billing_address` and `shipping_address` (JSON with `ADDRESS_PARTS`, null for the customer's own), `expected_delivery_date`, `shipping_method` and `shipping_terms`.
  - `SaveSalesDocumentRequest` drops them while the business is on Sales Lite, so a Lite edit leaves them as they are. Conversions copy them all; a credit note copies the salesperson and team.
  - `SalesSnapshots::customerOf()` is the customer with the document's own addresses. The customer snapshot taken on sending or issuing, the API's `customer` and the PDF all use it.
- Sales teams (Doc 16 Phase A) are `sales_teams` and `sales_team_members` (`SalesTeamRepository`). The owner counts as a member (`isMember()`, `activeMemberIds()`) whether or not the business has a `workspace_members` row for them; a business made in a test has none. `GET /sales/salespeople` gives the people and switched-on teams to choose from: the sales form, the Overview's filters and the teams card use it, so it is open to the sales drafting permissions, View Sales Values and Manage Sales Settings.
- The frontend's `useActiveRoute()` treats a menu link with a query (`/contacts?role=customer`) as current only with that query, and then the plain link to the same page (`/contacts`) is not.
- Sales orders (Doc 16 Phase B, migrations 000151 and 000152) are `sales_documents` of type `sales_order`, kind `orders`, with statuses `confirmed`, `on_hold` and `completed`, and `delivery_status`, `invoice_status`, `confirmed_at` and `confirmed_by` beside them. `DocumentType::edition()` says a kind needs full Sales: `DocumentTypes` shows its numbering and template only then, and the `sales.kind` middleware (`EnsureSalesKindEdition`) lets Sales Lite only read them.
  - `SalesDocumentRules::orderActions()` lists an order's actions; `AllowedActions::sales()` drops them all on Sales Lite, and `convert_to_sales_order` from quotations and proformas. Confirm, hold, resume and complete are `SalesDocumentFlow`'s; Amend is `SalesDocumentService::amend()` (`PUT /sales/orders/{id}/amend`, checked by `SalesOrderAmendments`); Duplicate, Create Proforma and Create Invoice are `SalesConversionService`'s.
  - An order's lines keep their ids through edits (`SalesDocumentRepository::syncLines`; the form sends each line's `id`), as invoice lines link to them. Every other document's lines are replaced on saving, keeping only `source_line_id` links to its own source's lines (`SalesDocumentService::sourceLinks`).
  - `SalesOrderInvoicing` works out what each line has left (`lines()`), checks invoicing beyond (`check()`, and `checkIssue()` before an invoice from an order is issued) and counts an order's invoiced and credited quantities again from its issued invoices and their issued credit notes (`refresh()`, after each issue and void). `SalesOrderProgress` gives the delivery and invoice statuses: a line needs delivering unless its item is a service.
- A permission group for a full edition only has a fourth element, `'full'` (`Permissions::GROUPS['sales.orders']`). It counts with "{family}:full" among the families (`WorkspaceApps::permissionFamilies()`, which roles and new businesses use). `WorkspaceApps::requestHas('sales', Edition::Full)` is the check for a response that shows a full-only field (the invoice policy on items and Settings → Sales).
- Deliveries (Doc 16 Phase C, migration 000153) are `SalesDelivery` and `SalesDeliveryLine`, numbered as `DocumentType::Delivery` (DN-), which has no template of its own (`hasTemplate()`): `DocumentTypes::for($workspace, templated: true)` leaves it out of Settings → Documents' templates, and its PDF uses the order's. `SalesDeliveryService` checks quantities against what is left (`left()`), and after each delivery counts the order's delivered quantities again (`refreshOrder()`); `SalesDeliveryRules` lists a delivery's actions, and `AllowedActions::deliveryNote()` filters them. A delivery line refers to its order line, restricted on delete.
- Customer advances and applied credit (Doc 16 Phase D1, migration 000154) are `CustomerAdvance` (numbered as `DocumentType::CustomerAdvance`, ADV-, no template) and `SalesAllocation` (an invoice, and an advance or credit note, with an amount; removed ones stay). `InvoiceSettlement::settle()` takes active allocations off an invoice after its payments and before its own credit notes, and a credit note's allocations elsewhere off what it can apply to its own invoice; both keep the sum in `sales_documents.allocated_amount`. `SalesAllocationService` applies and takes off credit, settling the invoice and the credit note's own invoice, or refreshing the advance; `CustomerAdvanceService` records, keeps an overpayment's extra (`keepExtra()`, inside `SalesPaymentService::record()`'s transaction) and reverses. `AllowedActions::sales()` drops `apply_credit` on Sales Lite and `record_advance` while Settings → Sales has advances off.
- Deposit invoices (Doc 16 Phase D2, migration 000155) are invoices of their order (`source_id`) with `sales_documents.is_deposit`; a deduction is a negative line with `sales_document_lines.deposit_invoice_id`. `SalesOrderDeposits` works out a deposit's amount, what is left of it (`leftOf()`, counting drafts), the deduction lines a new invoice takes (`deductions()`), checks deductions saved from the form (`checkLines()`, from `SalesDocumentService::create()` and `update()`) and on issuing (`checkIssue()`, from `SalesOrderInvoicing::checkIssue()`), and what an order with deposits bills (`billsPast()`). `SalesCalculator` leaves lines marked `deduction` out of the document discount. `SalesConversionService::depositOrder()` makes the draft.
- Returns (Doc 16 Phase E, migration 000156) are `SalesReturn` and `SalesReturnLine` (an order line, its quantity and warranty), numbered as `DocumentType::SalesReturn` (RET-, no template). `SalesReturnService` checks quantities against what was delivered (`left()`, from a delivery no more than it took), takes a return through its steps and, once received, counts the order lines' returned quantities again (`refreshOrder()`); `SalesReturnRules` lists its actions, `AllowedActions::salesReturn()` filters them, and `SalesDocumentRules` offers `create_return` only on an order with something delivered. `SalesDeliveryLinesInput` takes words of its own for the return's form.
- Price lists (Doc 16 Phase F1, migration 000157) are `PriceList` (with `price_list_contacts` and `price_list_locations` when not for all) and `PriceListRule`. `PriceListPricing::quote()` prices lines (`POST /sales/prices`); `sales_document_lines.price_list_id` and `price_list_rule_id` keep what priced a line, checked as the business's by `SaveSalesDocumentRequest` and copied by conversions. In the frontend, `useLinePricing()` (from `useSalesDocumentForm`) asks for prices while a line's `priceAuto` holds; typing a price clears it (`SalesFormLine`).
- Discount limits and hand-typed prices (Doc 16 Phase F2, migration 000158) are `workspace_roles.max_discount` and `sales_documents.discount_reason`, checked by `SalesPricingChecks::check()` from `SalesDocumentService::create()` and `update()` on full Sales. The form's defaults carry `max_discount` and `prices_by_hand`, which `SalesFormLine` uses to make an item's price read-only.
- Credit control (Doc 16 Phase G1, migration 000159) is `CustomerCredit`, with `CustomerCreditRepository` and `GET`/`PUT /sales/customers/{id}/credit` (`sales.values.view`; `sales.credit.manage` to change).
  - A customer who may not buy on credit (`credit_sales` off) has a limit of nothing; no limit and credit sales on means nothing is checked.
  - Exposure, in the base currency: Outstanding Invoices' rows for the customer as of today (`base_outstanding`); plus, with `sales_settings.credit_counts_orders`, each confirmed or held order's `base_grand_total` less its issued invoices' (a subquery on `source_id`); less unapplied credit. An advance's `exchange_rate` is to its payment account's currency, so its base value is `balance × base_amount / amount`; a credit note's is `balance_due × exchange_rate`.
  - `check()` runs in `SalesDocumentFlow::issue()` (at the rate it is issued at, after the rate question) and `confirm()`. A document is checked only when it adds to the exposure: an invoice adds its base total, less what a counted order of its still had uninvoiced; an order adds its total only while orders count.
  - Warn returns the message, which the controller adds after "Invoice … issued." from `SalesDocumentFlow::$creditNotice`. Block throws on `credit_reason` unless the user has `sales.credit.override` and gave one; then the reason goes on the document and a second Activity Log entry ("… over its customer's credit limit, because: …").
  - The frontend's `SalesCreditModal` opens from `SalesDocumentActions` when issuing or confirming fails on `credit_reason`, and sends the action again with the same rate and number. The status tables it used moved to `utils/salesDocuments.ts` (`PRIMARY_ACTIONS`, `REASONED_ACTIONS`, `CONFIRMED_ACTIONS`, `MAKING_ACTIONS`) to keep the component under 200 lines.
- The customer's link (Doc 16 Phase G2, migration 000160) is `CustomerResponses`, with `customer_responses` (the link, the document as `linkable`, `kind` accepted, rejected or comment, `name`, `body`, `ip_address`, `user_agent`).
  - The page `/d/{token}` is server-rendered: `DocumentRenderer::sales()` takes `$screen` (`CustomerResponses::screen()`, null on Sales Lite) and `documents/partials/respond.blade.php` draws plain forms posting to `/d/{token}/accept`, `/reject` and `/comment` (web routes: session and CSRF; `throttle:customer-responses`, 20 an hour per link and address). The page's policy allows `form-action 'self'`; it still has no scripts.
  - Answers lock the quotation, check `SalesDocumentRules::allows(…, 'accept')`, and set Accepted, or Rejected with `status_reason`. A refusal is a `ValidationException` on `response`, so the page comes back with it.
  - Each is logged with `ActivityLogService::recordCustomer()` - put down to no one, whoever is signed in to that browser - and sent through `Notifier` (kind `customers`) to those with the document kind's View at its location.
  - Expiry: `document_links.expires_at`, set by `DocumentLinks::ensure()` from `sales_settings.link_expiry_days` on full Sales. `works()`, `DocumentLinkRepository::activeFor()` and `SalesDocument::activeLink()` leave out an expired link, so sharing again makes a new one.
  - The frontend's `useSalesSettingsForm` leaves out `FULL_ONLY` settings on Sales Lite.
- The rest of Settings → Sales (Doc 16 Phase H1, migration 000161) is `sales_settings`' `new_sales_start_as`, `default_price_list_id`, `deposit_percent`, `backorders`, `default_shipping_terms` and `salesperson_is_creator`.
  - What pages act on rides in the navigation: `workspace.sales` (`start_as`, `deposit_percent`, `backorders`; null on Sales Lite), built by `NavigationBuilder::salesChoices()`. `SalesSettingService` bumps `nav_version` when one of those three changes, and the settings form reloads the navigation after saving.
  - `useNewSale().startKind` is what the Dashboard's and a contact's New Sale open, and what New Sale lists first: the chosen kind while the user may make one, else an invoice.
  - `PriceListPricing::quote()` appends `PriceListRepository::fallback()` - the default list, active, in the currency and on the date, whatever its customers and locations - after the eligible lists.
  - `SalesDeliveryService::deliver()` overrides the request's `backorder` with `always` or `never`; `SalesFormService::defaults()` adds `shipping_terms` and `salesperson_id` on the full app.
  - The Dashboard's `sales.pipeline` is `DashboardService::pipeline()`, each figure null without its kind's View.
- The "Sales by" reports (Doc 16 Phase H2a) are `ReportCatalog::REPORTS` entries of `SalesSummaryReport` that need `sales_full` (the full Sales app on, and sales seen):
  - `ReportCatalog::GROUPED` gives each its grouping, which `ReportService::filters()` uses whatever is asked. The report takes its title from the entry (`report_name`).
  - `namesFor()` marks them `edition` `full` for the app's page.
  - Sales Summary's full-app groupings are `SalesSummaryReport::FULL_GROUPS`, which `ReportService` refuses on Sales Lite. The frontend's `reportGroups(key, fullSales)` adds them.
  - Category and brand come from `SalesReportRepository::issuedLines()`'s joins to the line's item now; salesperson, team and source from the document (`issued()` loads them, `issuedLines()` joins them).
- The Discount Report, Price List Usage and Estimated Sales Margin (Doc 16 Phase H2b) are `DiscountReport`, `PriceListUsageReport` and `SalesMarginReport`:
  - `ReportCatalog::FULL_SALES` maps what the full app's reports need to a permission more: `sales_costs` takes `items.costs`, `sales_lists` takes `sales.price_lists.view`, and `sales_full` none. All need the full app on and sales seen.
  - `SalesReportRepository::discounted()` gives invoices with a line or invoice discount, with `line_discounts` summed. `issuedLines()` takes `priced` and carries each line's `price_list_id`, `price_list_rule_id` and its item's `purchase_price`; `priceLists()` loads lists with their rules.
  - `Report::percent()` gives a share to one place, or null of nothing, for `percent` columns.
  - `PriceListRepository::replaceParts()` updates a rule sent with its id (`PriceListResource` now gives it), adds the others and deletes the rest.
- The quotation reports (Doc 16 Phase H3a) need `sales_quotes` (`sales.quotations.view` besides sales seen):
  - `QuotationReport` groups `SalesReportRepository::quotationsOut()` (not drafts or cancelled ones) by `QuotationReport::GROUPS` and counts where each stands; `QuotationPerformanceReport` and `QuotationConversionReport` lay it out. Expired is sent with its expiry before the filters' `as_of`, which is today unless asked.
  - `ExpiredQuotationsReport` lists `expiredQuotations()`: draft or sent, dated by the as-of day and expired before it.
  - The frontend's `reportGroups()` gives the two summaries `QUOTATION_GROUPS`.
  - A report's rows reach the page with only their columns' keys and `link` (`ReportTableResource`), so a money column in each row's own currency needs a `currency` column beside it.
- The order reports (Doc 16 Phase H3b) need `sales_orders` (`sales.orders.view` besides sales seen); Delivery Performance needs `sales_deliveries` (`sales.deliveries.view` alone, as it shows no values):
  - `OrderReport::left()` works out an order's value, left to deliver and left to invoice from `SalesReportRepository::openOrders()` - whole orders with their lines and each line's item type - with `SalesFigures::lineNetOf()`, which is `lineNet()` for a line loaded with its document.
  - `OrderBacklogReport`, `OrdersToDeliverReport` and `OrdersToInvoiceReport` lay it out; `DeliveryPerformanceReport` reads `deliveredBetween()`, by `delivered_on` and the delivery's own location.
  - The quotation, backlog and delivery summaries share `GroupsSalesDocuments` (by month, customer, salesperson or team, and every month of a range); the frontend's `FULL_SALES_GROUPINGS` gives each its groupings.
- Phase H3c's reports are `ReturnsReport` (needs `sales_returns`, `sales.returns.view` alone, from `SalesReportRepository::returnsBetween()`), `CreditRefundsReport`, `InvoicePaymentsReport` and `CreditExposureReport` (all `sales_full`):
  - `invoicesAsOf()` gives `advanced_by` - what of `allocated_by` came from advances - and takes `issued_from` and `issued_to`.
  - `CustomerCredit::exposures()` gives every customer's figures as `summary()` does, reading each part once for all of them: `CustomerCreditRepository`'s open orders, advances and credit notes take a null customer for everyone's, and Outstanding Invoices' rows carry `contact_id`. Both go through `exposureOf()` and `figuresOf()`.
  - `CreditExposureReport` takes `CustomerCredit` in its own constructor, beside what `Report` takes.
- A Nuxt page for several kinds sets `minEdition` per route: `minEdition: route => (route.params.kind === 'orders' ? 'full' : 'lite')` (`middleware/app-access.global.ts`). The order's own page has none, so it opens read-only on Sales Lite.
- CRM's settings (Doc 17 Phase A1) are `CrmSetting` (`mode`: `leads` or `opportunities`) and `CrmOption` (`kind`: `lead_source`, `lost_reason` or `activity_type`; `key` for a starting one, from `CrmOption::STARTER`; `position`; `is_active`), saved by `CrmSettingService`:
  - `CrmOptionRepository::addStarter()` fills every list once, the first time `show()` finds none. A row of a starting one keeps its `key` for code to recognise, and the service refuses to delete it (409).
  - The Leads menu item's show-if is `crm_uses_leads` (`ShowIfRules`), and saving a changed mode bumps `nav_version`. `NavigationBuilder::FULL_ORDER['crm']` holds Doc 17's order.
  - The permission group is `crm.settings` (Manage Application Settings), which the role templates leave to full access, as they do every `.settings` group.
  - A model whose column has a default needs it in `$attributes` too, or what `create()` returns reads it as null.
- The pipeline (Doc 17 Phase A2) is `CrmPipeline` with its `CrmStage`s (`position`, `probability`, `category` open, won or lost, `is_active`):
  - `CrmPipelineRepository::addStarter()` makes the first one once (`CrmPipeline::STARTER_STAGES`), from `CrmSettingService::show()`. `replaceStages()` updates a stage sent with its id, adds the others and deletes the rest, as price list rules are.
  - `CrmPipelineService` forces won to 100% and lost to 0% (`CrmStage::probabilityFor()`); `SaveCrmPipelineRequest` keeps an open, a won and a lost stage switched on.
- Sales teams are guarded by `app.any:sales:full,crm` (`EnsureAnyAppActive`): any one of the apps on at its edition opens them. `sales_teams.manager_id` is set only when `manager_id` is sent, so switching a team off keeps it. `GET /business-settings/sales-teams/people` gives the team form its people.
- Leads (Doc 17 Phase B1, migration 000164) are `CrmLead` (`crm_leads`), worked by `CrmLeadService` (the list, one lead, the form's options, adding and changing) and `CrmLeadFlow` (assign, contact, qualify, unqualify, archive, reopen). `CrmLeadRules` says which step each status allows, and `AllowedActions::lead()` keeps those the role allows.
  - `LeadVisibility::scopeFor()` gives `CrmLeadRepository` what a user sees: their own leads; with `crm.records.team` also those owned by the people of their teams and those given to their teams (`SalesTeamRepository::teamsOf`); with `crm.records.all` every one; with `crm.leads.assign` the queue (no owner). Always only at their locations (`atLocations`). A lead out of sight is a 404; one at a location out of reach, a 403 (`RouteRecords::CRM_LEAD`).
  - `LeadAssignments` lists who a lead can be given to (members holding `crm.leads.view`, from `NotificationAudience::membersWithPermission`, and the owner), checks the owner can use the lead's location, and sends `leads.assigned` (the `leads` notification kind).
  - `EnsureCrmUsesLeads` (the `crm.leads` middleware) lets the leads' routes only read in Opportunities Only mode, and `AllowedActions::lead()` then offers no steps.
  - `CrmSettingService::start()` starts the lists and the pipeline: Settings → CRM, the lead form's options and adding a list entry all call it.
- `WorkspaceMemberRepository::RELATIONS` loads each member's user with their first and last name, so `$member->user->name` is set.
- A lead's qualification (Doc 17 Phase B2a, migration 000165) is seven columns on `crm_leads` (`CrmLead::QUALIFICATION_FIELDS`). Its interested items are `crm_lead_items` (`CrmLeadItem`: an `item_id` or none, `name`, `quantity`, `position`):
  - `LeadInterests` checks the items sent against the business's and keeps an item's name; `CrmLeadRepository::replaceItems()` replaces them whole, and only when `interested_items` is sent.
  - `crm_settings.qualification_required` lists what qualifying needs, as keys of `CrmLead::QUALIFYING`; null means `CrmSetting::QUALIFICATION_DEFAULT` (the customer need).
  - `LeadQualification::missing()` works out what a lead lacks: Qualify's refusal and the lead's `qualification_missing` both use it.
- `RouteRecords` finds a route's record by the start of its name, the first that fits.
  - A family inside another's prefix goes before it: `api.sales.deliveries.` before `api.sales.`.
  - A route whose `{id}` is another kind of record goes in `ROUTES`: a new delivery takes its order's id.
  - A prefix mapped to null is checked at no location.
  - When a new kind of record gets routes under an existing prefix, add it there, with its model in `RecordLocationRepository`, and in `FileLinkService` if it has files.
- A lead's files, comments and timeline (Doc 17 Phase B2b) are Doc 14's, under `/crm/leads/{id}/attachments`, `/comments` and `/activity`:
  - `CrmLeadAttachmentController` finds the lead through `CrmLeadService::find()`, so it answers only for whoever sees the lead.
  - `DiscussedRecords` knows the `crm` family. Its `DiscussedRecord` carries `sees`: `LeadVisibility::sees()`, for any user, narrows who a comment may mention. `RecordActivityRepository::forLead()` reads the timeline, and CRM has no values permission, so it shows in full.
  - `FileLinkService` opens a lead's file only for someone who sees the lead.
- Converting a lead (Doc 17 Phase C1, migration 000166) is `CrmLeadConversion`, behind `GET /crm/leads/{id}/conversion` (what it offers) and `POST /crm/leads/{id}/convert`:
  - The matches come from Doc 03's `ContactDuplicates`. A new contact goes through `ContactService::create()` with `allow_duplicates`, since its possible copies were shown.
  - The lead row is locked, so it is converted once.
  - Opportunities are `CrmOpportunity` (`crm_opportunities`), made through `CrmOpportunityRepository`. Each keeps its `lead_id`, and a converted lead its `contact_id`, `opportunity_id`, `converted_at` and `converted_by`.
  - `CrmPipelineService` refuses removing a stage that holds an opportunity (`stagesInUse()`); a stage keeps its id when saved.
- Possible duplicate leads (Doc 17 Phase C2, migration 000167):
  - A lead keeps `match_email`, `match_phone` and `match_business`, filled by its model's `saving` hook through `App\Support\LeadMatchKeys` (tidied as `ContactDuplicates` tidies a contact's).
  - `LeadDuplicates` finds a lead's matches: leads by those keys (`CrmLeadRepository::matching()`, as `LeadVisibility` lets the user see them), and contacts (`ContactRepository::matchingAny()`). It also works out the Leads page's flags, three queries for a whole page.
  - Decisions are `crm_duplicate_reviews` (`CrmDuplicateReview`). Between two leads one is kept on each. `CrmLeadDuplicateService` makes and takes them back and logs them, and `CrmLeadConversion` puts a linked contact first.
- Merging contacts (Doc 17 Phase C3, migration 000168):
  - A merged contact has `merged_into_id`, `merged_at` and `merged_by` (`Contact::isMerged()`, relations `mergedInto`, `merger`, `mergedContacts`).
  - `ContactMergeService` checks and merges under row locks. `ContactMergeFields` works out what the kept one gains (pure, no queries). `ContactMergeRepository` counts and moves the records with plain updates, reading what the kept one has first, because MySQL refuses to update a table through a subquery of itself.
  - `ContactService::findChangeable()` / `ensureChangeable()` answer 409 for a merged contact: edit, archive, restore, delete and attachments use them.
  - `ContactRepository::possibleDuplicatesOf()` and `matchingAny()` leave merged contacts out, so Doc 03's warnings, imports, lead matches and conversion do too. `GET /contacts?unmerged=1` is the pickers' list, and CustomerPicker's `unmerged` prop asks for it.
- Opportunities (Doc 17 Phase D1, migration 000169):
  - `CrmOpportunityService` and `CrmOpportunityFlow` (stage moves under a row lock), `CrmOpportunityRules` (open: edit, stage), `AllowedActions::opportunity()`.
  - `OpportunityVisibility` is LeadVisibility's scope without the queue. `OpportunityAssignments` does what LeadAssignments does, under the `opportunities` notice kind.
  - `OpportunityForecast` sets `weighted_value` and `base_weighted_value` on loaded records, one rate read per currency, via `ExchangeRateService::rateOn()` for the business's today. Set on a record, they would be saved with it, so the service's `find()` stays plain and `show()` / `shown()` add them.
  - Each stage it is at is a `crm_stage_visits` row (`CrmStageVisit`, the open one with no `left_at`), made by `CrmOpportunityRepository::enterStage()`. Interested items are `crm_opportunity_items`, checked by LeadInterests.
  - CustomerPicker's `active-contact` prop lists active contacts of any role (`status=active`).
- How an opportunity ends (Doc 17 Phase D2a, migration 000170):
  - `CrmOpportunityClosing` wins, loses and reopens, moving it to the pipeline's first won or lost stage switched on, or back to an open one, under a row lock. `CrmOpportunityFlow` archives and restores.
  - `CrmOpportunityRules`: open is edit, stage, win, lose, archive; won or lost is reopen, archive; archived is only restore.
  - The ending's columns are `CrmOpportunity::OUTCOME_FIELDS`, cleared by reopening. Winning sets `expected_value` to the final value, and the resource's `outcome` and `archived` carry the rest.
  - The list leaves archived ones out unless its status is `archived`.
- An opportunity's records (Doc 17 Phase D2b, no migration):
  - Doc 14's family for it is `opportunities`: the routes' default, in `DiscussedRecords` (with `OpportunityVisibility::sees()` for mentions), `RecordActivityService` / `RecordActivityRepository::forOpportunity()` and `FileLinkService`. The frontend's RecordDiscussion takes `family="crm"`, the app.
  - `CrmOpportunityService::findForFiles()` refuses an archived one. Comments take any status.
  - `ActivityLogService::record()` takes `changes` when given, else works them out from the save (`SettingChanges::of()`). The opportunity's steps give theirs through `App\Services\Crm\CrmOpportunityChanges`, or `[]` for none.
- Stale stages and what a stage needs (Doc 17 Phase D3a, migration 000171):
  - `crm_stages.stale_after_days` (open stages only, else null) and `required_fields`, a JSON list of `CrmStage::REQUIRABLE` keys, which `CrmStage::needs()` gives in that order. `CrmPipelineRepository::replaceStages()` saves kept stages through the model, so the list's cast applies.
  - `App\Services\Crm\StageRequirements::ensure()` checks them wherever an opportunity enters a stage: `CrmOpportunityService::create()`, `CrmOpportunityFlow::changeStage()`, `CrmOpportunityClosing` (win, lose, reopen) and `CrmLeadConversion`. Its refusal is a `stage_id` error (`opportunity.stage_id` on conversion) naming what is missing. `valuesOf()` reads an opportunity's details.
  - Stale is worked out on reading, never stored: `CrmOpportunityService::mark()` sets `days_at_stage` and `stale` from `stage_entered_at`, and the list's `status=stale` is the open ones past their stage's period (`CrmOpportunityRepository::stale()`).
  - `SettingChanges` counts an empty list as none, as it does empty text.
  - The frontend's `STAGE_NEEDS`, `filledNeeds()` and `stageNeeds()` (`utils/crmOpportunities.ts`) mirror the API's check for CrmStageNeedsNote, in Change Stage, Mark Won, Mark Lost and Reopen.
- Several pipelines (Doc 17 Phase D3b, migration 000172):
  - `crm_pipelines.team_ids` and `location_ids` are JSON lists, null for all; `CrmPipeline::fits()` reads them, and `pipelineFits()` in the frontend's `utils/crmOpportunities.ts` mirrors it.
  - `App\Services\Crm\PipelineChoice` decides where an opportunity goes. `choose()` takes the one asked for or the first that fits, else refuses under `pipeline_id` (`opportunity.pipeline_id` when converting). `active()` lists those switched on, and `forForms()` / `described()` give what a form offers.
  - `CrmPipelineService::ensureOneForAll()` keeps one pipeline switched on for every team and location, refusing under `is_active`.
  - Delete only while no opportunity is or was in it: `CrmPipelineRepository::isUsed()` for one, `usedIds()` for the list. The resource's `removable` reads `in_use`, set by `CrmSettingService::show()` and `CrmPipelineService::save()`; a new one has none. The frontend also hides Delete on the last pipeline switched on for everyone.
  - The opportunity options' `pipelines` (whom each is for, with its open stages) replaced `stages`, and `all_stages` carry their `pipeline_id`. The conversion preview's `stages` went the same way. `CrmOpportunityService::pipeline()` is gone.
  - CrmOpportunityFormMain keeps the form's pipeline and stage to ones that fit as its team and location change. `useSalesTeams()` keeps its list in shared state, so Settings → CRM's pipeline cards choose from the teams its Sales Teams card loads.
  - DataPager's `plural` prop, for a noun that does not just add s.
- The board and the calendar (Doc 17 Phase D4, no migration):
  - `CrmOpportunityViews` (behind `CrmOpportunityViewController`, `GET crm/opportunities/board` and `/calendar`, under View Opportunities) builds both. `CARDS_PER_COLUMN`, `CLOSED_DAYS` and `CALENDAR_LIMIT` are its limits; the frontend's `BOARD_CLOSED_DAYS` repeats the 30.
  - `CrmOpportunityRepository::filtered()` is the list's query - visibility, search, status, stage, pipeline, owner - shared by `paginateFor()`, `closingBetween()` (the calendar) and `onBoard()`. `CrmOpportunityService::filters()` ("me", the stale moments) and `mark()` (forecast, overdue, stale) are public for the same reason.
  - The board reads its pipeline's opportunities with a few columns (`onBoard()`), groups and sorts them in PHP for each column's order, count and `OpportunityForecast::total()`, then reads only the cards it shows (`cards()`).
  - A card moves through the existing steps: `POST …/stage` when dropped on an open stage, and Mark Won or Mark Lost (CrmOpportunityWinModal, CrmOpportunityLoseModal) after reading the opportunity, as its menu's Change Stage does with CrmOpportunityStageModal. A modal is opened a tick after it mounts, so its own `watch(open)` sets its defaults.
  - Dragging is HTML5 drag and drop; the card's menu is the way on touch screens. The calendar's weeks are worked out in `utils/crmCalendar.ts`, in UTC.
  - The Opportunities page is a frame (`pages/crm/opportunities/index.vue`) around CrmOpportunityList, CrmOpportunityBoard and CrmOpportunityCalendar, with the options read once for all three.
- Activities (Doc 17 Phase E1, migration 000173):
  - `crm_activities` (`CrmActivity`): one of `lead_id`, `opportunity_id` or `contact_id` when planned - a converted lead's keep `lead_id` and gain `opportunity_id` (`CrmActivityRepository::carryToOpportunity()`, from `CrmLeadConversion`). `regarding()` is the opportunity first. `location_id` is the record's, null for a contact's. `due_at` is UTC; `CrmActivityService::mark()` sets `due_on`, `due_time` (the business's) and `overdue`.
  - `CrmActivityService` (behind `CrmActivityController`) plans, changes, completes (with `follow_up`) and cancels; `ActivityVisibility` decides who sees one - the Activities page by `assigned_to`, a record's card by the record's own visibility - and `ActivityAssignments` who it can be given to.
  - Activity types are `crm_options` of kind `activity_type`, with `needs_outcome` (`CrmOption::NEEDS_OUTCOME` for the starters). The key `call` marks a call, which alone keeps a `call_direction`.
  - Each step is logged on the record (`ActivityLogService::record()` with the lead, opportunity or contact as subject); an edit's changes are made readable (`readable()`): the due moment in the business's time, the type and the assignee by name.
  - Frontend: `useCrmActivities()`, `utils/crmActivities.ts` (`businessNextHour()` for the business's time zone), CrmActivitiesCard on the three record pages, the Activities page, and CrmActivityDialogs - plan, edit, complete, cancel - shared by both.
- Reminders and notices (Doc 17 Phase E2, migration 000174):
  - An activity's `remind_minutes` (`CrmActivity::REMINDERS`, `DEFAULT_REMINDER` 15 when left out), `remind_at` (UTC, null once due when planned) and `reminded_at`. `CrmActivityService::reminder()` works out `remind_at`; a change of moment clears `reminded_at`. Their changes stay out of the timeline's list.
  - `App\Services\Crm\ActivityNotices`: `assigned()` (from planning, changing the assignee and a follow-up) and `remindDue()`, behind `crm:remind-activities` (every minute). `CrmActivityRepository::dueReminders()` reads every business's due ones and `claimReminder()` marks one sent for the first run only.
  - The kind is `activities` in `NotificationKinds` (triggers `activities.assigned` and `activities.reminder`); a notice's subject is the activity. Notification tests that list every kind name it.
- The timeline (Doc 17 Phase E3, no migration) is `CrmTimelineService`, behind `GET /crm/timeline/{kind}/{id}?show=` (`CrmTimelineController`, `CrmTimelineRequest`): `kind` lead, opportunity or contact; `show` all, activities, notes, changes, files or documents. A closure in `RoutePermissions` picks its permission by kind.
  - `CrmTimelineRepository` reads each part up to `SHOWN` (100): `logEntries()` - the Activity Log entries about the records, without those carrying `comment_id` or `activity_id`, `properties->file` telling files from changes - `endedActivities()` and `comments()`; the sales documents come through `OpportunityDocuments::madeFor()` (Phase F1). The service merges them newest first and keeps 100.
  - A contact's gathers `CrmLeadRepository::ofContact()` and `CrmOpportunityRepository::ofContact()`: the leads converted into it and its opportunities that the user sees.
  - `CrmActivityService` puts `activity_id` in each step's log entry, from Phase E3 on.
  - `CrmTimelineEntryResource` shapes each kind. A change's `mark` (won, lost, status, stage, owner) comes from its entry's `status`, `to_stage` and `owner_id`; an activity's `type_key` picks its icon.
  - Frontend: `useCrmTimeline()`, `utils/crmTimeline.ts` (the filters and icons), CrmTimeline with CrmTimelineEntry, CrmTimelineCard on a contact's page, and RecordDiscussion's `timeline` prop on a lead's and an opportunity's. `RecordChangeList` folds an entry's changes, for this timeline and Doc 14's.
  - A lead's and an opportunity's `/activity` routes (Doc 14's record timeline) are still there, though their pages now read this one.
- Sales documents made from an opportunity (Doc 17 Phase F1, migration 000175): `sales_documents.opportunity_id` (`SalesDocument::opportunity()`).
  - `App\Services\Crm\OpportunityDocuments`: `seen()` (one the user sees, while CRM is on), `refusal()` (lost, archived, another contact), `start()` (what a document starts with), `list()` (the card's, behind `GET /crm/opportunities/{id}/documents`, `CrmOpportunityDocumentController`), `madeFor()` (a contact's or an opportunity's documents the user may see - the timeline's too) and `shownLink()` (a document's `opportunity`).
  - `SaveSalesDocumentRequest` checks a new document's `opportunity_id`; `SalesDocumentService::create()` keeps it and `update()` leaves it. `SalesConversionService::header()` carries it; `duplicate()` clears it.
  - `GET /sales/{kind}/defaults?opportunity_id=` adds `opportunity` (`SalesFormService::opportunity()`): its contact as `customer`, its currency, and its `location_id`, `salesperson_id`, `sales_team_id` and `lines`.
  - Frontend: CrmOpportunityDocuments on the opportunity's page, `?opportunity=` in `useSalesDocumentForm()` (`fromOpportunity()`), SalesDocumentAside's Opportunity, and `.alert-info` in `main.css`.
- An opportunity won by its order (Doc 17 Phase F2, migration 000176): `crm_settings.win_on_order` (`CrmSetting`, on by default; `CrmSettingService::winsOnOrder()`), saved with the operating mode (`SaveCrmSettingsRequest`, `CrmSettingsResource`).
  - `SalesDocumentFlow::confirm()` calls `CrmOpportunityClosing::winFromOrder()` once the order is confirmed and logged. It finds the opportunity for the business, whoever confirms (`CrmOpportunityRepository::findInBusiness()`), and wins it as `win()` does - its won stage, 100%, `closed_on` today, the order's `net_total` when the currencies match. What would refuse it - a won stage's needs, a conflict - is swallowed, so the order is confirmed either way. Its log entry carries `sales_document_id`.
  - Frontend: SettingsCrm's checkbox and `useCrmSettings().saveGeneral()` (it was `saveMode()`).
- E-mail from CRM records (Doc 17 Phase F3a, migration 000177): `crm_emails` (`CrmEmail`, its `FOR` as an activity's), `CrmEmailService` behind `GET /crm/emails/draft/{kind}/{id}` and `POST /crm/emails` (`CrmEmailController`, `SendCrmEmailRequest`), and the permission `crm.emails.send`.
  - Sending queues `SendCrmEmail`. `App\Services\Crm\CrmMailer` makes each document's PDF (`DocumentPdf::sales()`), reads each file (`FileStorage::contents()`) and sends `App\Mail\RecordMail` - several attachments, the `emails.document` views - under `EmailSettingService::senderName()` and `replyTo()`. A file gone throws `AttachmentMissing`; `failed()` words the failure.
  - What can be attached: `OpportunityDocuments::madeFor()` less drafts, void and cancelled documents, and the record's `attachments` files.
  - The log entry carries `email_id`, so the timeline (`CrmTimelineRepository::emails()`, its `emails` part) shows the e-mail instead. `ContactMergeRepository` moves `crm_emails`; `ContactService` keeps a contact with any from being deleted.
  - Frontend: `useCrmEmails()`, CrmEmailModal with CrmEmailAttachments, CrmTimelineEmail, and CrmTimeline's `canEmail`.
- E-mail templates (Doc 17 Phase F3b, migration 000178): `crm_email_templates` (`CrmEmailTemplate`, its `PLACEHOLDERS`), `CrmEmailTemplateService` behind `/business-settings/crm/email-templates` (`CrmEmailTemplateController`, `SaveCrmEmailTemplateRequest`, `crm.settings.manage`), and Settings → CRM's `email_templates`.
  - `CrmEmailService::draft()` returns them as `templates`, filled for the record and sender by `placeholders()` and `fill()`.
  - Frontend: SettingsCrmEmailTemplates with CrmEmailTemplateModal (its placeholder chips), `useCrmSettings().saveTemplate()` and `deleteTemplate()`, and CrmEmailModal's Template list.
- A contact's CRM part (Doc 17 Phase F4, no migration): `CrmContactSummaryService` behind `GET /crm/contacts/{id}/summary` (`CrmContactSummaryController`, `CrmContactSummaryResource`, `contacts.view`). It reads `CrmOpportunityRepository::forContactProfile()` and `CrmLeadRepository::convertedInto()` within the user's scopes, and totals with `OpportunityForecast::total()`.
  - Frontend: CrmContactSummary on the contact's page (`useCrmContactSummary()`), and CrmOpportunityForm's `?contact=&contact_name=`.
- The CRM Overview (Doc 17 Phase G1, migration 000179): `CrmOverviewService` behind `GET /crm/overview` (`CrmOverviewController`, `CrmOverviewRequest`, `CrmOverviewResource`; `crm.opportunities.view`).
  - It reads `CrmLeadRepository::overview()`, `CrmOpportunityRepository::forOverview()` and `CrmActivityRepository::overdueCount()` within each one's visibility scope, and totals with `OpportunityForecast::total()` - or sums in the currency chosen.
  - The period's days are the business's: `ReportPeriod::range()`, and for leads' `created_at` its bounds in the business's time zone. `crm_settings.closing_soon_days` (`CrmSetting::MAX_CLOSING_SOON_DAYS`) sets closing soon.
  - Frontend: `pages/crm/overview.vue`, `CrmOverviewFilters`, `CrmOverviewCards` and `useCrmOverview`. The overview cards' classes (`.overview`, `.overview-card`, `.overview-filters`) are in `main.css`, shared with the Sales Overview. The Activities page takes `?status=` and `?assigned_to=`.
- The Forecast (Doc 17 Phase G2, migration 000180, its menu item only): `CrmForecastService` behind `GET /crm/forecast` (`CrmForecastController`, `CrmForecastRequest`, `CrmForecastResource`; `crm.opportunities.view`).
  - It reads `CrmOpportunityRepository::forForecast()` (open ones and those won this month, with owner, team and stage), which shares `narrowed()` - the filters - with `forOverview()`.
  - Groups: `month` (Overdue, 12 months from this one, Later), `owner`, `team`, `stage`; totals with `OpportunityForecast::total()`, or sums in the currency chosen.
  - Frontend: `pages/crm/forecast.vue`, `CrmForecastTable` and `useCrmForecast`. `CrmOverviewFilters` takes no `pick` there, and its slot holds the grouping.
- Response tracking (Doc 17 Phase G3a, migration 000181): `crm_leads.first_assigned_at` and `first_contacted_at` - `assigned_at` and `contacted_at` stay the latest. The migration fills them for leads that exist from the Activity Log (an assignment's `owner_id` and `previous_owner_id`, a marking's `status`), activities done and e-mails.
  - `first_assigned_at` is set by `CrmLeadService::create()` (with an owner) and `CrmLeadFlow::assign()`; `first_contacted_at` by `CrmLeadFlow::contact()`, `CrmActivityService::complete()` and `CrmEmailService::send()` - the last two through `CrmLeadRepository::answered()`, which leaves `updated_at` (the edit version) alone.
  - `App\Services\Crm\RecordFollowUp` gives `follow_up` on `CrmLeadResource` and `CrmOpportunityResource`: `CrmActivityRepository::lastDoneAt()` and `nextPlanned()`, `CrmEmailRepository::lastSentAt()` (from `created_at`, whatever the mail server did). The next activity's `label` only with `crm.activities.view`. `CrmLeadResource` no longer sends `contacted_at`; `assigned_at` stays, for "Given to".
  - Frontend: CrmFollowUpCard on both pages (`utils/crmFollowUp.ts` words the response time and the way of contact). `useCrmFollowUp()`'s `touch()` - from CrmActivitiesCard and CrmTimeline's e-mail - makes the page call its record composable's `refresh()`, a quiet read.
- Follow-up warnings (Doc 17 Phase G3b, migration 000182): `crm_settings.lead_response_days` (2) and `recent_activity_days` (14), `CrmSetting::MAX_FOLLOW_UP_DAYS` 365, saved with the rest (`SaveCrmSettingsRequest`, `CrmSettingService::SAVED` and `periods()`, `CrmSettingsResource`).
  - `App\Services\Crm\FollowUpCutoffs` draws the lines: `staleSince()` (moved from `CrmOpportunityService::filters()`), `uncontactedBefore()`, `quiet()` (closing_from, closing_to, quiet_since).
  - Leads: `CrmLeadRepository::followUpCounts()` (unassigned = status New; uncontacted = open, `first_contacted_at` null, added before the cut-off), and the list's `uncontacted` status (`ListCrmLeadsRequest`; `CrmLeadService::list()` adds `uncontacted_before`). `overview()` and `followUpCounts()` share `narrowed()`.
  - Opportunities: `CrmOpportunity::FOLLOW_UP_STATUSES` (stale, no_activity, quiet). `CrmOpportunityRepository::filtered()` takes them, through `withoutNextActivity()` and `quiet()` (subqueries on `crm_activities` and `crm_emails`); `followUpCounts()` counts all three for the Overview.
  - `CrmOverviewService` adds `leads.unassigned`, `leads.uncontacted`, `follow_up` and both periods (`CrmOverviewResource`). The lead and opportunity options carry the days their filters are named with.
  - Frontend: CrmOverviewFollowUp (the Follow-up group, the Overdue Activities card moved into it; CrmOverviewCards takes `filters` instead of `ownerId`). The Leads page and CrmOpportunityList start from `?status=`, `?owner_id=`, `?source_id=`, `?pipeline_id=`, `?stage_id=`. The toolbars name the new statuses with the days. SettingsCrm's three periods.
- The Dashboard's CRM figures (Doc 17 Phase G4, no migration): `DashboardService::summary()` takes the user and adds `crm` from `CrmOverviewService::dashboard()`, for the Dashboard's period and location (`DashboardResource`). It is there while CRM is on, for `crm.opportunities.view`.
  - `dashboard()` shares the Overview's counting:
    - `leadCounts()`: `CrmLeadRepository::overview()`, its new and awaiting (`CrmLead::AWAITING`);
    - the open ones from `forOverview()`, with `value()` and `closingSoon()`;
    - `activityCounts()`: `CrmActivityRepository::dueCount()` (the business's day, as the Activities page's Due Today) and `overdueCount()`, which share `planned()`.
  - `FollowUpCutoffs::closingSoon()` (closing_from, closing_to) draws the one closing-soon line. It is used by:
    - the Overview's and the Dashboard's closing soon, and `quiet()`;
    - the Opportunities list's new `closing_soon` status (`CrmOpportunity::FOLLOW_UP_STATUSES`; `CrmOpportunityRepository::closingSoon()`). The opportunity options carry `closing_soon_days` again, to name it.
  - The Leads list's `awaiting` status (`ListCrmLeadsRequest`, `CrmLeadRepository::paginateFor()`).
  - The activity figures, the Dashboard's and the Overview's `overdue_activities`, need `crm.activities.view` (`CrmOverviewService::seesActivities()`), and are null otherwise.
  - Frontend:
    - DashboardCrm sits after DashboardPipeline, outside the chart's `v-if`, so it shows without Sales, Purchases and Expenses; its type is `useDashboard`'s `DashboardCrm`.
    - `.dashboard-figures` and `.dashboard-figure` are in `main.css`, shared with DashboardPipeline, at most four across.
    - `pagePermissions` has `/crm/activities` (`crm.activities.view`).
- CRM's lead reports (Doc 17 Phase H1, no migration): `App\Services\Reports\Crm\LeadReport` (abstract, extends `Report`) builds them from `CrmLeadRepository::forReports()`. That is the leads `LeadVisibility` lets the user see, added in the period's business days, narrowed by `narrowed()`.
  - It has `GROUPS` (month, source, owner, team), `grouped()` (every month, or every status in order, else the most leads first) and `share()`.
  - `LeadsByReport` is Leads by Source, Owner and Status (`ReportCatalog::GROUPED`).
  - `LeadResponseReport` reads `first_contacted_at` against `lead_response_days`.
  - `LeadRateReport` is both rates, told apart by `report_key`, which `ReportService::built()` now passes with `report_name`.
  - `ReportCatalog`:
    - the category `crm`;
    - the needs key `crm_leads`: CRM on and `usesLeads()`, with `crm.reports.view` and `crm.leads.view` (the `CRM` map);
    - `namesFor('crm')` lists them for the Apps Center.
  - `RunReportRequest` and `ReportService::filters()` take `sales_team_id`, `owner_id` and `lead_source_id`, and pass `user` (`WorkspaceAccess::user()`).
  - `Permissions`: the group `crm.reports` (View CRM Reports), requiring `reports.view`.
  - Frontend:
    - ReportCrmNarrowing, in ReportFilters, holds the sales team, owner and lead source dropdowns, from `/crm/leads/options`;
    - `useReports` has the new filter keys, `reportQuery()`, and `LEAD_GROUPS` in `GROUPINGS` (which was `FULL_SALES_GROUPINGS`).
- CRM's opportunity, people and activity reports (Doc 17 Phases H2 and H3, no migration), in `App\Services\Reports\Crm`:
  - `OpportunityReport` (abstract) has:
    - `open()` and `closed()`, from `CrmOpportunityRepository::forReports()`: archived ones left out; the open ones (or those without a next activity), or those won or lost between two business days;
    - `narrowing()`;
    - `grouped()`: month, owner, team, source, or stage (every open stage of the shown pipelines, from `openStages()`);
    - `sum()` over `OpportunityForecast::baseValues()`, which gives each opportunity's whole and weighted value in the base currency, each rate read once;
    - `averageDays()` and `unratedNote()`.
  - Built on it:
    - `OpportunitiesByStageReport`;
    - `ForecastReport`: Pipeline Value and Weighted Forecast, from `CrmForecastService::rows()`, told apart by `report_key`;
    - `StageDurationReport`, from `CrmOpportunityRepository::stageVisitsEnded()`;
    - `OpportunityListReport`: Closing Soon (with `FollowUpCutoffs::closingSoon()`) and Without Follow-up (with `CrmActivityRepository::lastDoneByOpportunity()` and `CrmEmailRepository::lastSentByOpportunity()`);
    - `ClosedOpportunitiesReport`, `WinRateReport` and `LostReasonsReport`.
  - `PerformanceReport` is Salesperson and Sales Team (`ReportCatalog::GROUPED`: owner, team). It reads the leads, the closed and open opportunities, and `CrmActivityRepository::completedBetween()`. The lead and activity columns show only with their view.
  - `ActivityReport` (abstract) has `ActivityCompletionReport` (`dueBetween()`) and `OverdueActivitiesReport` (`overdue()`). `CrmActivityRepository`'s counts share `narrowed()` and `planned()`.
  - `ReportCatalog`:
    - the needs keys `crm_opportunities` and `crm_activities` (the `CRM` map);
    - `DEFAULT_GROUPS`: Pipeline Value by stage, which the frontend's `reportDefaultGroup()` mirrors.
  - `RunReportRequest` and `ReportService` take `pipeline_id` and `stage_id`. `Report::share()` is shared by all the CRM reports.
  - A list report has a Currency column. `ReportTableResource` keeps only the columns' keys, so a row's own currency must be one.
  - Frontend: ReportCrmNarrowing takes its choices from a lead's, an activity's (`CRM_ACTIVITY_REPORTS`) or an opportunity's options, with Pipeline and Stage. `GROUPINGS` has the new reports' groups.
- Lead assignment rules (Doc 17 Phase I1, migration 000183):
  - `crm_assignment_rules`: name, position, `is_active`; what a lead must have (`source_id`, `location_id`, `sales_team_id`, `country`, `item_id`); whom it goes to (`user_id`, or `team_id` with `method`, `round_robin` or `workload`); and `last_user_id`, the team's turn.
  - `CrmAssignmentRule` and `CrmAssignmentRuleRepository`. Its `tookTurn()` moves the turn on quietly, and `lock()` holds the row while a lead is saved.
  - `App\Services\Crm\LeadRouting::route()` holds the rules switched on in order:
    - `fits()`;
    - `people()`: `LeadAssignments::owners()` at the lead's location, a team's members only while the team is on;
    - `turn()`: rotated after `last_user_id`, and by workload sorted by `CrmLeadRepository::openCountsByOwner()`, then the turn.
  - `CrmLeadService::create()` routes a lead added without an owner, inside its transaction and after its interested items. `CrmLeadFlow::assignByRules()` (POST `/crm/leads/{id}/assign-by-rules`, `crm.leads.assign`) does the same for a queued one.
  - Settings: `CrmAssignmentRuleService` (`checked()` makes sure each id is the business's own, and the user one of `owners()`), `CrmAssignmentRuleController`, `SaveCrmAssignmentRuleRequest`, `ReorderCrmAssignmentRulesRequest`, and `CrmAssignmentRule(s)Resource`. The routes are under `/business-settings/crm/assignment-rules`, with `crm.rules.manage`.
  - `Permissions`: the group `crm.rules` (Manage Assignment and Scoring Rules, also for I2), requiring `settings.view`.
  - Frontend:
    - SettingsCrmAssignmentRules, with CrmAssignmentRuleModal and CrmAssignmentRuleWhen (the conditions), on `useCrmAssignmentRules()`, whose shared state is `crm:assignment-rules`;
    - SettingsCrm shows its other cards only with `crm.settings.manage`, and the settings page's CRM tab needs either permission;
    - CrmLeadAssignModal's "Use the Assignment Rules" (`LeadStep` `assign-by-rules`).
- Lead scoring (Doc 17 Phase I2, migration 000184):
  - `crm_scoring_rules`: name, `is_active`, `factor`, what it asks (`source_id`, `location_id`, `item_id`, `option` or `threshold`, as `CrmScoringRule::NEEDS` says) and `points`, -100 to 100 and never 0.
  - `crm_leads` gains `score` (0 to 100, null while not scored) and `score_factors`: the fitting rules' names and points, most first. `CrmLead::SCORE_BANDS` holds the bands.
  - `App\Services\Crm\LeadScoring`:
    - `rescore()` scores one lead while it is open;
    - `rescoreAll()` scores a business's open leads in runs of 200 - or, with no rule on, clears every lead's score;
    - `rescoreEveryBusiness()` is `crm:score-leads`' (hourly, for businesses with a rule on and CRM in use).
    - It reads interests, activities done and e-mails sent in bulk (`CrmLeadRepository::interestsOf()`, `CrmActivityRepository::doneByLead()`, `CrmEmailRepository::sentByLead()`) and converts at today's rate.
    - It writes through `CrmLeadRepository::scored()`, quietly (`toBase()`), so a lead's version (EditConflicts) stays, and only where the score or factors changed.
  - It is called from `CrmLeadService::create()` and `update()`, `CrmLeadFlow::move()` (contact, qualify, unqualify, archive, reopen), `CrmActivityService::complete()` and `CrmEmailService::send()`. A rule's change dispatches `ScoreLeads` (queued, safe to retry).
  - Settings: `CrmScoringRuleService` (keeps only what the factor asks, each id the business's own), `CrmScoringRuleController`, `SaveCrmScoringRuleRequest` and `CrmScoringRuleResource`. The routes are under `/business-settings/crm/scoring-rules`, with `crm.rules.manage`.
  - Leads: `CrmLeadResource` has `score` and `score_factors`, `CrmLeadListResource` `score`, and the options `scoring`. `ListCrmLeadsRequest`'s `score` is a band.
  - Frontend:
    - SettingsCrmScoringRules, with CrmScoringRuleModal and CrmScoringRuleAsk (what the factor needs), on `useCrmScoringRules()`, whose shared state is `crm:scoring-rules`;
    - `utils/crmScoring.ts`: the factors, details, responses and bands, and `describeScoringRule()`;
    - CrmLeadScoreCard and CrmLeadScorePill on the lead's page and the Leads list, and CrmLeadsToolbar's Score filter.
- Marketing consent (Doc 17 Phase I3, migration 000185):
  - `marketing_consents`: whose (`contact_id`, `lead_id`, or both once the lead is converted), `purpose_id` (a `crm_options` row of the new kind `consent_purpose`), `channel`, `status` (given, refused, withdrawn), `source`, `recorded_at`, `recorded_by`, `expires_on`, `evidence`, and `withdrawn_at`, `withdrawn_by` and `withdrawal_note`. Rows are never deleted.
  - The migration adds the starting consent purposes to businesses whose CRM lists started already; `CrmOption::STARTER` has them for new ones.
  - `MarketingConsent` and `MarketingConsentRepository`: `standing()` locks the latest answer for a purpose and channel; `carryToContact()` is called by `CrmLeadConversion`; `usesPurpose()` and `usesContact()` guard deletes.
  - `CrmConsentService`:
    - `index()` marks each answer's `shown_status` (expired past `expires_on`) and `standing`, and gives the purposes switched on;
    - `record()` and `withdraw()` answer with `index()` again;
    - converted leads and merged contacts are refused (409);
    - moments are the business's day and time (`recorded_on` and `recorded_time`, `withdrawn_on` and `withdrawn_time`), now when left out, never later than now.
  - `CrmConsentController` serves both kinds by the route's `kind` default: `/crm/leads/{id}/consents` (`crm.leads.view`, `crm.leads.manage`) and `/crm/contacts/{id}/consents` (`contacts.view`, `contacts.edit`), each with `/{consentId}/withdraw`. `RecordMarketingConsentRequest`, `WithdrawMarketingConsentRequest`, `MarketingConsent(s)Resource`.
  - Also changed: `ContactMergeRepository` (moving, move), `ContactService::delete()`, `CrmSettingService::deleteOption()` and `BusinessDataExport` (the `marketing-consents` file).
  - Frontend:
    - CrmConsentCard with CrmConsentHistory, CrmConsentModal and CrmConsentWithdrawModal, on `useCrmConsents()`, and `utils/crmConsents.ts` (channels, sources, statuses);
    - the lead's page and the contact's page show it;
    - `CRM_LISTS` has Consent Purposes, and `MERGE_MOVING` the consent records.
- Custom fields (Doc 17 Phase I4, migration 000186):
  - `crm_custom_fields`: `record` (lead or opportunity), `name` (unique per record), `type` (text, number, date, choice, yes_no), `options` (a choice's), `is_required`, `is_active` and `position`.
  - `crm_leads` and `crm_opportunities` gain `custom_fields` (JSON, by field id): text, numbers and choices as strings, dates as Y-m-d, yes or no as booleans.
  - `CrmCustomField` and `CrmCustomFieldRepository`: `activeFor()`, `reorder()`, and `hasValues()`, which reads the JSON by path (`custom_fields->{id}`), as MySQL and SQLite both do.
  - `App\Services\Crm\CustomFieldValues`:
    - `forForm()` gives a record's active fields;
    - `checked()` takes each active field's value from the input, of its type, a required one filled, and keeps inactive fields' values. Errors go under `custom_fields.{id}`;
    - `shown()` gives the active fields with the record's values.
  - `CrmLeadService` and `CrmOpportunityService` check them in their `checked()`: always on adding, on a change only when `custom_fields` is given. The options give the fields (`custom_fields`), and `CrmLeadResource` and `CrmOpportunityResource` the shown ones.
  - Settings: `CrmCustomFieldService` (a name per record, a type kept while it has values, a field with values switched off rather than deleted), `CrmCustomFieldController`, `SaveCrmCustomFieldRequest`, `ReorderCrmCustomFieldsRequest` and `CrmCustomFieldResource`. The routes are under `/business-settings/crm/custom-fields`, with `crm.settings.manage`.
  - The business's data export has a crm-custom-fields file, to read the values by.
  - Frontend:
    - SettingsCrmCustomFields with CrmCustomFieldModal, on `useCrmCustomFields()` (`crm:custom-fields`);
    - CrmCustomFieldInputs on both forms (`toCustomFieldForm()`, `customFieldValues()`), and CrmCustomFieldsCard on both pages (`customFieldText()`), in `utils/crmCustomFields.ts`.
- Point of Sale on, with its settings (Doc 18 Phase A1, migration 000187):
  - The migration makes the `apps` row `pos` available where it was still coming soon, and bumps `CatalogVersion`. `AppCatalogSeeder` has it available, with its own page's words (`DETAILS['pos']`), for new installs.
  - `pos_settings` (one per business: `walk_in_contact_id`, `return_policy`, `receipt_footer`) and `pos_payment_methods` (`name`, `type`, `payment_account_id` - nulled if the account goes - `needs_reference`, `gives_change`, `is_active`, `position`).
  - `PosSetting`, `PosPaymentMethod` (`TYPES`), `PosSettingRepository` (`started()` tells the first opening). `PaymentAccountRepository` gains `takingCustomerPayments()` and `startingCash()`.
  - `App\Services\Api\Pos\PosSettingService`: `show()` and `start()`, `save()` (an active customer only), and the methods' `addMethod()`, `updateMethod()`, `deleteMethod()` with their `checked()`.
  - `PosSettingController`, `SavePosSettingsRequest`, `SavePosPaymentMethodRequest`, `PosSettingsResource` (with the accounts a method may use), `PosPaymentMethodResource` (an account's `usable`). The routes are under `/business-settings/pos` with `app:pos`, under `pos.settings.manage`.
  - `Permissions`: the group `pos.settings` (Manage POS Settings), requiring `settings.view` and `contacts.view`. The business's data export has a pos-payment-methods file.
  - Frontend: the settings page's Point of Sale tab (`has('pos')`, `pos.settings.manage`): SettingsPos (CustomerPicker for the walk-in customer), SettingsPosPaymentMethods and PosPaymentMethodModal, on `usePosSettings()` (`pos:settings`, `POS_PAYMENT_TYPES`).
- POS registers (Doc 18 Phase A2, migration 000188):
  - `pos_registers`: `name` and `code` unique in the business, `location_id`, `currency`, `price_list_id` and `cash_account_id` (nulled if they go), `receipt_prefix`, `status` (`active`, `inactive` or `maintenance`), `created_by` and `updated_by`. Its payment methods and cashiers are `pos_register_payment_methods` and `pos_register_cashiers`, with composite keys. The migration adds the Registers menu row (`/pos/registers`, `pos.registers.view`), as `AppCatalogSeeder` does for new installs.
  - `PosRegister` (`STATUSES`, `paymentMethods`, `cashiers`) and `PosRegisterRepository`: `getFor()` and `findFor()` at the user's locations, `taken()`, `create()` and `update()` syncing both link tables, `usesLocation()`, `takingMethod()`. `PriceListRepository::activeFor()` is new; `takingCustomerPayments()` now selects `location_id` too.
  - `App\Services\Api\Pos\PosRegisterService`: `index()`, `options()` (which calls `PosSettingService::start()`), `create()`, `update()` and `delete()`, with `checked()`; its comments give the rules. `people()` is the active members and the owner, with `WorkspaceAccess::locationsOf()`.
  - `PosRegisterController`, `SavePosRegisterRequest`, `PosRegisterResource` and `PosRegisterOptionsResource`. The routes are under `/pos/registers` with `app:pos` and `access`: the list under `pos.registers.view`, the options and the changes under `pos.registers.manage`.
  - `PosSettingService::deleteMethod()` and `LocationService::delete()` refuse with a 409 while a register uses them. The business's data export has pos-registers and its two link files.
  - Frontend: `pages/pos/registers/index.vue` (`family: 'pos'`), and PosRegisterModal with PosRegisterFormSelling (location, currency, price list, cash account, methods and cashiers: what fits, and the register's own that no longer does), on `usePosRegisters()` (`pos:registers`, `pos:registers:options`, `POS_REGISTER_STATUSES`).
- POS sessions (Doc 18 Phase B1, migration 000189):
  - `pos_sessions`:
    - `number`: S-00001, from the `WorkspaceSequence` counter `pos_session`;
    - `pos_register_id`, with no cascade: a register with sessions is kept;
    - `cashier_id`, and `currency`: the register's as the session opened;
    - `status`: `open`, `closing`, `closed` or `suspended` (B1 uses open and closed);
    - `opened_at`, `opening_float`, `opening_notes`, `closed_at` and `closed_by`.
  - `pos_session_closings` holds each closing's Z report: `opening_float`, `expected_cash`, `counted_cash`, `variance` (counted less expected), `variance_reason`, `notes`, `totals` (JSON, as `PosSessionTotals` gave it), `closed_by` and `closed_at`. The migration adds the Sessions menu row (`/pos/sessions`, `pos.sessions.view`).
  - `PosSession` (`LIVE` was every status but closed; since Phase B2a it is open and suspended only; never deleted) and `PosSessionClosing` (throws on update and delete, like the admin audit log). `PosSessionRepository` has:
    - `paginateFor()` and `findFor()`, at the user's locations through the register;
    - `lock()`, `liveAt()`, `liveOf()`, `liveRegisterIds()` and `heldAt()`;
    - `open()` and `close()`.
  - `PosRegisterRepository::lockFor()` locks the register while a session opens on it.
  - `PosSessionTotals::of()` is where Phases C to F add sales, payments, refunds and cash movements. It returns `sales_count`, `sales_total`, `tax_total`, `payments` by method, `cash_sales`, `cash_refunds`, `cash_in`, `cash_out` and `expected_cash` (the float, plus cash sales and cash in, less cash refunds and cash out). In B1 all are nil but the float.
  - `App\Services\Api\Pos\PosSessionService`: `list()`, `options()` (each register's `refusal`, and the user's own open session), `open()`, `show()` and `close()`.
  - `PosRegisterService` refuses a status, location or currency change while a session is open, and deleting a register with sessions (409). `PosRegisterResource` has `has_sessions`.
  - `PosSessionController`, `ListPosSessionsRequest`, `OpenPosSessionRequest`, `ClosePosSessionRequest`, `PosSessionListResource`, `PosSessionResource` (`totals` while open, `z_report` once closed, `closes`) and `PosSessionOptionsResource`. The routes are under `/pos/sessions` with `app:pos` and `access`: reading under `pos.sessions.view`, opening and closing under `pos.sessions.open`. The data export has pos-sessions and pos-session-closings.
  - Frontend: `pages/pos/sessions/index.vue` and `pages/pos/sessions/[id].vue`, with PosSessionOpenModal, PosSessionCloseModal, PosSessionTotalsList and PosSessionZReport, on `usePosSessions()` (`POS_SESSION_STATUSES`, `posSessionStatus()`, `cents()`).
- The supervisor's side of POS sessions (Doc 18 Phase B2a, migration 000190):
  - `pos_settings.variance_limit` (null for none), saved by `PosSettingService::save()` and sent by `PosSettingsResource`.
  - `pos_session_closings` gains `closer_reason`, `needs_approval`, `approved_by` and `approved_at`, and `reopened_by`, `reopened_at` and `reopen_reason`. `PosSessionClosing` lets only its `MARKS` be written after it is made, each once; any other change throws.
  - `PosSession::LIVE` is now open and suspended only: a session Closing (with closed, `COUNTED`) holds neither its register nor its cashier.
  - `PosSessionService`:
    - `close()` lets someone who approves variances close anyone's open session, with `closer_reason`, and leaves a variance beyond the limit Closing unless the closer approves variances;
    - `approve()`;
    - `reopen()`, with the register locked: it must be active, and neither it nor the cashier may have another session open;
    - `show()` adds `own`, `approves` and `reopens`.
  - `PosSessionRepository::approve()` and `reopen()` write the marks on the last closing.
  - Permissions: `pos.sessions.approve` (Approve Variances and Close Others' Sessions) and `pos.sessions.reopen` (Reopen Closed Sessions), each requiring `pos.sessions.view`. The close route takes `pos.sessions.open` or `pos.sessions.approve`. Approving and reopening are `POST /pos/sessions/{id}/approve` and `/reopen` (`ReopenPosSessionRequest`).
  - `PosSessionResource`: `z_report` is the last closing unless it was reopened, with `closer_reason`, `needs_approval` and the approval; `earlier` lists the reopened closings; `own` says whether it is the user's.
  - Frontend:
    - the session page's Approve Variance and Reopen Session, through `usePosSessionSteps()` and PosSessionActionModal;
    - (since B2b, the variance limit is saved with the other closing settings: see below);
    - PosSessionEarlierClosings;
    - the close form's "Why You Close It", when the session is not `own`;
    - the Z report's approval and the supervisor's reason;
    - Settings → Point of Sale's Variance Needing Approval.
- Counting by notes and coins (Doc 18 Phase B2b, migration 000191):
  - `pos_settings.counts_denominations` and `denominations` (JSON: each currency's values, the largest first), and `pos_session_closings.denominations` (JSON: `[{value, count, amount}]`).
  - `App\Support\CashDenominations`: `usual()` for the 13 currencies it knows, and `sorted()`.
  - `PosSettingService`:
    - `saveCashCount()` (PUT `/business-settings/pos/cash-count`, `SavePosCashCountRequest`, under `pos.settings.manage`) saves the variance limit, the switch and the lists. The main settings save no longer takes the variance limit;
    - `show()` returns each of the business's currencies' lists (`denominationsOf()`: the business's own, else the usual);
    - `countingFor()` gives a session currency's list, or null.
  - `PosSessionService::close()` works the cash counted out from `denominations` (`byDenomination()`) when the business counts that way, refusing a value not on the list, or no count at all. `show()` gives `denominations` while the session is open.
  - Frontend: SettingsPosClosing (`saveCashCount()`), PosSessionCountGrid in PosSessionCloseModal (`countTotal()`, `wholeCount()`), and the Z report's Notes and Coins.
- The selling screen (Doc 18 Phase C1, migration 000192 for its menu row): `PosSellingService` behind `GET /pos/sell`, `/pos/sell/items` and `/pos/sell/quote` (`PosSellController`, `SearchPosItemsRequest`, `QuotePosCartRequest`; `PosSellScreenResource`, `PosSellItemsResource`, `PosSellItemResource`, `PosCartQuoteResource`), under `pos.selling.open`, in the user's own open session (`PosSessionRepository::sellingOf()`).
  - Items: `ItemRepository::forSelling()`, `byCode()` (the barcode, else the SKU in any case), `forCart()`, `mostSoldAt()` (issued invoices at a location since a day) and `categoriesSold()`.
  - `PosPricing`: `rate()`, `standard()` (an item's price at the register) and `quote()`. The customer's own lists (`PriceListRepository::eligible()`, those naming them), then the register's (`fallback()`), through `PriceListPricing::quoteFrom()` - else the item's price divided by the rate. Taxes as a sales form picks them, totals by `SalesDocumentService::totals()`.
  - The quote is a GET, its lines in the address (`lines[0][item_id]=…`), so `LogApiWriteActivity` logs nothing for it.
  - Frontend: `pages/pos/sell.vue` with PosSellCatalog, PosSellCart, PosSellCartLine and PosSellCustomer; `usePosSelling()`; `usePosCart()` and `usePosCartSync()` (the cart in `useState`, kept in `localStorage` per session, priced 250 ms after a change, the latest answer only); `.pos-item` in `main.css`; PosSessionOpenModal's `stay`.
- Payment and the completed sale (Doc 18 Phase C2, migration 000193: `pos_orders`, `pos_order_payments`, `pos_order_lines`): `PosOrderService::complete()` behind `POST /pos/orders` (`PosOrderController`, `CompletePosOrderRequest`, `PosOrderResource`; idempotent), under `pos.selling.sell`.
  - `PosCounter` holds what selling stands on - the user's open session, today's rate, the customer, the cart's lines and the register's usable methods (`methods()`) - for `PosSellingService` and `PosOrderService` both.
  - `PosPricing::priced()` gives the invoice's rows and totals; `quote()` trims them for the cart.
  - `PosSale::record()` writes the sale in one transaction: the invoice (`SalesDocumentRepository::createMadeBy()`, Point of Sale its source app), its lines, the order, each payment (`SalesPaymentRepository::createMadeBy()`, `AccountLedger::recordPayment()`), then `InvoiceSettlement::settle()`. Receipt numbers come from `WorkspaceSequence`, as `pos_receipt|{register id}`.
  - `PosSessionTotals` counts a session's completed orders and their payments (`PosOrderRepository::completedIn()`, `paymentsIn()`) in bcmath.
  - Sales lists: `SalesDocumentListResource`'s `pos`, from the document's `sourceApp`.
  - Frontend: PosPayModal with PosPayRow and `usePosPayment()` (display-only cents, worked out as the API does); `usePosCart()`'s `pricing` and `reprice()`; PosSellCart's Pay; SalesDocumentsTable's POS pill.
- Discounts, price overrides and cash rounding (Doc 18 Phase C3, migration 000194): `pos_settings.discount_limit` and `cash_rounding`, saved by `PosSettingService::saveSelling()` (PUT `/business-settings/pos/selling`, `SavePosSellingSettingsRequest`); `pos_orders.discount_reason`, `discount_approved_by` and `cash_rounding`; `pos_order_lines.standard_price` and `override_reason`.
  - `PosCounter::lines()` reads each line's `price`, `override_reason` and discount, `discount()` the order's. `PosPricing::priced()` puts an override over the line's price (keeping its `standard`), totals through `SalesCalculator` itself and, given a step, adds the Cash rounding line as a deduction (`rounding()`); `shown()` is the cart's quote.
  - `PosDiscounts::check()` refuses what the user may not give and says whether it goes beyond the limit; `allowBeyond()` needs `pos.selling.beyond` and a reason.
  - Frontend: SettingsPosSelling (`saveSelling()`), PosSellLineAdjust on PosSellCartLine, PosSellOrderDiscount, `usePosCart()`'s `discount`, `lineBody()` and `discountBody()`, and `usePosPayment()`'s rounding.
- The receipt (Doc 18 Phase C4, migration 000195): `pos_orders.receipt_prints` and `receipt_texts` (the return policy and footer, written by `PosSale::record()`).
  - `PosReceiptService::pdf()` (GET `/pos/orders/{id}/receipt`, `PosReceiptController`) locks the order, counts the copy and makes it - the first the cashier's, a later one needing `pos.receipts.reprint`; `email()` (POST `/pos/orders/{id}/receipt/email`, `EmailPosReceiptRequest`) queues a `DocumentEmail` for the `PosOrder`, which `DocumentMailer` sends with the receipt as the original.
  - `DocumentRenderer::posReceipt()` with `PosReceiptSheet` (facts, lines, totals, payments) draws `documents/pos-receipt.blade.php`; `DocumentPdf::posReceipt()`; `GotenbergClient`'s `receipt` paper - 80 mm, its own margins, `singlePage`. `DocumentEmailText::forPosReceipt()`.
  - The screen's `last_order` (`PosOrderRepository::lastIn()`, `PosReceiptResource`), and `PosOrderResource`'s `email`.
  - Frontend: PosSaleDone (the sale's dialog, out of PosPayModal), PosReceiptEmail, PosSellLastSale with DocumentPdfModal, `usePosSelling()`'s `receiptPdf()` and `emailReceipt()`.
- Holding and resuming orders (Doc 18 Phase D1, migration 000196): `pos_held_orders` (`PosHeldOrder`, `PosHeldOrderRepository`) - the cart as JSON with each line's price then - and `pos_settings.hold_hours`.
  - `PosHoldService::hold()`, `list()`, `preview()`, `resume()` and `cancel()` (`/pos/held-orders`, `PosHeldOrderController`, `HoldPosOrderRequest`, `CancelPosHeldOrderRequest`); `resumable()` prices the cart again and says what changed, leaving out what can no longer be sold or given. `expire()`, run by `pos:expire-held-orders` hourly.
  - `PosCounter::unsold()` and `usableCustomer()`; the screen's `held_count`.
  - Frontend: PosSellHold, PosHeldOrdersModal, `usePosHeldOrders()`, `usePosCart()`'s `load()`; SettingsPosSelling's Keep Held Orders For.
- Pending payments (Doc 18 Phase D2, migration 000197): `pos_payment_methods.confirms_later`; `pos_order_payments.payment_account_id`, `status` (completed, pending, declined), `closed_by`, `closed_at` and `decline_reason`.
  - `PosSale::pay()` records a pending one without a customer payment; `PosSale::receive()` makes the customer payment, for a sale and for a confirmation alike.
  - `PosPendingPayments::of()`, `confirm()` and `decline()` (GET `/pos/sessions/{id}/pending-payments`, POST `/pos/order-payments/{id}/confirm` and `/decline`, `PosPendingPaymentController`, `DeclinePosPaymentRequest`, `PosOrderPaymentResource`); `PosSessionTotals`' `pending`; the screen's `pending_count`.
  - Frontend: PosPendingPayments on the session's page with `usePosPendingPayments()`, PosPaymentMethodModal's box, PosPayRow's hint, PosSaleDone, PosSessionTotalsList's Waiting to Be Confirmed.
- Credit sales (Doc 18 Phase D3a, migration 000198): `pos_orders.on_account` and `credit_reason`. `PosOrderService::credit()` - the full Sales app, `pos.selling.credit`, a named customer - and `CustomerCredit::checkAdding()`, the credit check for what is left on account (`check()` now goes through the same `against()`); `PosSale::record()`'s `terms`; the screen's `credit_sales`.
  - Frontend: PosPayModal's on-account box and Why Go Over the Credit Limit, `usePosPayment()`'s `onAccount`, PosSaleDone's on-account line and warning.
- The customer's credit as a payment (Doc 18 Phase D3b, migration 000199): `pos_orders.credit_applied`. `PosCustomerCredit::available()` and `apply()` - allocations from advances and credit notes, oldest first, in the sale's transaction (`PosSale::record()`); `PosOrderService::usableCredit()`; GET `/pos/sell/credit` (`ShowPosCustomerCreditRequest`, `PosCustomerCreditResource`).
  - Frontend: PosPayCredit in PosPayModal, `usePosPayment()`'s `credit`, `usePosSelling()`'s `customerCredit()`, PosSaleDone's "Customer credit".
- The formal invoice (Doc 18 Phase D4, migration 000200): `pos_orders.invoiced_at`. `PosFormalInvoices::issue()` and `pdf()` (POST and GET `/pos/orders/{id}/formal-invoice`, `PosFormalInvoiceController`, `PosFormalInvoiceResource`); `PosOrderAccess` - who may handle a sale's documents - shared with `PosReceiptService`; `SalesDocument::posOrder()` and `DocumentSheet::facts()`' POS Receipt; the last order's `contact_id` and `invoice`.
  - Frontend: PosSellLastSale's Formal Invoice, with `usePosLastSale()` - the last sale shared by the cart and its row - and `usePosSelling()`'s `formalInvoice()` and `formalInvoicePdf()`.
- Returns and their refunds (Doc 18 Phase E1, migration 000201): `pos_returns` and `pos_return_lines` (`PosReturn`, `PosReturnLine`, `PosReturnRepository`). `PosReturnService::lookup()`, `quote()` and `record()` (GET `/pos/returns/lookup` and `/quote`, POST `/pos/returns`, `PosReturnController`, `LookupPosReturnRequest`, `QuotePosReturnRequest`, `RecordPosReturnRequest`): the Sales app's `CreditNoteLines`, `CreditNoteService::create()` and `issue()` and `CreditNoteRefunds::refund()` underneath, the refund capped by `SalesDocumentRules::refundable()`. `PosSessionTotals`' `cash_refunds`.
  - Frontend: PosReturnModal with PosReturnLines and `usePosReturns()`, on the selling page.
- The refund rules (Doc 18 Phase E2, migration 000202): `pos_returns.refund_status`, `refund_reason`, `refund_account_id`, `refund_due`, `refund_closed_by`, `refund_closed_at` and `refund_decline_reason`. `PosReturnService::alternative()` and the waiting refund in `record()`; `PosPendingRefunds::of()`, `confirm()` and `decline()` (GET `/pos/sessions/{id}/pending-refunds`, POST `/pos/returns/{id}/refund/confirm` and `/decline`, `PosPendingRefundController`, `PosPendingRefundResource`); the refund receipt - `DocumentRenderer::posRefundReceipt()`, `PosReceiptSheet::refund()`, `documents/pos-receipt.blade.php`'s heading, sale and Kept as credit - at GET `/pos/returns/{id}/receipt`.
  - Frontend: PosReturnModal's By another method and Refund Receipt, the selling page's refund receipt dialog, PosPendingRefunds on the session's page.
- Exchanges (Doc 18 Phase E3, migration 000203): `pos_returns.exchange_order_id`; `pos_orders.exchange_return_id`, `exchange_credit` and `exchange_refunded`. `PosReturn::EXCHANGE`; `PosExchanges::usable()` checks the exchange for the sale (`CompletePosOrderRequest`'s `exchange_return_id`) in `PosOrderService::complete()`, and `PosSale::exchange()` applies its credit note's credit (`PosCustomerCredit::applyNote()`), links the two and pays the rest back through `CreditNoteRefunds`. `PosOrderRepository::exchangeRefundedIn()` counts in `PosSessionTotals`' cash refunds; the receipt's Exchange and Paid back, and the refund receipt's Kept for the exchange, are `PosReceiptSheet`'s.
  - Frontend: PosReturnModal's Exchanged and Start the Exchange Sale (`returnOutcome()` in usePosReturns), the cart's exchange (usePosCart's `exchangeFrom()` and `exchangeSplit()`), PosSellExchange, PosPayModal's exchange and Paid Back in Cash (usePosPayment), PosSaleDone.
- Cash movements (Doc 18 Phase F1, migration 000204): `pos_cash_movements` and `pos_settings.cash_movement_limit`. `PosCashMovement` (its kinds, their directions, and the account types a safe drop and a bank deposit take), `PosCashMovementRepository`, `PosCashMovementService` (`options()`, `record()`, `of()`, `approve()`, `decline()`) behind `PosCashMovementController`: GET `/pos/cash-movements/options`, POST `/pos/cash-movements`, GET `/pos/sessions/{id}/cash-movements`, POST `/pos/cash-movements/{id}/approve` and `/decline`. Each moves by `AccountTransferService::create()`; `PaymentAccountRepository::forCashMovements()` lists the accounts. `PosSessionTotals`' cash in and out are `PosCashMovementRepository::totalsIn()`; `PosSessionService::close()` refuses while one waits.
  - Frontend: PosCashMovementModal (the selling page's Cash In or Out) and PosCashMovements (the session's page), with usePosCashMovements; SettingsPosClosing's Cash Movement Needing Approval.
- Paid outs (Doc 18 Phase F2, migration 000205): `pos_cash_movements.payee_name`, `expense_category_id` and `expense_id`. `PosCashMovement::PAID_OUT`; `PosCashMovementService::payOut()` - `ExpenseService::create()`, `ExpenseFlow::record()` and `ExpensePaymentService::record()` - with `paysOut()` and `category()` (`ExpenseCategoryRepository::activeFor()`); the options' `categories`.
  - Frontend: PosCashMovementModal's Paid Out, PosCashMovements' payee and expense.
- The X and Z reports (Doc 18 Phase F3, no migration): GET `/pos/sessions/{id}/x-report` and `/z-report` (`PosSessionController::report()`), `PosSessionService::report()`, `DocumentPdf`/`DocumentRenderer::posSessionReport()`, `PosSessionReportSheet`, `documents/pos-session-report.blade.php` - and the roll's shared partials `documents/partials/pos-roll-styles` and `pos-roll-business`.
  - Frontend: the session's page's X Report or Z Report, in a DocumentPdfModal (usePosSessions' `sessionReportPdf()`).
- POS's pages of records (Doc 18 Phase G1, migration 000206 for the menu): `PosRecordLists` (`orders()`, `returns()`, `movements()`) over `PosOrderRepository`, `PosReturnRepository` and `PosCashMovementRepository::paginateFor()`; GET `/pos/orders` (`PosOrderController::index`, `ListPosOrdersRequest`, `PosOrderListResource`), `/pos/returns` (`PosReturnController::index`, `ListPosReturnsRequest`, `PosReturnListResource`) and `/pos/cash-movements` (`PosCashMovementController::list`, `ListPosCashMovementsRequest`, `PosCashMovementListResource`). The Sessions page's choices gain `cashiers`.
  - Frontend: pages/pos/orders, pos/returns and pos/cash-movements, with PosRecordsToolbar and usePosRecords; the session page's "Its sales" and "Its returns".
- POS today (Doc 18 Phase G2, migration 000207 for the menu): `PosOverviewService::today()` over `PosOverviewRepository` (`sales()`, `payments()`, `returns()`, `variances()`, `topItems()`, `openSessions()`), GET `/pos/overview` (`PosOverviewController`, `PosOverviewResource`); the Dashboard's `pos` (`DashboardService`, `DashboardResource`).
  - Frontend: pages/pos/overview and DashboardPos, with PosTodayFigures and usePosOverview.
- POS's documents on the Documents page (Doc 18 Phase G3, no migration): `DocumentIndexRepository`'s `pos` source (`fromPos()`), its refund receipts' half of the union (`refundReceipts()`, family `pos_returns`), `SalesDocument::posReturn()`; `DocumentIndexResource`'s `pos` and `pos_reference`, and a refund receipt's row (`refundReceipt()`); `DocumentIndexService`'s source name and CSV row.
  - Frontend: useDocumentIndex's POS labels and the `pos-refund-receipts` kind, DocumentsTable's reference line, DocumentsToolbar's Point of Sale source; the Returns page opens searched.
- POS's reports (Doc 18 Phase H, no migration): `PosReportRepository` (sales, their lines, returns, theirs, payments, cashiers) and `app/Services/Reports/Pos` - `PosReport` (the base: grouping by period, hour, location, register, cashier or salesperson), `PosSalesByReport` (POS Sales Summary and the "Sales by" reports), `PosPaymentMethodsReport`, `PosAverageOrderReport`; `ReportCatalog`'s `pos` category and `POS` needs, `ReportService`'s register and cashier; the `pos.reports.view` permission; `PosReportChoiceController` (`GET /reports/pos/choices`).
  - Frontend: ReportPosNarrowing, useReports' POS groupings and `loadPosChoices`; the Reports page's empty state.
  - Phase H2: `PosDiscountsReport`, `PosTaxesReport`, `PosReturnsReport`, `PosVoidsReport`, `PosTopItemsReport` and `PosMarginReport`, `PosReport::lineRows()`; `PosReportRepository::discounted()`, `overrides()`, `closedHeld()`, `declinedPayments()` and `declinedRefunds()`.
  - Phase H3: `PosSessionsReport` (with `PosSessionTotals` for a session still open), `PosCashVariancesReport` and `PosCashMovementsReport`, `PosReport::oneCurrency()`; `PosReportRepository::sessions()`, `variances()` and `cashMovements()`.
- The cashier's PIN and the register's lock (Doc 18 Phase I1, migration 000208): `users.pos_pin` (hashed) and `pos_pin_set_at`, `pos_settings.lock_minutes`, `pos_sessions.last_active_at` and `locked_at`. `PosLockService` (pin, lock, unlock), `PosPinRepository`, `PosLockController` (`PUT /pos/pin`, `POST /pos/lock`, `POST /pos/unlock`), `PosLockResource`; `PosCounter::selling()` refuses a locked register (423) and keeps its last use, `PosCounter::lockOf()` locks one idle past the minutes; `PosSessionRepository::mark()`.
  - Frontend: PosSellLock (the lock screen, the idle timer), PosPinModal, usePosLock (`POS_LOCKED_EVENT`, `POS_UNLOCKED_EVENT`, `useIdle`); useApi raises the locked event on a 423; PosSellCatalog searches again once it is open.
- A manager's approval at the register (Doc 18 Phase I2, migration 000209): `pos_order_lines.override_approved_by`, `pos_returns.refund_approved_by`. `PosApprovals` (approvers, approve, approverFor: a cached token per session and kind), `PosApproverRepository`, `PosApprovalController` (`GET /pos/approvers`, `POST /pos/approvals`); `PosDiscounts`, `PosOrderService`, `PosSale`, `PosHoldService`, `PosSellingService`, `PosCashMovementService` and `PosReturnService` take `approvals`.
  - Frontend: usePosApprovals (`ask()`, the cart's tokens), PosApprovalModal (last on the selling page, so it opens over the others); PosSellLineAdjust, PosSellOrderDiscount, PosCashMovementModal and PosReturnModal ask; usePosSelling and usePosHeldOrders send the tokens.
- A register's devices (Doc 18 Phase I3, migration 000210): `pos_devices`, `PosDevice`, `PosRegister::devices()`; `PosDeviceRepository` (and `used()`, from `PosReceiptService::pdf()` and `PosSellingService::items()`), `PosDeviceService`, `PosDeviceController` (`/pos/registers/{register}/devices`, `/test`, `/test-page`), `PosDeviceResource`; `DocumentRenderer::posDeviceTest()` and `pos-device-test.blade.php`.
  - Frontend: PosRegisterDevicesModal, PosDeviceForm, usePosDevices; the Registers page's Devices.
- eCommerce on, with its storefronts (Doc 19 Phase A1, migration 000211): the migration makes the `apps` row `ecommerce` available where it was still coming soon, with its Sales Lite dependency and the Storefronts menu row; `AppCatalogSeeder` has them, and its page's words (`DETAILS['ecommerce']`), for new installs.
  - Settings → eCommerce: `ecommerce_settings` (`guest_checkout`, `verify_customer_email`, `price_display`, `order_prefix`), `EcommerceSetting`, `EcommerceSettingRepository`, `EcommerceSettingService`, `EcommerceSettingController` (`/business-settings/ecommerce`), `EcommerceSettingsResource`; permission `ecommerce.settings.manage`.
  - `storefronts`: `name` unique in the business, `code` unique across izSuite (`Storefront::RESERVED_CODES` refused), `status`, `default_language` and `languages`, `currency` and `currencies`, `countries`, `location_id`, `support_email` and `support_phone`. `StorefrontRepository` (and `usesLocation()`, from `LocationService::delete()`), `StorefrontService`, `StorefrontController` (`/ecommerce/storefronts`, `/options`), `StorefrontResource`, `StorefrontOptionsResource`; permissions `ecommerce.store.view` and `ecommerce.store.manage`.
  - Frontend: the Storefronts page (`pages/ecommerce/storefronts`), StorefrontModal and StorefrontFormReach, useStorefronts; SettingsEcommerce and useEcommerceSettings.
- The plans' storefronts (Doc 19 Phase A2, migration 000212): `packages.storefront_limit` and `package_versions.storefront_limit` (in `PackageVersion::TERMS`), in the admin's limits partial (`UpdatePackageRequest`); `PlanLimits::ensureCanAddStorefront()` and `storefronts()` - `StorefrontRepository::countOpenFor()`, all but the archived - called by `StorefrontService` on adding one and on opening an archived one; `PlanLimits::usage()['storefronts']`, null while eCommerce is off, through `BusinessUsageResource`; `sells_online` and `storefront_limit` in `PublicCatalog` and `PackageOfferResource`.
  - Frontend: SettingsUsageCounts' Storefronts row, and `planLimits()` in `utils/plans.ts`.
- A storefront's domains (Doc 19 Phase A3, migration 000213): `storefront_domains` (`host` unique across izSuite, `token`, `verified_at`, `checked_at`, `is_primary`), `StorefrontDomain`, `StorefrontDomainRepository`, `StorefrontDomainService` (and `addressOf()`, `answersFor()`), `StorefrontDomainController` (`/ecommerce/storefronts/{storefront}/domains`, `/primary`, `/{domain}/verify`), `StorefrontDomainsResource` and `StorefrontDomainResource`; `DomainDns` reads TXT records, and the tests stand in for it. Without signing in: `StorefrontHostController` (`GET /storefront-hosts?domain=`) and `StorefrontHostResource`. `config/ecommerce.php`: `storefront_domain` and `cname_target`. Permission `ecommerce.domains.manage`.
  - Frontend: StorefrontDomainsModal and StorefrontDomainRow, useStorefrontDomains; the Storefronts page's Domains.
- Items published online (Doc 19 Phase B1, migration 000214): `catalog_products` - one per item: `title`, `slug` unique in the business, the descriptions, `keywords`, `status`, `visibility`, `available_from` and `available_until` (the business's days), `sales_method`, `availability` and `stock_quantity`, `weight` and `weight_unit`, `video_urls`, the SEO fields and `all_storefronts` - and `catalog_product_storefronts`. `CatalogProduct` (`statusOn()`, and `pictures()` and `cover()` in `FileCollection::Gallery`, public with thumbnails), `Item::catalogProduct()`; `CatalogProductRepository`, `CatalogService`, `EcommerceCatalogController` (`/ecommerce/catalog`, `/{item}`) and `CatalogPictureController` (`/{item}/pictures`), `CatalogItemResource` and `CatalogEntryResource`. Permissions `ecommerce.catalog.view`, `.publish` and `.manage`.
  - Frontend: the Catalog page and an item's page (`pages/ecommerce/catalog`), CatalogProductListing, CatalogProductMedia, CatalogProductSeo, CatalogProductPublication and CatalogProductSelling; useCatalog and useCatalogProductForm.
- Categories, collections and attributes (Doc 19 Phase B2, migration 000215):
  - Tables:
    - `catalog_categories`, an item category's online side: `slug` unique in the business, `description`, the SEO fields, `in_menu` and `position`.
    - `catalog_collections`: `name` and `slug` unique in the business, `kind` (promotion, season, brand or theme), `item_brand_id` for a brand's, `description`, `status`, `available_from` and `available_until`, the SEO fields and `position`.
    - `catalog_collection_products`, with `position`.
    - `catalog_products.custom_attributes`.
  - Models: `CatalogCategory` and `CatalogCollection`, each with `image()` in `FileCollection::Image`; `ItemCategory::catalogCategory()`.
  - Repositories and services: `CatalogCategoryRepository` and `CatalogCollectionRepository`; `CatalogCategoryService` and `CatalogCollectionService`.
  - Controllers:
    - `CatalogCategoryController`: `/ecommerce/catalog-categories`, `/{category}` and `/{category}/image`.
    - `CatalogCollectionController`: `/ecommerce/collections`, `/options`, `/{id}` and `/{id}/image`.
  - Resources: `CatalogCategoryResource`, `CatalogCollectionResource` and `CatalogCollectionOptionsResource`.
  - Deleting an item or an item category deletes its listing or online side through that model (`ItemRepository::delete()`, `ItemLabelRepository::delete()`), so their pictures go too.
  - Frontend:
    - the Catalog page's tabs (`pages/ecommerce/catalog/index.vue`): CatalogProductsList, CatalogCategoriesList and CatalogCollectionsList;
    - CatalogCategoryModal, CatalogCollectionModal, CatalogPictureField and CatalogProductAttributes;
    - useCatalogTaxonomy.
- Options and variants (Doc 19 Phase B3, migration 000216):
  - Tables: `catalog_product_variants`, a listing's variants - `item_id` unique (an item is a variant of one listing at most), `option_values` in the options' order, `status` (active or hidden), `availability` and `stock_quantity`, `weight` and `position` - and `catalog_products.options`.
  - Models: `CatalogProductVariant`; `CatalogProduct::variants()` and `Item::catalogVariant()`.
  - `CatalogVariantRepository` (`candidatesFor()`, `itemsFor()`, `save()`) and `CatalogVariantService`. `CatalogService` refuses a listing of another listing's variant, checks the variants shown when a listing is published, and gives an item's page `variant_of`.
  - `CatalogVariantController`: `/ecommerce/catalog/{item}/variant-items` and `/{item}/variants`; `SaveCatalogVariantsRequest` and `CatalogVariantItemResource`. `CatalogEntryResource` and `CatalogItemResource` gain the options, the variants, `variant_of` and `variant_count`.
  - Frontend: CatalogProductVariants, CatalogVariantOptions and CatalogVariantRow on an item's page; useCatalogVariantsForm.
- The storefront opens (Doc 19 Phase C1, no migration):
  - `StorefrontCatalogRepository` (`findStore()`, `products()`, `product()`, `menuOf()`, `brandsOf()`; what a store shows is `showing()`), `StorefrontPricing` (`terms()`, `priceOf()`, `fromPrice()`) and `StorefrontCatalogService` (whether a store is open).
  - `StoreCatalogController`, public: `GET /api/v1/stores/{code}`, `/products` and `/products/{slug}`; `ListStoreProductsRequest`; `StoreResource`, `StoreProductCardResource` and `StoreProductResource`.
  - Frontend: the `storefront` layout; `pages/store/[code]` (home, `shop`, `products/[slug]`); StoreHeader, StoreFooter, StoreUnavailable, StoreProductCard, StorePrice, StoreShopFilters, StoreGallery, StoreVariantPicker and StoreProductAction; useStore. Shared styles under "Storefronts" in `main.css`.
- Categories, collections and search suggestions on the storefront (Doc 19 Phase C2, no migration):
  - `StorefrontTaxonomyRepository` (`category()`, `collections()`, `collection()`, `categoriesMatching()`, `collectionsMatching()`); `StorefrontCatalogRepository::products()` takes a collection; `CatalogProduct::collections()`. `StorefrontCatalogService` gains `category()`, `collections()`, `collection()` and `suggestions()`.
  - `StoreCatalogController`: `/categories/{slug}`, `/collections`, `/collections/{slug}` and `/suggestions`; `SuggestStoreProductsRequest`; `StoreCategoryResource`, `StoreCollectionResource` and `StoreSuggestionsResource`.
  - Frontend: `pages/store/[code]/categories/[slug]` and `collections/[slug]`; StoreProductList (the shop's list, shared), StorePartHead and StoreSearch.
  - Tests: `Tests\Concerns\RunsAStorefront`, a business with a storefront open.
- Filters by price, options and attributes (Doc 19 Phase C3, no migration):
  - `StorefrontFacets` (`of()`, `passing()`) works them out from `StorefrontCatalogRepository::listingsWithin()`, with `StorefrontPricing`; `products()` takes the listings that pass. `StoreCatalogController::facets()` (`/facets`) and `StoreFacetsResource`.
  - Frontend: StoreFacetFilters, under the shop's filters; the chips in StoreShopFilters; `storeFacetFilters()` and `useStoreFacets()` in useStore.
- A storefront's theme and branding (Doc 19 Phase C4a, migration 000217):
  - `storefronts.theme` (what its customers see), `theme_draft` and `theme_published_at`. `App\Support\StorefrontTheme`: the choices, `DEFAULTS`, and `of()`, which fills in what a theme leaves out. Its pictures are in the `branding` collection (`Storefront::brandingFiles()`, up to `MAX_BRANDING`).
  - `StorefrontThemeRepository`, `StorefrontThemeService` (the draft checked and saved, pictures added, the draft published or discarded, and then the pictures neither theme shows deleted), `StorefrontThemeController` (`/ecommerce/storefronts/{storefront}/theme`, `/pictures`, `/publish`, `/discard`), `SaveStorefrontThemeRequest`, `UploadStorefrontPicturesRequest` and `StorefrontThemeResource`. The permission `ecommerce.themes.manage`, with eCommerce's View.
  - The store: `StorefrontCatalogService` takes the one previewing (`preview=1` and a token that may manage themes), who sees the draft theme, and a store not open as open. `StoreResource` gains `preview`, `open_to_customers` and `theme` (`StoreThemeResource`).
  - Frontend: the Theme page (`pages/ecommerce/storefronts/[id]/theme`), ThemeBrandingCard, ThemeLookCard, ThemeLayoutCard and ThemeSocialCard; useStorefrontTheme. The storefront layout wears the theme through the design's colour variables, with the preview's bar and the announcement; `useStorePreview()` in useStore keeps the preview in a cookie for the visit. `nuxt.config.ts` keys izSuite's icons, so a store's favicon takes their place.
- The home page's sections and banners (Doc 19 Phase C4b, no migration):
  - The theme's `home` (`StorefrontTheme::SECTIONS`, and the default's four). `StorefrontThemeService` checks them (`sections()`, `banner()`) against the storefront's pictures and the business's categories and collections: `StorefrontThemeRepository::categories()` and `collections()`, which are also the Theme page's `choices`.
  - `StorefrontHome` lays the home out - what each section shows today, those with nothing left out - for `StorefrontCatalogService::home()`; `StoreCatalogController::home()` (`/stores/{code}/home`) and `StoreHomeResource`; `StorefrontTaxonomyRepository::categorySlug()`.
  - Frontend: ThemeHomeCard, ThemeHomeBanner and ThemeHomeFields on the Theme page, and ThemePictureChoice for the logo's, favicon's and banners' pictures. The store's home is StoreHomeBanner and StoreHomeSection, from `useStoreHome()` in useStore (`useStoreCollections()` is gone).
- A storefront's pages (Doc 19 Phase C5a, migration 000218):
  - `storefront_pages` (`kind`, `title`, `slug` unique in the storefront, `body` - rich text, cleaned by `RichText::page()` with h2 and h3 - `status`, `in_footer`, the SEO fields and `position`); `StorefrontPage` (`STANDARD`: each kind's title and link) and `Storefront::pages()`.
  - `StorefrontPageRepository`, `StorefrontPageService` (an unwritten page as it starts; publishing needs words, but for the contact page), `StorefrontPageController` (`/ecommerce/storefronts/{storefront}/pages`, `/pages/{kind}`), `SaveStorefrontPageRequest`, `StorefrontPagesResource` and `StorefrontPageResource`.
  - The store: `StorefrontCatalogService::page()`, and the footer's `pages` in `store()`; `StoreCatalogController::page()` (`/stores/{code}/pages/{slug}`) and `StorePageResource`.
  - Frontend: the Pages list and a page's editor (`pages/ecommerce/storefronts/[id]/pages`), StorefrontPagePublishing and useStorefrontPages; RichTextEditor's `headings`; the store's `pages/store/[code]/pages/[slug]`, and its links in StoreFooter.
- Pages of the business's own (Doc 19 Phase C5b, migration 000219):
  - `packages.storefront_page_limit` and `package_versions.storefront_page_limit` (in `PackageVersion::TERMS`), in the admin's limits partial (`UpdatePackageRequest`), plans list and versions; `PlanLimits::ensureCanAddStorefrontPage()` and `storefrontPageLimit()`; the plans' `storefront_page_limit` (`PublicCatalog`, `PackageOfferResource`).
  - `StorefrontPage::OWN` pages: `StorefrontPageRepository` (`own()`, `ownPage()`, `ownCount()`, `slugTaken()`, `nextPosition()`, `delete()`), `StorefrontPageService` (`create()`, `delete()`, `slugOf()`), and `StorefrontPageController::store()` and `destroy()`.
  - Frontend: StorefrontOwnPages on the Pages list; the editor `pages/ecommerce/storefronts/[id]/pages/[page]` takes a kind, an id or `new`; StorefrontPagePublishing's link and place for them; `planLimits()` in `utils/plans.ts`.
- A storefront's SEO (Doc 19 Phase C6a, no migration):
  - The theme's `seo` (`StorefrontTheme`: title, description, image, index), checked by `StorefrontThemeService` and shown by `StoreThemeResource`; `StoreProductResource`'s `listed`.
  - The sitemap: `StorefrontCatalogService::sitemap()`, from `StorefrontCatalogRepository::sitemapProducts()`, `StorefrontTaxonomyRepository::sitemapCategories()` and `StorefrontPageRepository::publishedOf()`; `StoreCatalogController::sitemap()` (`/stores/{code}/sitemap`) and `StoreSitemapResource`.
  - Frontend: `useStoreSeo()`, `useStoreListAddress()` and `storeProductData()` in `composables/useStoreSeo.ts`, used by every store page; the layout's robots for a store not open; ThemeSearchCard on the Theme page; the sitemap's XML at `server/routes/store/[code]/sitemap.xml.ts`.
- The cookie notice (Doc 19 Phase C6b, migration 000220):
  - `ecommerce_settings.cookie_notice` and `cookie_notice_text` (`EcommerceSetting`, `SaveEcommerceSettingsRequest`, `EcommerceSettingService`, `EcommerceSettingsResource`); the store's `cookie_notice` in `StoreResource`, from `StorefrontCatalogService::store()`.
  - Frontend: SettingsEcommerce's Cookie Notice; StoreCookieNotice in the storefront layout; `useStoreConsent()` and `DEFAULT_COOKIE_NOTICE` in `composables/useStoreConsent.ts`.
- Shipping and pickup (Doc 19 Phase D1, migration 000221):
  - Tables: `shipping_zones` (`countries`, `regions`, `cities`, `postal_codes`, `position`); `shipping_methods` (`kind`, `price`, `rates` - bands of `from` and `price` - `tax_rate_id`, `days_min` and `days_max`, `min_order` and `max_order`, `min_weight` and `max_weight` in kg, `category_ids`, `is_active`, `position`); `pickup_points` (`location_id` unique, `is_active`, `instructions`, `ready_within`).
  - Models `ShippingZone`, `ShippingMethod` (its kinds, `BANDED`, `PRICED`) and `PickupPoint`; `Location::pickupPoint()`.
  - `ShippingRepository` - and, for the checkout, `offeredZones()` and `offeredPickupPoints()`. `ShippingSettingService` with `ShippingMethodRules`. `ShippingRates::for()` (the zone and its methods with their prices), `zoneOf()` and `kilograms()`.
  - `EcommerceShippingController` (`/business-settings/ecommerce/shipping`, `/shipping-zones`, `/shipping-methods`, `/pickup-points/{location}`); `SaveShippingZoneRequest`, `SaveShippingMethodRequest`, `SavePickupPointRequest`; `EcommerceShippingResource`, `ShippingZoneResource`, `ShippingMethodResource`, `PickupLocationResource`. Permission `ecommerce.shipping.manage`.
  - Frontend: SettingsEcommerce is now the tab's cards, each by permission, its form moved to SettingsEcommerceGeneral. SettingsEcommerceShipping with ShippingZoneBlock, ShippingZoneModal, ShippingMethodModal, ShippingMethodRates and ShippingMethodLimits; SettingsEcommercePickup with PickupPointModal; useEcommerceShipping.
- Payment methods without a gateway (Doc 19 Phase D2, migration 000222): `ecommerce_payment_methods` (`type`, `payment_account_id`, `instructions`, `reference`, `countries`, `min_order` and `max_order`, `delivery`, `is_active`, `position`); `EcommercePaymentMethod`; `EcommercePaymentMethodRepository` (and, for the checkout, `usableIn()`), `EcommercePaymentMethodService`, `EcommercePaymentOptions::for()`; `EcommercePaymentMethodController` (`/business-settings/ecommerce/payment-methods`), `SaveEcommercePaymentMethodRequest`, `EcommercePaymentMethodsResource` and `EcommercePaymentMethodResource`. Permission `ecommerce.payments.manage`.
  - Frontend: SettingsEcommercePayments and EcommercePaymentMethodModal, useEcommercePaymentMethods.
- The cart (Doc 19 Phase D3, migration 000223):
  - Tables: `ecommerce_carts` (`token_hash`, `status`, `currency`, totals as last shown, `last_activity_at`, `expires_at`) and `ecommerce_cart_lines` (`catalog_product_id`, `catalog_product_variant_id`, `item_id`, `quantity`, `unit_price` as last shown); `storefronts.price_list_id`; `ecommerce_settings.cart_days`.
  - Models `EcommerceCart` (its statuses, `MAX_LINES`, `MAX_QUANTITY`) and `EcommerceCartLine`; `Storefront::priceList()`.
  - `StorefrontPricing` gains the storefront's price list (`terms()`' `list` and `date`), `unitPrice()` (as sold: the business's tax mode, the store's currency, with its source) and `taxIdOf()`; `PriceListPricing::listPrice()` prices one item from one list. `StorefrontCatalogRepository::shownAmong()`, and its items' `unit` and `purchase_price`.
  - `EcommerceCartRepository`; `StorefrontCartLines::read()` (each line's state and prices, the invoice rows and totals); `StorefrontCartService` (`show()`, `add()`, `update()`, `remove()`, `expire()`); `StorefrontCatalogService::openStore()`. `StoreCartController` (`/stores/{code}/cart`, `/cart/lines`, `/cart/lines/{line}`), `AddStoreCartLineRequest`, `UpdateStoreCartLineRequest`, `StoreCartResource`. `ExpireEcommerceCarts` (`ecommerce:expire-carts`, hourly).
  - `StorefrontService` checks the price list (`priceList()`), and its options give `price_lists` (`StorefrontOptionsResource`).
  - Frontend: StoreAddToCart on a product's page; StoreHeader's cart and count; `pages/store/[code]/cart.vue` with StoreCartLine and StoreCartSummary; `useStoreCart()` and `storeCartProblem()` in `composables/useStoreCart.ts`; SettingsEcommerceGeneral's Keep Carts For; StorefrontFormReach's Price List.
- Checkout and the order (Doc 19 Phase D4, migration 000224):
  - Tables: `ecommerce_orders` - `number` unique in the business, `contact_id` and `contact_created`, its three statuses, `currency`, `exchange_rate`, the business's tax mode and rounding, the totals as shown and as calculated, `customer` and `customer_email` (in small letters), the addresses, `delivery` with `shipping_method_id`, `pickup_point_id` and `delivery_snapshot`, `ecommerce_payment_method_id` with `payment_snapshot` and `payment_reference`, `customer_note`, `source` and `campaign`, `consents`, the customer's address and browser, `access_token_hash` and `placed_at`. `ecommerce_order_lines` - `kind` (product or shipping), the listing, variant and item, the snapshot, prices, source, tax, amounts as calculated and shown, `weight` and `shipped`. `ecommerce_carts.converted_at`, and `ecommerce_settings.checkout_phone` and `checkout_notes`.
  - Models `EcommerceOrder` (its statuses, deliveries and `NUMBER_SEQUENCE`) and `EcommerceOrderLine`; `Workspace::ecommerceOrders()`.
  - `StorefrontCheckout::quote()` (the offers, the choice and the totals; `ships()`, `weightOf()`), `StorefrontOrderPlacement` (`review()`, `place()`: one order per cart, its key `hash_hmac` of the cart's token), `StorefrontCustomerContacts` (`contactFor()`, `recordConsent()`), `EcommerceOrderRepository` (and `contactMadeFor()`, `usesStorefront()`, `usesContact()`, `usesListing()`, `usesItem()`); `EcommerceCartRepository::findByToken()`; `StorefrontCatalogService::openStore()`' `open` and `policiesOf()`; `StorefrontPricing::shownAmount()`, `shownBase()` and `inCurrency()`.
  - `StoreCheckoutController` (`/stores/{code}/checkout/quote`, `/checkout`), `QuoteStoreCheckoutRequest`, `PlaceStoreOrderRequest`, `StoreCheckoutResource`, `StoreOrderResource`.
  - Kept for its orders: `StorefrontService::delete()`, `ContactService::delete()` and `ItemService::delete()` refuse; `CatalogService::remove()` archives a listing on an order.
  - Frontend: `pages/store/[code]/checkout.vue` with StoreCheckoutCustomer, StoreCheckoutAddresses and StoreCheckoutAddress, StoreCheckoutDelivery, StoreCheckoutPayment, StoreCheckoutReview and StoreOrderPlaced; `useStoreCheckout()` and `useStoreCampaign()` (the storefront layout remembers a visit's campaign); the checkout's shared styles (`.store-step`, `.store-choice`) in `main.css`; SettingsEcommerceGeneral's Phone at Checkout and notes.
- The order's page and its confirmation e-mail (Doc 19 Phase D5, migration 000225):
  - `ecommerce_order_emails` (`kind`, `to`, `status`, `sent_at`, `error`); `EcommerceOrderEmail` and `EcommerceOrder::emails()`.
  - `StoreOrderMailer` (`queue()`, `deliver()`, `failed()`; the order as its e-mail shows it, in the business's formats), the encrypted job `SendStoreOrderEmail` (three tries) and `StoreOrderMail`, with `emails/store-order-received` and its plain-text part; `StorefrontOrderPlacement` queues it and gives the link (`linkOf()`).
  - The order's page: `StorefrontOrderPage::show()` and `EcommerceOrderRepository::forCustomer()`; `StoreOrderController` (`GET /stores/{code}/orders/{key}`).
  - Frontend: `pages/store/[code]/orders/[key].vue`; StoreOrderPlaced shows a placed order (`fresh`, with its page's link) or the order's page, with its statuses in words.
- The customer's account (Doc 19 Phase E1, migration 000226):
  - Tables: `storefront_customers` (`workspace_id`, `contact_id` for Phase E2, `storefront_id` it signed up on, `name`, `email` in small letters and unique in the business, `phone`, `password`, `email_verified_at`, `status` active or closed, `closed_at`, `last_signed_in_at`), `storefront_customer_tokens` (`token_hash`, `last_used_at`, `expires_at`) and `storefront_customer_codes` (`purpose` verify or reset, `code_hash`, `attempts`, `expires_at`, `used_at`).
  - Models `StorefrontCustomer`, `StorefrontCustomerToken` and `StorefrontCustomerCode`; `StorefrontCustomerRepository`.
  - `StorefrontCustomerAuth` (signing up, verifying, a new code, signing in and out, a forgotten password, `fromToken()` and `signIn()`), `StorefrontCustomerCodes` (`issue()`, `cooldown()`, `check()`) with `StoreCustomerCodeMail` (`emails/store-customer-code` and its plain-text part, sent at once, not queued), and `StorefrontCustomerAccount` (the details, a new password, closing).
  - The `store.customer` middleware (`EnsureStoreCustomer`) reads `X-Customer-Token` and gives the request `storeCustomerToken` and `storeCustomer`; `store.customer:optional` lets a visitor not signed in through (for Phase E2's checkout). Signing in's tries are counted by `RateLimiter` in the service; the routes that send e-mails have `throttle:10,1,store-account-mail`, whose prefix keeps their count apart from the store group's.
  - `StoreAccountAuthController` and `StoreAccountController` (`/stores/{code}/account...`); eight form requests in `Requests/Api/Ecommerce`; `StoreCustomerResource` and `StoreSignInResource` (the token once, `verify` while there is none).
  - Frontend: `useStoreAccount()` and `useStoreCodeWait()` in `composables/useStoreAccount.ts` (the token in the `izsuite_customer_{code}` cookie; the e-mail being verified or reset, and when its code was sent, shared by the pages); `pages/store/[code]/account/` - `index`, `sign-in`, `sign-up`, `verify`, `forgot` and `reset`; StoreAccountCard, StoreAccountProfile, StoreAccountPassword and StoreAccountClose; StoreHeader's account link beside the cart.
- The account's contact, orders and addresses (Doc 19 Phase E2, migration 000227):
  - `ecommerce_orders.storefront_customer_id` (`EcommerceOrder::customerAccount()`, `StorefrontCustomer::orders()`), and `storefront_customer_addresses` (`StorefrontCustomerAddress`, its `MAX` and `FIELDS`; `StorefrontCustomer::addresses()`).
  - `StorefrontCustomerContacts::linkAccount()` and `unlinkAccount()`; `ContactRepository::customersWithEmail()` and `findCurrent()`; `EcommerceOrderRepository::ofAccount()`, `findOfAccount()`, `adoptFor()` and `releaseFrom()`. `StorefrontCustomerAuth` links on verifying and on a new password, and unlinks on opening an account again; `StorefrontCustomerAccount::close()` deletes the addresses.
  - `StorefrontOrderPlacement::review()` and `place()` take the signed-in account (`store.customer:optional` on checkout's routes); `StoreCheckoutResource`'s `checkout.signed_in`.
  - `StorefrontCustomerOrders` and `StorefrontCustomerAddresses` (`keepFromOrder()` too); `StoreAccountOrderController` and `StoreAccountAddressController`; `SaveStoreAddressRequest`; `StoreAccountOrderResource` and `StoreAddressResource`. Tests: `Tests\Concerns\SignsInStoreCustomers`.
  - Frontend: `composables/useStoreAccountRecords.ts` (`useStoreAccountOrders()`, `useStoreAddresses()`, `storeAddressFields()`, `storeAddressLine()`); `utils/storeOrders.ts` (an order's statuses in words, shared with StoreOrderPlaced); `pages/store/[code]/account/orders/` (`index`, `[id]`) and `addresses.vue`; StoreAccountNav, StoreAddressForm and StoreSavedAddressPicker; `useStoreCheckout()`'s `fillFromAccount()` and the sign-in sent with checkout's calls; the account pages' shared styles (`.store-account-page`, `.store-account-back`) and warnings' links in `main.css`.
- What a signed-in customer is offered (Doc 19 Phase E3, migration 000228):
  - `storefront_customers.language`; `CatalogProduct::VISIBLE_PUBLIC`, `VISIBLE_CUSTOMERS` and `VISIBLE_LINK`.
  - `StoreVisitor`: the request's customer, set and let go by `EnsureStoreCustomer` - `store.customer:optional` is on every store call of the catalog, cart and checkout - with `listed()` and `reachable()`. `StorefrontCatalogRepository` shows the listings by it (but the sitemap's, for everyone alone), and `StorefrontPricing::terms()` gives `lists`: the customer's own (`PriceListRepository::eligible()`, less those for every customer), then the storefront's.
  - `StorefrontCustomerAccount::view()` and `update()` - the language among `languagesFor()`, marketing by `StorefrontCustomerContacts::marketingOf()`, `setMarketing()` and `languageChosen()` - with `SaveStoreAccountRequest`'s `language` and `marketing`, and `StoreAccountResource`. `linkAccount()` gives the contact the account's language; `recordConsent()` records nothing while consent stands given; `StorefrontOrderPlacement::review()`'s `marketing` is `StoreCheckoutResource`'s `checkout.marketing`.
  - Frontend: `useStoreSignIn()` in `composables/useStoreAccount.ts` - the token read as it is now (`headers()`), and whom data was loaded for (`who()`, in the keys) - used by `useStoreCalls()` in `useStore.ts`, `useStoreCart()` and `useStoreAccount()`. StoreAccountProfile's language and marketing; StoreCheckoutReview's line for a customer who takes marketing already. Tests: `EcommerceSignedInCustomersTest`.
- The Orders page and an order's page (Doc 19 Phase F1, migration 000229):
  - Permission `ecommerce.orders.view` (needs `ecommerce.store.view`); routes `api.ecommerce.orders.index`, `.show` and `.activity` (`/ecommerce/orders`, `/{id}`, `/{id}/activity`). The migration adds the Orders menu row; `AppCatalogSeeder` has it for new installs.
  - `EcommerceOrderService` (`list()`, `find()`, `describe()`); `EcommerceOrderRepository::paginateFor()` and `findFor()`, kept to the user's locations by the order's `location_id`; `EcommerceOrder::location()` and `EcommerceOrderLine::priceList()`; `EcommerceOrderController`, `ListEcommerceOrdersRequest`, `EcommerceOrderListResource` and `EcommerceOrderResource`.
  - The timeline: `DiscussedRecords` and `RecordActivityService` take the family `ecommerce_orders` (amounts always shown, as CRM's), `RecordActivityRepository::forEcommerceOrder()`. `ActivityLogService::recordCustomer()` marks its entries `customer: true`, so `ActivityEntryResource` says "The customer" for them instead of izSuite.
  - Frontend: `pages/ecommerce/orders/` (`index`, `[id]`); EcommerceOrdersToolbar, EcommerceOrderCustomer, EcommerceOrderDelivery, EcommerceOrderLines and EcommerceOrderDetails; `composables/useEcommerceOrders.ts`; `utils/ecommerceOrders.ts` (the statuses' labels and pills for the business). RecordTimeline's root class is `record-timeline` now: `.timeline` was the About page's, whose centre line and width it took. Tests: `EcommerceOrdersTest`.
- Confirming an order, its invoice issued (Doc 19 Phase F2, migration 000230):
  - `ecommerce_orders.sales_document_id` (`EcommerceOrder::invoice()`), `confirmed_at` and `confirmed_by` (`confirmer()`); `EcommerceOrder::CONFIRMED`; `EcommerceOrderEmail::ORDER_CONFIRMED`.
  - Permission `ecommerce.orders.confirm` (needs View Orders); routes `api.ecommerce.orders.confirmation` (GET `/{id}/confirmation`, what confirming would do) and `api.ecommerce.orders.confirm` (POST `/{id}/confirm`, idempotent).
  - `EcommerceOrderChecks::run()`: what stops it (not pending, an item inactive, a Limited listing or variant short - the order's lines of it together - or its contact inactive or no customer), what is pointed out (prices now, worked out by `StorefrontPricing` with `StoreVisitor` signed in as the order's account for the moment, and a merged contact) and what it counts down. `EcommerceOrderRepository::forConfirming()` (locked), `update()` and `survivingContact()` (follows `merged_into_id`; `contactMadeFor()` uses it too); `CatalogProductRepository::stockOf()` (locked) and `setLeft()`.
  - `EcommerceOrderInvoicing::issue()`: the invoice through `SalesDocumentRepository::createMadeBy()` with eCommerce's app id, numbered by `DocumentNumbers::next()` at the order's location, its rows from `SalesLines::rows()` with each order line's own tax put back, totalled by `SalesCalculator` at the order's price-tax mode, rounding and rate; the customer snapshot's addresses are the order's (`SalesSnapshots::addressLines()`).
  - `EcommerceOrderConfirmation` (`review()`, `confirm()`): the transaction, the count-down, the order updated, `DocumentLinks::ensure()` for the e-mail's link, and both Activity Log entries. `EcommerceOrderController::confirmation()` and `confirm()`; `EcommerceOrderConfirmationResource`. `EcommerceOrderResource` gives `invoice`, `confirmed_at` and `confirmed_by`; `StoreOrderResource` gives `invoice` (number and `/d/` link) while the invoice has a working link (`SalesDocument::activeLink`, loaded by `forCustomer()` and `findOfAccount()`).
  - The order's e-mails: `StoreOrderMailer::deliver()` words the subject by kind and hands the view `kind` and `invoice`; the views are `emails/store-order` and `store-order-text` now (renamed from `store-order-received`), one for both kinds.
  - `DocumentLinks::url()` builds a secure link from `APP_URL` with the route's path, not from the request's host: the store's pages rendered on the server ask the API at `http://api:8000`.
  - Frontend: EcommerceOrderConfirm (the dialog); the order's page has Confirm Order (pending, with the permission), the notice, and when and by whom it was confirmed with its invoice's link (with View Invoices); `useEcommerceOrders()`'s `loadConfirmation()` and `confirmOrder()`; StoreOrderPlaced shows the invoice on the order's own page and the account's. Tests: `EcommerceOrderConfirmTest`.
- An order's payment received or failed (Doc 19 Phase F3, migration 000231):
  - `ecommerce_orders.payment_failures` and `payment_failure_reason`; `EcommerceOrder::PARTIALLY_PAID`, `PAID` and `PAYMENT_FAILED`; `EcommerceOrderEmail::PAYMENT_RECEIVED` and `PAYMENT_FAILED`.
  - Routes under Confirm Orders: `api.ecommerce.orders.payment` (GET `/{id}/payment`, what recording one would do), `.payments.store` (POST `/{id}/payments`) and `.payment-failed` (POST `/{id}/payment-failed`), both idempotent; `EcommerceOrderPaymentController`, `RecordEcommercePaymentRequest`, `FailEcommercePaymentRequest` and `EcommerceOrderPaymentReviewResource`.
  - `EcommerceOrderPayments` (`review()`, `record()`, `fail()`): the order locked by `forConfirming()` (which loads its invoice now); a pending order confirmed first by `EcommerceOrderConfirmation::confirmLocked()` - `confirm()`'s work, inside the caller's transaction; then `SalesPaymentService::record()` with the account of the order's payment snapshot (`PaymentAccountRepository::findFor()` at the user's locations, active and taking customer payments) and the method by its type (bank transfer, mobile money, else cash). An amount over what is owed is refused here first, so Sales' "keep the extra as an advance" never comes up.
  - `EcommerceOrderPaymentStatus::follow()`, called at the end of `InvoiceSettlement::settle()` - every payment, reversal, credit and refund - sets the order's payment status from its invoice (`EcommerceOrderRepository::ofInvoice()`).
  - The order's e-mails: `StoreOrderMailer::queue()` takes a link that may be none and `$details` (amount, reference, what is left), carried by `SendStoreOrderEmail`; the subject, the opening line and the payment note follow the kind (`howToPay()`).
  - `EcommerceOrderResource` gives `invoice.balance_due`, `payments` (the invoice's, with their accounts - loaded by `findFor()`), `payment_failures` and `payment_failure`.
  - Frontend: EcommerceOrderPayment and EcommerceOrderPaymentFailed (the dialogs), EcommerceOrderPayments (the card), and EcommerceOrderConfirmSummary - what confirming does, shared by Confirm Order's dialog and Record Payment's; `useEcommerceOrders()`'s `loadPayment()`, `recordPayment()` and `failPayment()`; F1's `ECOMMERCE_PAYMENT_TYPES` map in `utils/ecommerceOrders.ts` is `ECOMMERCE_PAYMENT_TYPE_NAMES` now. Tests: `EcommerceOrderPaymentsTest`.
- Holding, releasing and cancelling an order (Doc 19 Phase F4, migration 000232):
  - `ecommerce_orders.held_status` and `status_reason`; `EcommerceOrder::ON_HOLD` and `CANCELLED`; `EcommerceOrderEmail::ORDER_CANCELLED`; `ecommerce_settings.review_above` and `hold_for_review` (`SaveEcommerceSettingsRequest`, `EcommerceSettingService::SAVED`, `EcommerceSettingsResource`).
  - Permissions `ecommerce.orders.hold` and `ecommerce.orders.cancel` (each needs View Orders); routes `api.ecommerce.orders.hold` and `.release` (Place Orders On Hold) and `.cancel` (Cancel Orders) - POST `/{id}/hold`, `/release` and `/cancel`, idempotent - in `EcommerceOrderStatusController`, each with `EcommerceOrderReasonRequest`.
  - `EcommerceOrderReview::notes()` (`EcommerceOrderRepository::placedFromEmail()` for an e-mail's orders within a day); `EcommerceOrderService::page()` - `find()` with the notes in `EcommerceOrder::$reviewNotes`, a declared property, so never saved - is what every order-page answer returns now (show, confirm, payments, holds, cancel), and `EcommerceOrderResource` gives them as `review`, with `held_status` and `status_reason`. `StoreOrderResource` gives none of them.
  - `EcommerceOrderHolds` (`hold()`, `release()`, `holdAsPlaced()` - called by `StorefrontOrderPlacement::place()` after the placed entry) and `EcommerceOrderCancellation::cancel()`: the order locked; refused while anything settles its invoice; confirmed once (`confirmed_at`), `EcommerceOrderChecks::taken()` - the count-down's holders, now public - given back with `CatalogProductRepository::setLeft()`; the issued invoice voided by `SalesDocumentFlow::void()`, which revokes its links and logs "Voided invoice …".
  - The cancellation's e-mail: no link, "It will not be sent." (or collected), no instructions, and "If you have paid for it, we will be in touch about your money." while its payment was Pending or Failed.
  - Frontend: EcommerceOrderActions - every action on an order's page, moved from the page: Payment Not Received, Record Payment, Confirm Order, Release, and a More menu with Put On Hold and Cancel Order, one dialog asking why for the three - and EcommerceOrderNotices (on hold or cancelled with why, and Worth a look); `useEcommerceOrders()`'s `changeStatus()`; Settings → eCommerce's Review Orders Above and its checkbox; StoreOrderPlaced shows how to pay only while nothing is paid and the order is not cancelled, and a cancelled order's statuses as Cancelled alone. Tests: `EcommerceOrderHoldsTest`.
- Editing an order's customer, addresses and delivery (Doc 19 Phase F5a, no migration):
  - Permission `ecommerce.orders.edit` (needs View Orders). `Permissions::CONFIRMED_ALONE` keeps Confirm Orders from needing it, where `directNeeds()` makes a group's Confirm need its Edit. Routes, under Edit Orders: `api.ecommerce.orders.customer` (PUT `/{id}/customer`), `.delivery.quote` (POST `/{id}/delivery/quote`, what an address would offer) and `.delivery` (PUT `/{id}/delivery`), in `EcommerceOrderEditController` with `UpdateEcommerceOrderCustomerRequest` and `UpdateEcommerceOrderDeliveryRequest` (checkout's address rules; partial for a quote); `EcommerceOrderDeliveryOptionsResource`. `EcommerceOrderResource`'s `delivery` gives `shipping_method_id` and `pickup_point_id`.
  - `EcommerceOrderEdits`: `locked()` and `checkEditable()` - pending, confirmed or held, nothing paid (`SalesDocument::isSettledAtAll()`, which F4's cancelling uses too), nothing fulfilled; `customer()`; `retotal()` - `EcommerceOrderTotals::recalculate()` (the lines' amounts and shown amounts, and the order's totals, from SalesCalculator at the order's mode, rounding and rate) and, while issued, `EcommerceOrderInvoicing::refresh()` (the invoice's lines, totals, balance and the customer's addresses, from the order); `changes()` - before and after, as the timeline's change list takes them.
  - `EcommerceOrderDeliveryEdit` (`quote()`, `save()`): checkout's own `StorefrontCheckout::offers()` and `shippingRow()` and `StorefrontOrderPlacement::delivery()` - public now - for the order's parcel and at the order's currency, rate and display; `EcommerceOrderRepository::replaceShipping()` and `updateLine()`; the payment method checked by `EcommercePaymentOptions::for()` when the delivery or its country changes.
  - Each edit is one entry - "Changed the customer details of online order …", "Changed the addresses and delivery of …" - its changes under it (`properties.changes`, the timeline's "4 changes"). `ActivityLogService::log()` cuts any description over 255 characters, which MySQL refuses, and keeps it whole in `properties.description`.
  - Frontend: EcommerceOrderCustomerEdit and EcommerceOrderDeliveryEdit (with AddressFields, its `state` the order's `region`; what an address offers asked 400 ms after it stops changing); EcommerceOrderActions' More menu offers Edit Customer and Edit Addresses and Delivery; `useEcommerceOrders()`'s `updateCustomer()`, `quoteDelivery()` and `updateDelivery()`. Tests: `EcommerceOrderEditsTest`.
- An order's products and discount (Doc 19 Phase F5b, migration 000233):
  - Migration 000233: `ecommerce_orders.discount_type` (`EcommerceOrder::PERCENT` or `AMOUNT`, Sales' words), `discount_value` and `discount_reason`.
  - Routes, under Edit Orders: `api.ecommerce.orders.products.offered` (GET `/{id}/products/offered?search=`), `.products` (PUT `/{id}/products`) and `.discount` (PUT `/{id}/discount`), in `EcommerceOrderEditController` with `SearchEcommerceOrderProductsRequest`, `UpdateEcommerceOrderProductsRequest` (each line by id, or by `catalog_product_id` and `catalog_product_variant_id`; 1 to 50 lines, quantities 1 to 999, as a cart's) and `UpdateEcommerceOrderDiscountRequest`; `EcommerceOrderProductOptionsResource`. `EcommerceOrderResource` gives `discount`, `price_tax_mode` and each line's listing and variant; `StoreOrderResource`'s totals give the discount.
  - `EcommerceOrderProductEdit` (`offered()`, `save()`): the store's catalog and prices read with the visitor signed in as the order's account (`StoreVisitor::signIn()`, as F2's `EcommerceOrderChecks::prices()` does) - `StorefrontCatalogRepository::products()` and `shownAmong()`, `StorefrontPricing::unitPrice()`, `priceOf()` and `taxIdOf()` at the store's terms today, shown as the order shows prices; a new line made as placement makes one (`SalesLines::rows()`, `StorefrontCheckout::weightOf()` and `ships()`); the Limited stock from `EcommerceOrderChecks::taken()` before and after, counted when `confirmed_at` is set (as F4's cancelling gives it back); `EcommerceOrderRepository::deleteLine()` and `addLines()`; the shipping by `EcommerceOrderDeliveryEdit::reprice()`.
  - `EcommerceOrderTotals`: `productsBase()` and `discountOff()` - the amount a discount takes off - and `recalculate()` working SalesCalculator out twice: without the discount for what each line shows, with it (an amount) for the totals; `discount_total` as the store shows prices (`shownDiscount()`). `EcommerceOrderInvoicing::issue()` and `refresh()` give the invoice `discount_type` amount and `discount_value` that amount, so Sales totals it alike.
  - `EcommerceOrderEdits::discount()`, `discountWords()` and `checkPaymentLimits()` - the method's `min_order` and `max_order` against a changed total, for products, discount and F5a's delivery. `EcommerceOrderChecks::plain()` is public. `StoreOrderMailer`'s totals give the discount.
  - Frontend: EcommerceOrderProductsEdit (with EcommerceOrderProductPicker, searched 300 ms after typing stops) and EcommerceOrderDiscountEdit; EcommerceOrderActions' More menu: Edit Products, Give a Discount or Change Discount (its edit dialogs' flags in one object now); EcommerceOrderLines: Discount (10%) and Why; StoreOrderPlaced: Discount; `useEcommerceOrders()`'s `offeredProducts()`, `updateProducts()` and `updateDiscount()`. Tests: `EcommerceOrderProductEditsTest`; `EcommerceCheckoutTest`'s order totals give the discount.
- Payment links (Doc 19 Phase F6a, migration 000234):
  - Migration 000234: `ecommerce_payment_links` - `token_hash` (sha256 of a 64-character token, unique), `amount`, `currency`, `payment_methods` ([{id, name}] as sent), `expires_at`, `status` (active, paid or revoked), `sent_to`, `created_by`, `revoked_at`, `revoked_by` and `revoked_why` (revoked, replaced, cancelled or changed) - and `ecommerce_settings.payment_link_days` (7) and `partial_payments` (off). `EcommercePaymentLink::state()` reads an active link past its day as expired; expired is never kept.
  - Routes, under Confirm Orders: `api.ecommerce.orders.payment-link` (GET `/{id}/payment-link`), `.payment-links.store` (POST `/{id}/payment-links`, idempotent) and `.payment-links.revoke` (POST `/{id}/payment-links/{link}/revoke`), in `EcommercePaymentLinkController` with `SendEcommercePaymentLinkRequest` and `EcommercePaymentLinkReviewResource`; the store's `api.stores.pay.show` (GET `/stores/{code}/pay/{token}`) in `StorePaymentLinkController`, with `StorefrontPaymentLinkPage` and `StorePaymentLinkResource`. `EcommerceOrderResource` gives `payment_links` (`EcommerceOrderRepository::findFor()` loads them with their senders).
  - `EcommercePaymentLinks` (`review()`, `send()`, `revoke()`, `methods()`, `url()`): `EcommerceOrderPayments::checkPayable()` and `owed()` - public now; the methods `EcommercePaymentOptions::for()` gives the order, kept to `EcommercePaymentLink::METHOD_TYPES`; `expires_at` the business's today (`BusinessCalendar::today()`) plus the days, at its end, in the app's time zone (Eloquent keeps a Carbon's own zone's wall time, so it is converted first); the e-mail `EcommerceOrderEmail::PAYMENT_LINK` through `StoreOrderMailer` - `asked`, `until`, the link's methods as its payment, no instructions - and the views' `payment_link` case and button.
  - `EcommercePaymentLinkRepository::revokeActive()` and `markActivePaid()`, one UPDATE each: `EcommerceOrderCancellation` revokes (cancelled); `EcommerceOrderEdits::retotal()`, which every F5 edit goes through, revokes when the grand total changes (changed); `EcommerceOrderPaymentStatus::follow()` marks them paid once the order is. `StorefrontPaymentLinkPage` reads a link still active whose order owes nothing as paid, and one whose order is cancelled as revoked, and asks the lesser of the link's amount and what is owed.
  - Frontend: EcommerceOrderPaymentLink (the dialog) and EcommerceOrderPaymentLinks (the card, with Revoke); EcommerceOrderActions' More menu: Send Payment Link; `useEcommerceOrders()`'s `loadPaymentLink()`, `sendPaymentLink()` and `revokePaymentLink()`; `ECOMMERCE_LINK_STATES` and `ECOMMERCE_LINK_REVOKED` in `utils/ecommerceOrders.ts`, and Payment link among the e-mails; `pages/store/[code]/pay/[token].vue` with StorePayByLink (its day written in en-GB at UTC, so the server and the browser write it alike) and `StorePaymentLink` in `useStoreCheckout.ts`; Settings → eCommerce's Payment Links Work For and paying in part. Tests: `EcommercePaymentLinksTest`; `EcommerceStorefrontsTest`'s settings.
- A payment link's customer saying they paid (Doc 19 Phase F6b, migration 000235):
  - Migration 000235: `ecommerce_payment_links.reported_at`, `reported_method` ({id, name, type, payment_account_id} as it was) and `reported_reference`. `EcommercePaymentLink::REPORTED` - shown, not kept: `state()` reads an active link with `reported_at` as reported, past its day or not; `askedOf()` - what it asks of an order owing so much - is shared by the store's page and Record Payment.
  - The store's `api.stores.pay.report` (POST `/stores/{code}/pay/{token}`, the group's throttle) in `StorePaymentLinkController::report()` with `ReportStorePaymentRequest`, and `StorefrontPaymentLinkReport::report()`: the order and the link locked; `StorefrontPaymentLinkPage`'s `find()`, `stateOf()`, `owed()` and `offered()` - public now - for the store, the state, what is owed and the ways still taken; the order's `payment_status` and `payment_reference`; `ActivityLogService::recordCustomer()`. `StorePaymentLinkResource` gives each method's `reference` and `reported`.
  - `EcommercePaymentLinkRepository::reportedFor()` - the order's active link with a report, one at most. `EcommerceOrderPayments` (its `$paymentLinks`; `$links` is DocumentLinks there): `review()` and `record()` start from it - `accountOf()` and `noAccount()` take the report, the recorded method by its type, the link Paid after; `fail()` takes a part-paid order with a report, keeps its status, and clears the report. `EcommerceOrderPaymentReviewResource` gives `amount` and `report`, its `method` and `reference` the service's.
  - Guards while reported: `EcommercePaymentLinks::checkNotReported()` in `review()` and `send()`, and `revoke()` refusing a reported link; `EcommerceOrderEdits::checkEditable()` refusing every F5 edit. Cancelling still revokes it (`revokeActive()`), the report kept.
  - Frontend: StorePayByLinkReport (the choices, the reference, I Have Paid) in StorePayByLink, which shows what was told; the page takes the reported link and the API's message. EcommerceOrderPaymentLinks: the report under a link, Reported's pill (`ECOMMERCE_LINK_STATES`); EcommerceOrderActions: `reported` - Payment Not Received offered for it, no Send Payment Link or edits; EcommerceOrderPayment: the report's line and its amount; EcommerceOrderPaymentFailed: the report cleared. Tests: `EcommercePaymentLinkReportsTest`; `EcommercePaymentLinksTest`'s and `EcommerceOrderPaymentsTest`'s answers with the new fields.
- New Nuxt components may need `docker restart izsuite-frontend` before a running dev server renders them.
- Two exports of one name in `composables/` or `utils/` are auto-imported as one of them, with only a warning in the frontend's log ("Duplicated imports …"): Doc 19 Phase F1's `ECOMMERCE_PAYMENT_TYPES` hid Phase D2's, of another shape, and broke Settings → eCommerce's Payment Methods until Phase F3. Look for the warning after adding an export.

## Small things left

- Doc 19 Phase D was checked through the API, its pages as the server renders them, and its main flows in a headless Chromium (19 steps, after D5). The testing checklist's other items - a storefront's price list on full Sales, guest checkout off, mobile money's reference, a digital product alone, the refusals to delete, the Activity Log - and a look in other browsers are still for a person (blocks D1 to D5).
- Doc 19 Phases E1 to E3 were checked through the API and their main flows in a headless Chromium (9, 7 and 10 steps). Their checklists' other items - E1's Verify Customer E-mail off, a second storefront and another business's, the Activity Log; E2's contact linking in Contacts, the 21st address, reopening; E3's category pages and suggestions signed in, Contacts' view of the language and consent, Verify Customer E-mail off, a second storefront - and a look in other browsers are still for a person (blocks E1 to E3). In browsers without `cookieStore` (Firefox), Nuxt keeps the sign-in's cookie in step by a BroadcastChannel instead: signing in or out there is worth a look.
- Doc 19 Phases F1 to F6b were checked through the API and their main flows in a headless Chromium (7, 8, 8, 8, 7, 7, 9 and 7 steps). Their checklists' other items - F1's two storefronts, the pager, a pickup order and one placed signed in, other records' timelines; F2's taxes on, a variant counted down, the Dashboard and Reports counting the invoice once, the account's page of an order placed signed in, a revoked link; F3's mobile money and pay on pickup, a payment recorded in Sales on an online order's invoice, Payments Received, an account made inactive; F4's three orders from one e-mail, two failed payments, a variant given back, a ready-for-pickup order cancelled; F5a's taxes on shipping, a quoted method, an own shipping address, a held order edited; F5b's variants, an order placed signed in with its own price list, shipping by quantity or weight, taxes shown in prices, a payment method's most; F6a's part-paid order, a link revoked by an edit or a cancelling, one expired, a method made inactive, an order with no method paid from afar; F6b's optional and unasked references, a report on a confirmed order, a way or account made inactive after a report, the customer's order page after one - and a look in other browsers are still for a person (blocks F1 to F6b). F5a's 7 steps passed over two runs, not in one, and F5b's discount step was looked at again after its save outlasted the wait: their scripts are worth running once more whole, with memory to spare (the PC ran low during both, and Claude Code stopped F5a's).
- A payment its customer reported through a link is recorded from the order's page, from the report (Doc 19 Phase F6b). One recorded in Sales instead leaves the report standing until the order is paid in full, or the report is marked not received - so Record Payment would offer it again: record a reported payment from the order's page.
- Holding an online order leaves its payment link working: a payment that comes meanwhile is recorded once the order is released. A link asks the amount it was sent with - never more than the order owes since - and expires at the end of its last day in the business's time zone, so a customer elsewhere may see it end at another hour of their day (Doc 19 Phase F6a).
- Nothing tells the business that a customer reported paying through a link: the order's payment shows Pending - on the Orders page, which filters by it - and its link Reported. A bell notice could come with the order notices that new orders wait for too. After a report the customer's order page still names the order's own way to pay, with their reference ("We will match your payment, reference …"), and a report marked not received leaves the order's reference as they gave it, as F3 leaves checkout's (Doc 19 Phase F6b).
- An online order's invoice changed by its edit (Doc 19 Phase F5a) keeps no earlier version of its own: the order's timeline says what changed, and the invoice's own entry "Changed …" is not written. A revision of the invoice, as Sales keeps them for documents sent again, could come later.
- An order's products edited so that it would deliver nothing - its delivered products all taken off - are refused, rather than the order turned into one with nothing to deliver; nor does an order with nothing to deliver take a delivered product, for want of an address and a method (Doc 19 Phase F5b). Either could come later, its delivery, shipping and fulfilment status following.
- A discount's amount on an online order is taken as the business keeps prices - before tax when it keeps them without - as an invoice's is; a store showing prices with tax shows it with its tax added (Doc 19 Phase F5b). The dialog says which.
- An order's customer is not e-mailed when the business edits it (Doc 19 Phases F5a and F5b): their order page shows it as it is, and the business tells them. An e-mail of the changes could come later.
- An order held or cancelled says why on its page alone; the Orders page shows only its status. A Worth a Look mark on the Orders page, and a filter for it, could come with Phase J's overview.
- `PosReturnRow` is an interface in both `usePosRecords.ts` and `usePosReturns.ts` (Doc 18): the frontend's log warns of it, but a type is gone at run time, so nothing breaks. Rename one when next there.
- A payment recorded on an online order is dated on or after its invoice, as in Sales: a pending order's invoice is dated the day it is confirmed, so a transfer that came days before is recorded that day (Doc 19 Phase F3).
- An online order's invoice says Made In eCommerce, with the order's number as Your Reference (Doc 19 Phase F2). Naming the order under its History's Made From, with a link, can come with Phase J's documents.
- Nothing tells the business of a new online order yet (Doc 19 Phase D4): it shows on the Orders page (Phase F1). An account's link to its contact shows only in the contact's Activity, until Phase F's customer views.
- A storefront customer's sign-in is kept per store, by a cookie of each store's: an account of the business signs in on each storefront, but on each one apart. Customers' codes go out at once, not through the queue, as izSuite's own do, so a mail server away loses that code (reported); the customer asks for another (Doc 19 Phase E1).
- A shipping zone is countries, regions, cities and postal codes: 19.26's "distance or service area" needs maps, and carriers' own rates their integrations. A method's categories are the items' categories (Doc 19 Phase D1).
- A cart takes whole quantities, up to 999 of a product and 50 lines. The shop's sorting by price goes by the items' own prices, even where the storefront sells at a price list (Doc 19 Phase D3).
- An order's link and e-mail use izSuite's address for the store (`FRONTEND_URL/store/{code}`), not its own domain, until the server serves the stores by their domains (Doc 19 Phase D5).
- A storefront customer's language is kept on their account and their contact, and nothing is translated yet: the store's pages and its e-mails stay in the business's own words (Doc 19 Phase E3). Listings for chosen customer groups wait for Doc 21, as 19.8 says.

- `SalesSettingsApiTest` fails since shuvo's `a5e46cb` (pulled 2026-11-19). The Sales settings' notes and terms are now kept as rich text, so plain text comes back wrapped in `<p>`, and the test still sends and expects plain text. Until a frontend editor for them is on `nuxt`, the settings form would show those tags too. Check it with shuvo.
- A POS return without its receipt is not built: the Sales app credits only from an issued invoice, so it needs a credit note of its own first. Its permission, Return Without a Receipt, comes with it (Doc 18 Phase E2).
- The POS Overview and the Dashboard's card show today alone, and the top items by what they took before tax; Phase H's reports bring other days (Doc 18 Phase G2).
- POS's pages of records filter by the registers' cashiers as they are now: someone no longer a register's cashier is not offered, though their sales stay listed. The pages have no dates to filter by; Phase H's reports come with them (Doc 18 Phase G1).
- `AdminUsageTest` failed once in a full run (2026-11-20, Doc 18 Phase G1's): its search for "rahim" also found Karim Stores, whose members' and owner's surnames and e-mails are made up at random - one happened to hold it. It passes alone; naming them in the test would steady it.
- A POS paid out's expense has no tax, whatever its category starts with, and no receipt number; it is recorded without the duplicate check's question (Doc 18 Phase F2).
- A POS cash movement's other account is any active account of the business in the session's currency: a safe is simply a cash account. Above the limit, approving is done on the session's page - or, since Phase I2, by a manager's PIN at the register (Doc 18 Phase F1).
- An exchange whose replacement is never sold keeps its credit on its credit note: a named customer's to use as their credit, the walk-in customer's there until it is refunded from the credit note in Sales. What an exchange pays back is paid to the cent, from the register's cash (Doc 18 Phase E3).
- A POS return's cash refund is paid to the cent: cash rounding does not round refunds. Item conditions are kept for Inventory, which is not built: no stock moves. Each return also logs its credit note's and refund's own entries (Doc 18 Phase E1).
- A POS sale's formal invoice renumbers its own invoice: links, e-mails and log entries made before keep the receipt number. It is offered for the session's last sale; any sale's comes with Phase G's Orders page (Doc 18 Phase D4).
- A POS credit sale's override needs Sales' own Override Credit Limits, as an invoice's does: Doc 18's "Override credit limits" is that permission. A sale left on account is due by the customer's payment terms; a due date typed at the register is not offered (Doc 18 Phase D3a).
- A pending payment is confirmed by hand: a card terminal's or a mobile provider's own confirmation comes with the payment integrations, which never create a payment twice. A declined one is paid from its invoice, in Sales; the session's page lists only what still waits (Doc 18 Phase D2).
- A held order is resumed into the cart and waits no longer: a resumed cart cancelled is gone, so hold it again rather than cancel it. Held orders are seen and cancelled from the selling screen, in one's own session at their register; Phase G's Orders page may list them by register (Doc 18 Phase D1).
- A POS receipt is at least 4.9 inches long: Gotenberg's one-page PDF is never shorter than its window, so a short receipt has paper to spare. It is drawn in the invoice template's formats: a register's own receipt template (section 18.5) is not built. Sending a receipt by a messaging app waits for the messaging integrations; reprints from any sale come with Phase G's Orders page (Doc 18 Phase C4).
- A discount beyond the limit is given by whoever is signed in with Discount Beyond the Limit - or, since Phase I2, approved at the cashier's register by a manager's PIN. Cash rounding rounds a whole sale paid in cash at all, and none paid by other methods alone; one step serves every currency (Doc 18 Phase C3).
- A POS sale's payments are ordinary customer payments, each numbered as a receipt (RCT-00001) and listed in Payments Received; the POS receipt's number is the invoice's (R1-00001). Reversing one there leaves the invoice owed again while its POS order stays completed: Phase E's refunds are the way to correct a sale (Doc 18 Phase C2).
- A POS cart is kept in the browser that made it, for its session, until it is sold or cancelled: another browser or device does not see it, and nothing of it is on the server before Phase C2. Holding orders comes with Phase D (Doc 18 Phase C1).
- An item has one unit, so the selling screen sells in it: choosing another waits for items with more than one. Every item takes quantities to 3 decimals, as sales lines do (Doc 18 Phase C1).
- A register sells by the customer's own price list or its own - never by the business's other lists for every customer, nor Settings → Sales's default one (Doc 18 Phase C1).
- **Doc 05:** the optional Responsible User on a payment account, and the Transfer Fee on a transfer, were never built.
- A disabled Settings section also disables its "Try again" button. Reloading the page works.
- Purchase and expense duplicate warnings can name document numbers from other locations, because that check covers the whole business.
- Replacing the logo or signature deletes the old file, so documents numbered earlier can lose that image.
- Menu Builder has no "Preview sidebar" yet.
- Country localization shows as none.
- On Contacts opened from Sales → Customers, picking another role in the filter leaves Customers highlighted, as the address keeps `role=customer`.
- A sales order line typed in without an item counts as goods to deliver; one of a service item does not.
- A role holds the full Sales permissions added since Doc 16 Phase B - Sales Orders, Deliveries, Customer Advances, Returns, Price Lists, and Prices and Discounts - only once granted: roles made before those phases, or from a template while the business was on Sales Lite, have none. Without Change Prices by Hand, such a role can no longer type an item's price on full Sales. Grant them in Settings → Users & Roles after turning Sales on.
- A delivery note is not e-mailed or shared yet, and whether to make a backorder is asked on each Mark Delivered: Settings → Sales gets its backorder rule in Phase H.
- A customer advance has no receipt PDF or e-mail yet, and is not refunded as money out: it is reversed. Credit applies only within one currency.
- A deposit invoice carries no tax, and is made only from a confirmed order, not from a proforma.
- A return is made from an order or its delivery, not from an invoice, and its credit note is made from the invoice without a link back to it.
- Price lists are for all customers or chosen ones: there are no customer groups yet.
- Amount Received (the Dashboard, the Sales Overview and Payments Received's report) counts invoice payments only, so money paid ahead does not show there. Phase H's Invoice and Payment Report should count advances.
- The Activity Log gets an entry, "POST api/v1/sales/calculate completed with status 200", each time a sales form works out its totals: `LogApiWriteActivity` logs every POST that writes no entry of its own, and calculating writes nothing. Found during Doc 16 Phase G1's check; older than it.
- A customer's credit exposure counts invoices dated up to today, as Outstanding Invoices does, so one dated ahead is left out until its day.
- The customer's link page answers in English, even for a document in another language: only Download PDF is translated there.
- A customer's address on a response is what Laravel sees: behind a proxy it is the proxy's until its address is trusted (see Deploying), and on dev it is Docker's gateway.
- An opportunity cannot move to another pipeline. Edited to a team or location its pipeline is not for, it stays where it is (Doc 17 Phase D3b).
- The Opportunities page's Pipeline and Stage filters offer only pipelines switched on. A switched-off pipeline's opportunities show under Any pipeline (the board can still pick one).
- A contact's Opportunities card lists up to 50 and leaves archived ones out, and its lead source is the first converted lead's, however many there were (Doc 17 Phase F4).
- A business starts with no e-mail templates, and a placeholder with nothing to stand for - an opportunity's name on a lead's e-mail - is left empty (Doc 17 Phase F3b).
- Replies to a CRM e-mail reach the business's own inbox and are not read back; there is no scheduled sending or tracked opening. Send E-mail is on the timeline, so on a lead's and an opportunity's page it is under the Comments card's Timeline tab (Doc 17 Phase F3a).
- Only a sales order's confirmation wins an opportunity, so with Sales Lite one is won by hand; and cancelling the order later leaves it won (Doc 17 Phase F2).
- A document's link to its opportunity is set when it is made: changing its customer later, or the opportunity's contact, leaves it linked, and nothing unlinks one. Documents made before Phase F1 have none. The card's "turn on Sales" path was not checked in the browser: the dev business runs Sales Lite (Doc 17 Phase F1).
- A timeline shows the newest 100 of each part, with no paging further back. Planning or changing an activity shows only in Settings → Activity Log, and a contact's Timeline card is read when their page opens, so an activity completed in the card above it shows once a filter is chosen again (Doc 17 Phase E3).
- A reminder cannot be snoozed, and goes only to the activity's assignee; there is no reminder for the owner of the record it is for (Doc 17 Phase E2).
- An activity, once completed or cancelled, is not reopened; activities are not on the opportunities' board or calendar; the Activities page searches subjects and record names, not places or descriptions; and its For link to a record the user does not see leads to a not-found page (Doc 17 Phase E1).
- The board keeps each column in the list's order: cards are not reordered by hand. Nothing changes a closing day by dragging it on the calendar, and the board's column of 50 has no link to the rest in the list (Doc 17 Phase D4).

## Open decisions

Each of these is yours to settle.

- May a limited Administrator give someone All Locations, or locations outside their own? It is allowed today, and Doc 12 does not say.
- May an account delete its last business? It is allowed today.
- Should Workspaces and Users stay at the bottom of the sidebar, or move under Settings? Doc 01 lists only Apps Center and Settings there.
- Phase B kept the old `user_staff` table. Should it be dropped?
- May a business require two-factor authentication of its users? Doc 12 does not say, so nothing makes anyone turn it on.
- Should View Activity Log keep needing every values and details permission (built in D2)? The other way is to mask them in the log, but masking cannot reach amounts written into an entry's text, such as "Recorded payment PAY-0001 of USD 100.00".
- Should a business over its plan's limits have a deadline to choose what stays active, after which izSuite chooses (the newest users and locations first)? Doc 13 does not say, so today it stays over, with nothing more added, until the owner chooses.
- Should internal notes stay editable after a document is issued or recorded? Today they change only with the rest of the form, and comments carry anything later.
- May a full-access role delete anyone's comment? Today only its author can, and comments are never edited.
- Should Managers type document numbers by hand? Manager roles made from now on may, since a Manager holds everything outside Settings. Roles made before may not until it is ticked.
- Should a completed payment's files need Reverse to be removed (built in E2)? The other way is to treat a payment's files like a draft's, under Record Payment.
- Doc 15 Phase C1 removed the earlier product's Plans & Pricing and Subscription History screens. Should its `plans`, `plan_payment_providers`, `subscriptions` and `invoices` tables be dropped too? Only the API's `/plans` still reads `plans`, and the frontend no longer calls it.
- Should support cases stay in izSuite's own Tickets (Doc 15 Phase B3 kept them there), or come from an outside help desk?
- izSuite's credit comes off the business's next invoice (Doc 15 Phase C2b). Should it come off an invoice already waiting too, by issuing that one again? Today the waiting invoice keeps its amount.
- Should the billing reports count months in a time zone of your choosing? Today they use the stored times (UTC).
- A business keeps the currency its subscription is in, even when it changes its own base currency (Doc 15 Phase C3a); a new subscription or plan change takes the new one when the plan has a price in it. Should a change of base currency move a running subscription too?
- Should plans be priced by country as well as by currency (section 15.9's "country or region")? Today a currency is the region: every business in that currency pays the same.
- Every refund asks for the admin's password and a reason (Doc 15 Phase C2a), since section 15.25 makes large refunds critical. Should a refund under some amount skip the password, and should large ones need a second admin's approval?
- Who in a business sees its support cases? Today the owner sees every one, and anyone else only the ones they opened. A business's Administrators could see them all too.
- Which admin roles open support sessions with edit access? Today only the Super Admin, until a role is given "Support session edit" in Admin > Roles.
- Should Admin > Users treat phone numbers typed differently ("+880 1711-000000" and "+8801711000000") as the same for its shared-phone alert? Today only the same typing matches.
- Should an advance paid back to the customer be a refund of its own (money out, with its receipt), rather than a reversal? Today it is reversed, which takes it out of the account's balance as if it never came.
- Should Settings → CRM let a business rename lead statuses (17.26 lists "Lead statuses" there)? Doc 17 Phase B1 keeps the seven fixed, since 17.7's rules hang on them.
- May a member take a lead from the queue without Assign Leads? Today only someone who may assign leads sees the queue and gives its leads out.
- Should a merge be able to move the other contact's financial records too, when the user ticks it (section 17.21 forbids only moving them silently)? Doc 17 Phase C3 leaves them on the merged contact. That contact's statement, credit exposure and open invoices stay apart from the kept one's.

## Deploying to a server

- Run `php artisan migrate`, then `php artisan db:seed --class=AppCatalogSeeder`. **Never run the full `DatabaseSeeder` on a server:** it adds demo users.
- The host nginx needs `client_max_body_size` of at least 120M, and the image must be rebuilt for the upload limits.
- A database that still has `workspaces.staff_id` links and has not yet run migration 000060 would lose them. Check this on any older server.
- Doc 14: the notification e-mails (Phase B, mentions in comments included) go through the queue worker. The scheduler runs `notifications:usage` hourly (Phase B2), and `model:prune` daily, which clears imports a week old and the one-day keys that stop duplicate creates (Phase A).
- Doc 15 Phase A1: after deploying, every admin is asked to set up two-factor at their next sign-in, so have an authenticator app ready. The new-browser e-mails go through the queue worker.
- Doc 15 Phase B1: the scheduler runs `businesses:close-due` hourly, and the owner's status e-mails go through the queue worker.
- Doc 15 Phase B2: support's account e-mails (the reset link, locking, unlocking, two-factor) go through the queue worker, and the reset link points at `FRONTEND_URL`.
- Doc 15 Phase B4: the scheduler runs `support-sessions:close-expired` every five minutes. A support session's link goes to `FRONTEND_URL`, and its End Session back to the admin panel's own host.
- Doc 15 Phase C1: migration 000131 changes only the admin roles' permissions; check Admin > Roles after it if the server's roles were edited by hand. The owner's subscription notices go through the queue worker.
- Doc 15 Phase C2a: migration 000132 adds the refunds, keeps each transfer confirmed so far as a payment, and gives the roles the payments and refunds. Add `refund.created` and `refund.updated` to the Stripe webhook's events (`charge.refund.updated` on an older API version), or a card refund Stripe takes later stays Waiting. The refund e-mails go through the queue worker.
- Doc 15 Phase C2b: migration 000133 adds izSuite's credit (`subscription_credits`, and `adjustment` on the invoices) and gives the roles the credits and the billing reports. The credit notices go through the queue worker.
- Doc 15 Phase C3a: migration 000134 moves each plan's prices into `package_prices`, in force from the start, records on each subscription the price it pays, and drops the two price columns from `packages`. Look over each plan's Prices card after it. The price-change e-mails go through the queue worker.
- Doc 15 Phase C3b: migration 000135 adds `promotions`, the invoices' `discount`, `promotion_id` and `promotion_code`, and gives the roles Admin > Promotions. Nothing else changes until a code is added.
- Doc 15 Phase D1: no migration. The Overview asks the queue for its size, so the web app needs to reach the queue's Redis, as the workers do.
- Doc 15 Phase D2: migration 000136 adds `workspace_usage_snapshots` and gives the roles Admin > Usage. The scheduler runs `usage:snapshot` daily at 23:50 UTC; a business's history starts the day it first runs.
- Doc 15 Phase D3a: migration 000137 adds `service_events` and gives the roles Admin > Service Health. The scheduler runs `health:heartbeat` each minute, and the workers run its job; restart them after deploying (`php artisan queue:restart`). The web app must reach Gotenberg and write to the files' disk, as the checks do both.
- Doc 15 Phase D3b: migration 000138 adds `incidents` and `incident_notes`, and gives the roles Admin > Incidents.
- Doc 15 Phase E1: migration 000139 adds `feature_flags` and gives the roles Admin > Feature Flags. Deploy the frontend with it: its navigation type reads `features`.
- Doc 15 Phase E2: migration 000140 adds an app's countries, conflicts and release version, makes unlisted apps Drafts, and lets Product Administrators retire apps. Deploy the frontend with it, for the Apps Center's new states. `AppCatalogSeeder` only adds apps that are missing, so it leaves statuses set in the admin alone.
- Doc 15 Phase E3a: migration 000141 adds a plan's reporting and support levels, its cycles and its countries. It gives Billing Starter, Standard and Premium their levels, and every plan both cycles everywhere. Deploy the frontend with it, for the plans' new lines.
- Doc 15 Phase E3b: migration 000142 adds plan versions, making each plan's terms as they stand its version 1, and lets Billing Administrators remove waiting versions. The scheduler runs `plans:advance` hourly; check the scheduler container picks it up after deploying.
- Doc 15 Phase F1: migration 000143 adds announcements and gives their pages to Product, Support and Read-Only Administrators. Deploy the frontend with it, for the banner. Rebuild the admin CSS.
- Doc 15 Phase F2: migration 000144 adds e-mail templates and their versions, and gives their pages to Product, Support and Read-Only Administrators. Nothing changes in what is sent until an admin activates a template. Restart the queue workers (`php artisan queue:restart`), as the e-mails changed.
- Doc 15 Phase F3a: migration 000145 adds `countries` and `country_versions`, filled from the list in the code, and gives the roles Admin > Countries. Nothing changes until an admin changes a country, except that new documents print United States, Canadian and Australian addresses with the city, region and postal code on one line. The scheduler runs `countries:advance` hourly. Deploy the frontend with it, for the setup's time zone. Rebuild the admin CSS, and restart the queue workers.
- Doc 15 Phase F3b: migration 000146 adds `payment_methods.countries`, empty for every method, so nothing changes until an admin chooses countries. Restart the queue workers, as the card charges changed.
- Doc 15 Phase G1: migration 000147 adds `privacy_requests` and gives the roles Admin > Privacy Requests. Restart the queue workers after deploying (`php artisan queue:restart`): a worker started before it does not know the export's link, so the export fails and goes back to Identity checked. The scheduler runs `privacy:purge-exports` hourly. `APP_URL` must be the backend's public address, as the e-mailed link points there. The exports are kept on the `local` disk (`storage/app/private/privacy-exports`), which must persist between deploys for 7 days.
- Doc 15 Phase G2: migration 000148 adds `legal_holds` and gives the roles Admin > Legal Holds. Nothing changes until a hold is placed.
- Doc 15 Phase G3: migration 000149 adds `consent_records` and `users.marketing_consent`, records the terms acceptance of each existing account on its day, and gives the roles Admin > Data Retention. Deploy the frontend with it, for the product news checkbox; the API's account now carries `marketing`. Rebuild the admin CSS, and restart the queue workers.
- Doc 16 Phase A: migration 000150 adds `sales_teams`, `sales_team_members` and the documents' salesperson, team, addresses and delivery columns, and puts Overview and Customers in the full Sales menu. Deploy the frontend with it. Restart the queue workers, as the PDFs changed. A business on Sales Lite sees no change.
- Doc 16 Phase B: migration 000151 adds the sales order columns, each line's delivered, invoiced, returned and credited quantities, a revision's reason, and gives the Sales Orders menu item the orders' View; 000152 adds the invoice policy (Settings → Sales and items) and an invoice's over-invoice reason. Deploy the frontend with it (the old `pages/sales/orders.vue` is gone). Restart the queue workers, as the PDFs changed. Existing roles need the sales order permissions granted.
- Doc 16 Phase C: migration 000153 adds `sales_deliveries` and `sales_delivery_lines` and puts Deliveries in the full Sales menu, after Sales Orders. Deploy the frontend with it. Restart the queue workers, as the PDFs' code changed. Existing roles need the delivery permissions granted.
- Doc 16 Phase D1: migration 000154 adds `customer_advances`, `sales_allocations`, the documents' `allocated_amount` and Settings → Sales's `customer_advances` (on). Deploy the frontend with it. Existing roles need the Customer Advances permissions granted.
- Doc 16 Phase D2: migration 000155 adds the invoices' `is_deposit` and the lines' `deposit_invoice_id`. Deploy the frontend with it. Restart the queue workers, as the PDFs changed.
- Doc 16 Phase E: migration 000156 adds `sales_returns` and `sales_return_lines` and puts Returns in the full Sales menu, after Invoices. Deploy the frontend with it. Existing roles need the Returns permissions granted.
- Doc 16 Phase F1: migration 000157 adds `price_lists`, its customers, locations and rules, and the lines' `price_list_id` and `price_list_rule_id`, and puts Price Lists in the full Sales menu, after Payments Received. Deploy the frontend with it. Existing roles need the Price Lists permissions granted.
- Doc 16 Phase F2: migration 000158 adds the roles' `max_discount` and the documents' `discount_reason`. Deploy the frontend with it. On full Sales a role without "Change Prices by Hand" can no longer type an item's price: grant it to the roles that should.
- Doc 16 Phase G1: migration 000159 adds the customers' credit control (`credit_limit`, `credit_sales`, `credit_action`, `credit_warning`), Settings → Sales's `credit_counts_orders` (off) and the documents' `credit_reason`. Nothing is checked until a customer is given a limit or set to pay ahead. Deploy the frontend with it. Existing roles need the Customer Credit permissions granted; the Finance User template has both.
- Doc 16 Phase G2: migration 000160 adds `customer_responses`, `document_links.expires_at` and Settings → Sales's `link_expiry_days` (none: links never expire) and `link_comments` (on). Links already made keep working as they were. The customer's answers and comments are web routes on the backend's host, so `APP_URL` must be its public address and sessions must work there. Behind a proxy, trust its address (Laravel's `trustProxies` in `bootstrap/app.php`), or every response records the proxy's. Their e-mails go through the queue worker. Deploy the frontend with it.
- Doc 16 Phase H1: migration 000161 adds the rest of Settings → Sales (`new_sales_start_as` invoice, `default_price_list_id`, `deposit_percent`, `backorders` ask, `default_shipping_terms`, `salesperson_is_creator` off), so nothing changes until a business chooses. Deploy the frontend with it: the navigation now carries `workspace.sales`.
- Doc 17 Phase A1: migration 000162 adds `crm_settings` and `crm_options`, and changes the CRM menu where it exists: Deals becomes Opportunities (`/crm/opportunities`), Leads gets the `crm_uses_leads` show-if, and Customers is added. Deploy the frontend with it, which renames the page. A business's lists start the first time its CRM settings are read, so no seeding is needed.
- Doc 17 Phase A2: migration 000163 adds `crm_pipelines`, `crm_stages` and `sales_teams.manager_id`. A business's pipeline starts the first time its CRM settings are read. Deploy the frontend with it: the team form now reads its people from `/business-settings/sales-teams/people`.
- Doc 17 Phase B1: migration 000164 adds `crm_leads` and gives the Leads menu item the leads' View (`crm.leads.view`). Deploy the frontend with it (the Leads placeholder page is replaced). Restart the queue workers: a lead's new owner is told by e-mail through them. Existing roles need the lead permissions granted in Settings → Users & Roles.
- Doc 17 Phase B2a: migration 000165 adds a lead's qualification columns, `crm_lead_items` and `crm_settings.qualification_required`. Deploy the frontend with it. From then on Qualify needs a lead's customer need, unless Settings → CRM says otherwise.
- Doc 17 Phase B2b: no migration. Deploy the frontend with it (a lead's page gains its files, comments and timeline).
- The fix of Doc 16's record locations (2026-11-16): backend only, no migration.
- Doc 17 Phase C1: migration 000166 adds `crm_opportunities` and a lead's conversion columns. Deploy the frontend with it. Existing roles need Convert Leads granted (Settings → Users & Roles).
- Doc 17 Phase C2: migration 000167 adds a lead's match keys - filling them in for the leads there are - and `crm_duplicate_reviews`. Deploy the frontend with it.
- Doc 17 Phase C3: migration 000168 adds `merged_into_id`, `merged_at` and `merged_by` to contacts. Nothing changes until someone merges. Merge Contacts is a new permission: give it to custom roles that need it. Deploy the frontend with it.
- Doc 17 Phase D1: migration 000169 adds an opportunity's person, competitors and tags, `crm_opportunity_items` and `crm_stage_visits` (each opportunity there is starts at its stage), and gives Opportunities in the sidebar its View permission. The Opportunities permissions are new: give them to custom roles that need them (the Sales User template has View, Create and Edit, Change Stages). Deploy the frontend with it.
- Doc 17 Phase D2a: migration 000170 adds how an opportunity ended (won or lost on, by, the won reason, the lost reason, competitor, notes and follow-up day) and its archiving. Mark Won or Lost and Reopen Won or Lost are new permissions: give them to custom roles that need them (the Sales User template has Mark Won or Lost). Deploy the frontend with it.
- Doc 17 Phase D2b: no migration. Deploy the frontend with it (an opportunity's page gains its files, comments and timeline).
- Doc 17 Phase D3a: migration 000171 adds each stage's stale-after days and the details it needs. Nothing changes until Settings → CRM sets them. Deploy the frontend with it.
- Doc 17 Phase D3b: migration 000172 adds `team_ids` and `location_ids` to `crm_pipelines`. Each pipeline there is stays for every team and location until Settings → CRM changes it. Deploy the frontend with it.
- Doc 17 Phase D4: no migration. Deploy the frontend with it (the Opportunities page gains Board and Calendar).
- Doc 17 Phase E1: migration 000173 adds `crm_activities`, whether an activity type needs an outcome (izSuite's Call, Meeting, Demonstration and Site Visit do), and Activities in CRM's sidebar. The Activities permissions are new: give them to custom roles that need them (the Sales User template has both). Deploy the frontend with it.
- Doc 17 Phase E2: migration 000174 adds an activity's reminder. Activities planned before it have none. Reminders need the scheduler running (`crm:remind-activities` every minute) and their e-mails the queue workers; restart them after deploying (`php artisan queue:restart`). Deploy the frontend with it.
- Doc 17 Phase E3: no migration. Deploy the frontend with it (a lead's and an opportunity's Comments card shows Timeline, and a contact's page gains a Timeline card). An activity's steps logged before it name no activity, so they show among a timeline's changes as well as the activity itself.
- Doc 17 Phase F1: migration 000175 adds a sales document's opportunity (`opportunity_id`). Documents made before it have none. Deploy the frontend with it (an opportunity's page gains Sales Documents, and the sales form starts from an opportunity).
- Doc 17 Phase F2: migration 000176 adds Settings → CRM's "Win an opportunity when its sales order is confirmed", on for every business. Deploy the frontend with it.
- Doc 17 Phase F3a: migration 000177 adds `crm_emails`, and a new permission, CRM → E-mail: Send E-mail, given to the Sales User template; give it to custom roles in Settings → Users & Roles. Its e-mails go through the queue: restart the workers after deploying (`php artisan queue:restart`). Deploy the frontend with it.
- Doc 17 Phase F3b: migration 000178 adds `crm_email_templates`; businesses start with none. Deploy the frontend with it.
- Doc 17 Phase F4: no migration. Deploy the frontend with it (a contact's page gains its Opportunities card, and Add Opportunity there starts with them).
- Doc 17 Phase G1: migration 000179 adds the closing-soon period to CRM settings (30 days) and puts Overview first in CRM's menu, for whoever may view opportunities. Deploy the frontend with it.
- Doc 17 Phase G2: migration 000180 puts Forecast after Activities in CRM's menu, for whoever may view opportunities. Deploy the frontend with it.
- Doc 17 Phase G3a: migration 000181 adds when each lead was first assigned and first contacted, and fills both for the leads that exist. Then `queue:restart`, and deploy the frontend with it.
- Doc 17 Phase G3b: migration 000182 adds the two follow-up periods to Settings → CRM (2 and 14 days). Deploy the frontend with it.
- Doc 17 Phase G4: no migration. Deploy the frontend with it (the Dashboard gains its CRM card, and the Leads and Opportunities lists a status each).
- Doc 17 Phase H1: no migration. A new permission, CRM → CRM Reports: View CRM Reports, brings Reports' View with it. Roles made from the Manager template from now on have it; give it to custom roles in Settings → Users & Roles. Deploy the frontend with it (the Reports page gains its CRM group and filters).
- Doc 17 Phases H2 and H3: no migration. Deploy the frontend with them (the Reports page's CRM group gains thirteen reports, and the Pipeline and Stage filters).
- Doc 17 Phase I1: migration 000183 adds `crm_assignment_rules`; businesses start with none, so every lead added without an owner still waits in the queue until rules are set up. A new permission, CRM → Assignment and Scoring Rules: Manage Assignment and Scoring Rules, brings Settings' View with it. Roles made from the Manager template from now on have it; give it to custom roles in Settings → Users & Roles. Deploy the frontend with it.
- Doc 17 Phase I2: migration 000184 adds `crm_scoring_rules`, and the leads' `score` and `score_factors`. Businesses start with no rules, so no lead is scored until rules are set up. The scheduler runs `crm:score-leads` hourly, and the queue worker scores the leads again when the rules change. It uses Phase I1's permission. Deploy the frontend with it.
- Doc 17 Phase I3: migration 000185 adds `marketing_consents`, and the five starting consent purposes to each business whose CRM lists started already. No permission is new: consent follows the lead's and the contact's. Deploy the frontend with it.
- Doc 17 Phase I4: migration 000186 adds `crm_custom_fields`, and `custom_fields` to leads and opportunities. Businesses start with none, so the forms are as before until fields are added. It uses Manage CRM Settings. Deploy the frontend with it.
- Doc 18 Phase A1: migration 000187 adds `pos_settings` and `pos_payment_methods`, and offers Point of Sale in the Apps Center (from Standard, needing Sales Lite) where it was still coming soon. Then run `php artisan db:seed --class=AppCatalogSeeder` for its Apps Center page's words. A new permission, Point of Sale → POS Settings: Manage POS Settings, brings Settings' View and Contacts' View with it; give it to custom roles in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase A2: migration 000188 adds `pos_registers` with its payment methods and cashiers, and the Registers row in Point of Sale's menu (`AppCatalogSeeder` has it for new installs). A new permission, Point of Sale → Registers (View, and Manage Registers), is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase B1: migration 000189 adds `pos_sessions` and `pos_session_closings`, and the Sessions row in Point of Sale's menu (`AppCatalogSeeder` has it for new installs). A new permission, Point of Sale → Sessions (View, and Open and Close Sessions), is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase B2a: migration 000190 adds the variance limit to `pos_settings`, and the supervisor's marks to `pos_session_closings`. Two new permissions, Point of Sale → Sessions: Approve Variances and Close Others' Sessions, and Reopen Closed Sessions, are not given to custom roles: give them in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase B2b: migration 000191 adds counting by notes and coins to `pos_settings` and `pos_session_closings`. No permission is new. Deploy the frontend with it: Variance Needing Approval moves to the Closing Sessions card in Settings → Point of Sale.
- Doc 18 Phase C1: migration 000192 adds Open POS, first in Point of Sale's menu (`AppCatalogSeeder` has it for new installs). A new permission, Point of Sale → Selling: Open POS (with Open and Close Sessions and Contacts' View), is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase C2: migration 000193 adds `pos_orders`, `pos_order_payments` and `pos_order_lines`. A new permission, Point of Sale → Selling: Sell Items (with Open POS), is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it: Open POS gains Pay, and the Sales lists mark POS sales.
- Doc 18 Phase C3: migration 000194 adds the discount limit and the cash rounding step to `pos_settings`, and what an order keeps of its discounts, overrides and rounding. Three new permissions under Point of Sale → Selling - Apply Discounts, Override Prices and Discount Beyond the Limit - are not given to custom roles: give them in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase C4: migration 000195 adds `pos_orders.receipt_prints` and `receipt_texts`. A new permission, Point of Sale → Receipts → Reprint Receipts, is not given to custom roles: give it in Settings → Users & Roles. The receipt needs Gotenberg 8 (its `singlePage`). Deploy the frontend with it.
- Doc 18 Phase D1: migration 000196 adds `pos_held_orders` and `pos_settings.hold_hours`. Two new permissions under Point of Sale → Selling - Hold and Resume Orders, and Cancel Held Orders - are not given to custom roles: give them in Settings → Users & Roles. The scheduler runs `pos:expire-held-orders` hourly. Deploy the frontend with it.
- Doc 18 Phase D2: migration 000197 adds `pos_payment_methods.confirms_later` and what a POS payment keeps of its confirmation - each payment so far given its customer payment's account. A new permission, Point of Sale → Pending Payments → Confirm Pending Payments, is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase D3a: migration 000198 adds `pos_orders.on_account` and `credit_reason`. A new permission under Point of Sale → Selling, Create Credit Sales, is not given to custom roles: give it in Settings → Users & Roles. Credit sales need the full Sales app. Deploy the frontend with it.
- Doc 18 Phase D3b: migration 000199 adds `pos_orders.credit_applied`. Deploy the frontend with it.
- Doc 18 Phase D4: migration 000200 adds `pos_orders.invoiced_at`. Deploy the frontend with it.
- Doc 18 Phase E1: migration 000201 adds `pos_returns` and `pos_return_lines`. A new permission group, Point of Sale → Returns and Refunds - Process Returns and Process Refunds - is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase E2: migration 000202 adds what a return keeps of its refund's method, reason and confirmation. A new permission, Point of Sale → Returns and Refunds → Use Alternative Refund Methods, is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase E3: migration 000203 links a return made as an exchange and the sale of its replacement. No new permission. Deploy the frontend with it.
- Doc 18 Phase F1: migration 000204 adds cash movements and their approval limit. Two new permissions, Point of Sale → Cash Movements → Record Cash Movements and Approve Cash Movements, are not given to custom roles: give them in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase F2: migration 000205 adds what a paid out keeps: its payee, expense category and expense. No new permission. Deploy the frontend with it.
- Doc 18 Phase F3: no migration. Deploy the frontend with it (a session's page gains X Report and Z Report).
- Doc 18 Phase G1: migration 000206 puts Orders, Returns and Refunds, and Cash Movements in Point of Sale's menu. Three new permissions - Point of Sale → Orders → View, Returns and Refunds → View, and Cash Movements → View - are not given to custom roles: give them in Settings → Users & Roles. Deploy the frontend with it.
- Doc 18 Phase G2: migration 000207 puts Overview first in Point of Sale's menu, for whoever may view POS's orders. Deploy the frontend with it (the Overview, and the Dashboard's Point of Sale card).
- Doc 18 Phase G3: no migration. Deploy the frontend with it (the Documents page's Point of Sale source, POS labels and references, and refund receipts).
- Doc 18 Phase H1: no migration. Give View POS Reports (POS Reports) to the custom roles that should see POS's reports. Deploy the frontend with it (the Reports page's Point of Sale group, its Register and Cashier filters, and its empty state).
- Doc 18 Phase H2: no migration, and no frontend change of its own.
- Doc 18 Phase H3: no migration, and no frontend change of its own.
- Doc 18 Phase I1: migration 000208 adds a POS PIN to users, Settings → Point of Sale's lock minutes, and a session's last use and lock. Run `php artisan migrate`. Deploy the frontend with it (the selling screen's Lock and Set PIN, its lock screen, and the lock minutes in Settings → Point of Sale → Selling).
- Doc 18 Phase I2: migration 000209 keeps who approved a price typed over (on order lines) and a refund by another method (on returns). Run `php artisan migrate`. Deploy the frontend with it (Ask a Manager on the selling screen, the cash dialog and the return dialog).
- Doc 18 Phase I3: migration 000210 adds `pos_devices`. Run `php artisan migrate`. Deploy the frontend with it (the Registers page's Devices).
- Doc 19 Phase A1: migration 000211 adds `ecommerce_settings` and `storefronts`, and offers eCommerce in the Apps Center (from Premium, needing Sales Lite) where it was still coming soon, with Storefronts in its menu. Then run `php artisan db:seed --class=AppCatalogSeeder` for its page's words. The new permissions, eCommerce (View eCommerce, Manage Storefront) and eCommerce Settings, are not given to custom roles: give them in Settings → Users & Roles. Deploy the frontend with it (the Storefronts page, and Settings → eCommerce).
- Doc 19 Phase A2: migration 000212 adds each plan's storefront limit - Billing Starter 1, the others none - and its versions'. Deploy the frontend with it (Storage & Usage, and the plans).
- Doc 19 Phase A3: migration 000213 adds `storefront_domains`. Set `ECOMMERCE_STOREFRONT_DOMAIN` (the domain the storefronts' izSuite addresses are under, store.izsuite.com unless set) and `ECOMMERCE_CNAME_TARGET` (where a business points its own domain) in `.env`. On the server: a wildcard DNS record and certificate for the storefront domain, and on-demand certificates for the businesses' own domains, asking `GET /api/v1/storefront-hosts?domain=` first (200 for a name izSuite answers for). A new permission, eCommerce → Domains: Manage Domains, is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it (the Storefronts page's Domains).
- Doc 19 Phase B1: migration 000214 adds `catalog_products` and `catalog_product_storefronts`, and Catalog in eCommerce's menu (`AppCatalogSeeder` has it for new installs). Its pictures are kept on the public disk. A new permission group, eCommerce → Catalog (View, Publish Products, Manage Categories and Collections), is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it (the Catalog page and an item's page).
- Doc 19 Phase B2: migration 000215 adds `catalog_categories`, `catalog_collections` and `catalog_collection_products`, and `custom_attributes` to `catalog_products`. Their pictures are kept on the public disk. Manage Categories and Collections, already in B1's Catalog group, now lets a role change them. Deploy the frontend with it (the Catalog page's Categories and Collections, and a listing's attributes).
- Doc 19 Phase B3: migration 000216 adds `catalog_product_variants`, and `options` to `catalog_products`. Deploy the frontend with it (a listing's Options and Variants, and the Catalog page's variants).
- Doc 19 Phase C1: no migration. Deploy the frontend with it (the storefronts at `/store/{code}`). Their pages render on the server, which reaches the API at `NUXT_API_BASE`; their pictures come from the API's public storage. A storefront's izSuite subdomain and custom domains (Phase A3) still need the server set up to serve it by its host.
- Doc 19 Phase C2: no migration. Deploy the frontend with it (a storefront's category and collection pages, and its search's suggestions).
- Doc 19 Phase C3: no migration. Deploy the frontend with it (a storefront's filters by price, options and attributes).
- Doc 19 Phase C4a: migration 000217 adds `storefronts.theme`, `theme_draft` and `theme_published_at`. A theme's pictures are kept on the public disk. A new permission, eCommerce → Themes and Pages → Manage Themes and Pages, is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it (the Theme page, and the storefront in its theme; `nuxt.config.ts` now keys izSuite's icons, so a store's favicon takes their place).
- Doc 19 Phase C4b: no migration. Deploy the frontend with it (the home page's sections and banners, on the Theme page and on the storefront).
- Doc 19 Phase C5a: migration 000218 adds `storefront_pages`. No new permission: pages come under Manage Themes and Pages. Deploy the frontend with it (the Pages list and editor, and the store's pages and their links in its footer).
- Doc 19 Phase C5b: migration 000219 adds `packages.storefront_page_limit` and `package_versions.storefront_page_limit` (Billing Starter 3, the others no limit). Deploy the frontend with it (pages of the business's own, and the plans' pages per storefront).
- Doc 19 Phase C6a: no migration. Deploy the frontend with it (the storefront's head tags, product data and sitemap, and the Theme page's Search and Sharing). The sitemap's server route calls the API at `NUXT_API_BASE`, else `NUXT_PUBLIC_API_BASE`.
- Doc 19 Phase C6b: migration 000220 adds `ecommerce_settings.cookie_notice` (on) and `cookie_notice_text`. Deploy the frontend with it (Settings → eCommerce's Cookie Notice, and the notice on the storefronts).
- Doc 19 Phase D1: migration 000221 adds `shipping_zones`, `shipping_methods` and `pickup_points`. A new permission, eCommerce → Shipping → Manage Shipping, is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it (Settings → eCommerce's Shipping and Pickup).
- Doc 19 Phase D2: migration 000222 adds `ecommerce_payment_methods`. A new permission, eCommerce → Payment Methods → Manage Payment Methods, is not given to custom roles: give it in Settings → Users & Roles. Deploy the frontend with it (Settings → eCommerce's Payment Methods).
- Doc 19 Phase D3: migration 000223 adds `ecommerce_carts`, `ecommerce_cart_lines`, `storefronts.price_list_id` and `ecommerce_settings.cart_days` (30). Restart the scheduler: carts expire hourly (`ecommerce:expire-carts`). Deploy the frontend with it (Add to Cart, the cart page and the header's cart, Keep Carts For, and a storefront's Price List).
- Doc 19 Phase D4: migration 000224 adds `ecommerce_orders`, `ecommerce_order_lines`, `ecommerce_carts.converted_at`, and `ecommerce_settings.checkout_phone` (required) and `checkout_notes` (on). Deploy the frontend with it (the checkout page, and Settings → eCommerce's Phone at Checkout and notes).
- Doc 19 Phase D5: migration 000225 adds `ecommerce_order_emails`. Restart the queue workers (`php artisan queue:restart`): the order's e-mail is a new job, `SendStoreOrderEmail`. Its link is at `FRONTEND_URL`. Deploy the frontend with it (the order's page).
- Doc 19 Phase E1: migration 000226 adds `storefront_customers`, `storefront_customer_tokens` and `storefront_customer_codes`. The customers' codes are sent at once by the API, so its mail settings must work. The API must let browsers send the `X-Customer-Token` header (Laravel's CORS default allows any). Deploy the frontend with it (the header's Sign in and the store's account pages).
- Doc 19 Phase E2: migration 000227 adds `ecommerce_orders.storefront_customer_id` and `storefront_customer_addresses`. Accounts verified before it are linked to their contacts at their next checkout signed in, or their next new password. Deploy the frontend with it (the account's Orders and Addresses, and checkout signed in).
- Doc 19 Phase E3: migration 000228 adds `storefront_customers.language`. Every store call now reads `X-Customer-Token`, and the storefront's pages rendered on the server send it, so a signed-in customer's pages are their own: nothing in front of the frontend may cache the store's pages across visitors. Deploy the frontend with it (the store as its customer sees it, the account's language and marketing, and checkout's marketing line).
- Doc 19 Phase F1: migration 000229 adds eCommerce's Orders menu row. The new permission, View Orders, is held by full access (the owner and administrators) and by roles made from the Manager template from now on; give it to existing custom roles in Settings → Users & Roles. Deploy the frontend with it (the Orders pages, and RecordTimeline's class).
- Doc 19 Phase F2: migration 000230 adds `ecommerce_orders.sales_document_id`, `confirmed_at` and `confirmed_by`. The new permission, Confirm Orders, is held by full access and by roles made from the Manager template from now on; give it to existing custom roles in Settings → Users & Roles. Restart the queue workers (`php artisan queue:restart`): `SendStoreOrderEmail` now sends the confirmation too, through the renamed views. The confirmation's link is the invoice's secure link, at `APP_URL`. Deploy the frontend with it (Confirm Order and its dialog, and the invoice on the customer's order pages).
- Doc 19 Phase F3: migration 000231 adds `ecommerce_orders.payment_failures` (0) and `payment_failure_reason`. No new permission: its actions are under Confirm Orders. Restart the queue workers (`php artisan queue:restart`): `SendStoreOrderEmail` takes a payment's details now, and a job queued before the deploy still runs without them. Deploy the frontend with it (Record Payment, Payment Not Received, the order's Payments card, and Settings → eCommerce's payment types, which F1 had broken).
- Doc 19 Phase F4: migration 000232 adds `ecommerce_orders.held_status` and `status_reason`, and `ecommerce_settings.review_above` (none) and `hold_for_review` (off). Two new permissions, Place Orders On Hold and Cancel Orders, are held by full access and by roles made from the Manager template from now on; give them to existing custom roles in Settings → Users & Roles. Restart the queue workers: the order e-mail job sends the cancellation too. Deploy the frontend with it (the order's actions and More menu, its notices, Settings → eCommerce's Review Orders Above, and the customer's order page for held, cancelled and paid orders).
- Doc 19 Phase F5a: no migration. A new permission, Edit Orders, is held by full access and by roles made from the Manager template from now on; give it to existing custom roles in Settings → Users & Roles. Deploy the frontend with it (the order's More menu with Edit Customer and Edit Addresses and Delivery, and their dialogs).
- Doc 19 Phase F5b: migration 000233 adds `ecommerce_orders.discount_type`, `discount_value` and `discount_reason` (none). No new permission: Edit Orders. Restart the queue workers: the order e-mails give the discount. Deploy the frontend with it (Edit Products and the discount in the order's More menu, the discount on the order's page and the customer's).
- Doc 19 Phase F6a: migration 000234 adds `ecommerce_payment_links`, and `ecommerce_settings.payment_link_days` (7) and `partial_payments` (off). No new permission: its actions are under Confirm Orders. Restart the queue workers (`php artisan queue:restart`): the order e-mail job sends payment links. A link's page is at `FRONTEND_URL`, its token carried encrypted by the queue and kept only hashed. Deploy the frontend with it (Send Payment Link and the Payment Links card on an order's page, Settings → eCommerce's payment links, and the store's link page).
- Doc 19 Phase F6b: migration 000235 adds `ecommerce_payment_links.reported_at`, `reported_method` and `reported_reference`. No new permission: Record Payment and Payment Not Received stay under Confirm Orders. The store's link page now takes a POST without signing in, held by the store group's throttle. Deploy the frontend with it (the link page's I Have Paid, and the report on an order's Payment Links card, Record Payment and Payment Not Received).
- Subscription billing (Doc 13 Phase C) needs the queue worker running for its e-mails, Gotenberg for its PDFs, an active Bank Transfer payment method with the bank details (Admin > Payment Methods), and izSuite's details in Admin > Settings > Billing. Renewals and grace need the scheduler running (`php artisan schedule:work`, or a cron entry for `schedule:run` every minute), which runs `subscriptions:advance` hourly.
