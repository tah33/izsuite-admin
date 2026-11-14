# izSuite project status

What is done, what comes next, and how to carry on from another PC.

- **Last updated:** 2026-11-14
- **Code:** Doc 15 Phase C3b (promotions) is on `main` in the admin commit right after `e290028`, and on `nuxt` in the frontend commit right after `128f85f`.
- **Tests:** 783 backend tests pass (last full run 2026-11-14, on this code, on the second PC).
- **Next step:** Doc 15 Phase D: the dashboard, usage and service health (see [What is next](#what-is-next)). Trying card payments with a Stripe test account (Doc 13 Phase D2) is still worth doing: Phase C1's Retry Card and Phase C2a's card refunds have only met a faked Stripe.
- **Since A1, every admin signs in with an authenticator app as well.** `admin@izsuite.io` is asked to set it up at its next sign-in.

Update this file at the end of every phase and commit it with that phase.

## Starting on another PC

1. Do the [setup](#setting-up-a-new-pc) below.
2. Open `izsuite-admin` in Claude Code and say:

   > Read PROJECT_STATUS.md, then start Doc 15 Phase D.

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
| **15** | **izSuite System Administration Portal** | **In progress: gap check, Phases A, B and C done (2026-11-14), D next** |
| 16 to 21 | Sales, CRM, POS, eCommerce, Customer Subscriptions, Marketing and Loyalty | Written, not started |
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

### 3. Doc 15: izSuite System Administration Portal (next: Phase D)

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

**Phase D: the dashboard, usage and service health** (sections 4, 18 and 23):

- **The Overview with real figures:**
  - businesses: all, active, on each plan, on trial, past due, in grace and suspended;
  - new sign-ups and active users;
  - the most activated apps, and storage used;
  - subscription revenue and failed payments;
  - failed background jobs, and alerts.

  It filters by dates, country, plan, subscription status, app and currency, and shows no business's transactions.
- **Usage:** each business's users, locations, storage and apps against its limits, with the warning state, as a list to sort and filter. A daily snapshot gives its history.
- **Service health:** the database, file storage, the queue (jobs waiting and failed, and retrying a failed one where safe), e-mail delivery (failed notifications and document e-mails), Stripe (its last webhook and failed calls), imports and exports, failed sign-ins, and alerts. Incidents keep notes, the admin responsible and their status. A retry never sends an invoice, payment or e-mail twice.

**Phase E: the catalogue and packages in depth** (sections 8, 10 and 12):

- **Packages:**
  - their fixed internal code shown;
  - a reporting level and a support level;
  - monthly and yearly each offered or not;
  - the countries a plan is offered in;
  - changes with an effective date and version history. A subscriber keeps the version they subscribed to.
- **Apps:**
  - the statuses Draft, Coming Soon, Published, Temporarily Unavailable, Deprecated and Retired (Available becomes Published);
  - the countries an app is offered in;
  - apps it conflicts with;
  - its release version.

  Retiring an app keeps its data.
- **Feature flags:**
  - each with its name, description and audience: internal, chosen businesses, countries, plans, or a percentage of businesses;
  - its dates, status and the admin responsible, and an emergency off switch;
  - the code asks `Features::enabled()`, and the frontend gets a business's flags from `/navigation`.

  Turning a flag off deletes nothing.

**Phase F: communications, announcements and countries** (sections 19, 20 and 21):

- **E-mail templates** in the admin for the platform's e-mails:
  - which e-mails: verification, invitation, password reset, security alerts, subscription notices, trial ending, renewal reminders, failed payments, grace, suspension, plan changes, usage limits, app announcements and maintenance;
  - each per language, with a subject, message, variables, preview, test send, status and version history.

  Today they are Blade views in the code. Security and billing notices stay ones nobody in a business can turn off.
- **Announcements:**
  - a banner in the workspace, for all businesses, chosen plans, countries, apps or businesses, or for admins only;
  - each with a title, message, dates, priority, a link, and whether it can be dismissed. Emergency and legal ones cannot be.
- **Countries in the admin** (today a fixed list in the code, `App\Support\Countries`), each with the defaults a new business takes: currency, timezone, date and number formats, address format and payment methods, with effective dates. Tax templates and document terminology wait for country localization.

**Phase G: privacy and compliance** (section 24):

- Privacy requests (a data export, closing an account, a correction), each with its identity check noted, its status and its audit entries.
- A business's data exported as a ZIP of CSV files, by a restricted permission, through a link that expires after 7 days.
- Legal holds that stop a closure or deletion, and consent records: terms accepted and marketing.
- Retention rules are shown but not enforced until you set them.

**Suggested to leave, unless you say otherwise:**

- A second admin's approval for critical actions, which section 25 asks for "where configured". The password and a reason come first; approval can follow once there are several admins.
- Configuring how Lite and full apps relate (section 11) in the admin. It stays in the code, since a wrong change could corrupt data, which the section itself forbids.
- API, automation, AI and SMS usage, their charges, setup fees and included usage. As in Doc 13, they wait for those services.
- Health checks for SMS delivery and exchange-rate providers, which don't exist yet: businesses enter their own rates.
- Prices by customer type, since there are no customer types yet.
- IP allow-listing and a separate domain for the admin panel. These are server decisions.
- Deleting a business for good. Closed keeps its data.

### 4. The full apps

After Doc 15, choose which app comes first. Docs 16 to 21 are written. Docs 22 and up must be written before they can be built.

## How we work

Each document goes the same way:

1. **"Start Doc N":** read the doc, compare it with the code, and list what is built and what is missing.
2. Split it into phases (A, B, C...) with suggested defaults written down. Saying "Start Phase X" accepts them. Say so if you want something different.
3. Build the phase: backend, frontend and tests, following `CLAUDE.md`.
4. Check it: the test suite, then in the browser, including as a limited member when roles or locations are involved. Remove any test data from the dev database.
5. Update this file and report back.
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
- Eloquent's strict mode is off, so a model loaded with a column list (`with('workspace:id,name')`) reads every other column as null, with no error. Load whole models for anything handed on to another service: Doc 13's billing reads a business's `package_id`, for one.
- An admin form shows its message, then reloads the page 300 ms later, which takes the message with it. Where the page does not change (Resend Invoice), give the form `data-no-reload` and the message stays.
- The Subscription Invoices buttons ask with the browser's own `confirm()`, which the Claude browser pane cannot answer. Confirm a transfer there with `SubscriptionInvoiceService::confirm()` in tinker instead.
- Billing's notices in the bell go to the business's members with Manage Subscription (`NotificationAudience`). In a test, set the business up with `SetUpNewWorkspace`, or its owner is no member and nothing is sent.
- The app reads a business's `setup_completed` from its cached navigation. After changing `setup_completed_at` by hand, call `WorkspaceRepository::bumpNavVersion()`, or the app keeps sending you to Set Up. Change it with a query (`Workspace::whereKey(1)->update(...)`): it is not fillable, so `$workspace->update()` leaves it as it was, without a word.
- In the Claude browser pane, navigating to the same address with only a different `#part` does not reload the page: reload it, or a check reads the page as it was.
- `sed` in Git Bash here does not match a backslash (`Tests\TestCase`) in its pattern; change such lines with PowerShell's `.Replace()` or the edit tool.
- If `docker` cannot reach its engine (`dockerDesktopLinuxEngine` not found), Docker Desktop has stopped. On the second PC it is installed per user, under `%LOCALAPPDATA%\Programs\DockerDesktop`. Starting it brings the containers back by themselves, but the admin's sign-in is lost with Redis.

### Checking the app as a limited member

1. Copy a small script into the container with `docker cp`, and run it with `docker exec izsuite-api php artisan tinker --execute="require '/tmp/x.php';"`. It creates a throwaway verified user and a `workspace_members` row, then prints `LoginAsService::link($member, $admin)`, a one-time link that works for 60 seconds. No password is needed.
2. Before opening the link, copy the owner's `izsuite_token` cookie into the browser's `localStorage`. Afterwards write it back as the cookie and reload. Login-as clears the token only in that browser, so the owner's session survives.
3. Delete the user and member when finished. If the plan's limits were raised for the test, put them back.

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
| Doc 15: Phase C3b, promotions | the commit after `e290028` | the commit after `128f85f` | 000135 |

Admin Clients and Login as came from shuvo (`1c91dbc`, frontend `b4abf92` and `04ebe7b`).

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
- Promotion codes (Doc 15 Phase C3b, migration 000135) are `promotions`, checked by `App\Services\Billing\Promotions::forSubscribing()` (refusing on the `code` field) and worked out by `discountOn()`. Only `SubscriptionService::subscribe()` and `quote()` take a code.
  - `SubscriptionBilling::quote()` and `issue()` take a `Promotion`; the quote has `discount`, `promotion` and `renews_at`. The discount comes off first, then credits, then tax on what is left. The invoice keeps `discount`, `promotion_id` and `promotion_code` (the code as it was), and `SubscriptionInvoice::net()` takes the discount off too.
  - A use is an invoice with the promotion that is not void (`PromotionRepository::uses()`). `subscribe()` checks the code before its transaction, then again inside it with the promotion row locked.
  - A total of zero with a discount and no credit is confirmed at once, paid with `SubscriptionBilling::PAID_WITH_PROMOTION`; with credit too, it is `PAID_WITH_CREDIT`. The receipt PDF words each.
- Dev's admin CSS is `public/build`, git-ignored and built only when missing (`docker/entrypoint.sh`), so it goes stale. After adding Tailwind classes to admin views, run `docker exec izsuite-api npm run build`; a server builds it fresh on deploy.
- The frontend's location pick is `useLocationPick()`. It is shared state behind the `izsuite_location` cookie, and `''` means All Locations. A pick no longer offered (an inactive location, another business's) counts as All Locations. `forNewRecord()` waits for the locations list, so a form opened right after a reload still gets the pick.

## Small things left

- **Doc 05:** the optional Responsible User on a payment account, and the Transfer Fee on a transfer, were never built.
- A disabled Settings section also disables its "Try again" button. Reloading the page works.
- Purchase and expense duplicate warnings can name document numbers from other locations, because that check covers the whole business.
- Replacing the logo or signature deletes the old file, so documents numbered earlier can lose that image.
- Menu Builder has no "Preview sidebar" yet.
- Country localization shows as none.

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

## Deploying to a server

- Run `php artisan migrate`, then `php artisan db:seed --class=AppCatalogSeeder`. **Never run the full `DatabaseSeeder` on a server:** it adds demo users.
- The host nginx needs `client_max_body_size` of at least 120M, and the image must be rebuilt for the upload limits.
- A database that still has `workspaces.staff_id` links and has not yet run migration 000060 would lose them. Check this on any older server.
- Doc 15 Phase A1: after deploying, every admin is asked to set up two-factor at their next sign-in, so have an authenticator app ready. The new-browser e-mails go through the queue worker.
- Doc 15 Phase B1: the scheduler runs `businesses:close-due` hourly, and the owner's status e-mails go through the queue worker.
- Doc 15 Phase B2: support's account e-mails (the reset link, locking, unlocking, two-factor) go through the queue worker, and the reset link points at `FRONTEND_URL`.
- Doc 15 Phase B4: the scheduler runs `support-sessions:close-expired` every five minutes. A support session's link goes to `FRONTEND_URL`, and its End Session back to the admin panel's own host.
- Doc 15 Phase C1: migration 000131 changes only the admin roles' permissions; check Admin > Roles after it if the server's roles were edited by hand. The owner's subscription notices go through the queue worker.
- Doc 15 Phase C2a: migration 000132 adds the refunds, keeps each transfer confirmed so far as a payment, and gives the roles the payments and refunds. Add `refund.created` and `refund.updated` to the Stripe webhook's events (`charge.refund.updated` on an older API version), or a card refund Stripe takes later stays Waiting. The refund e-mails go through the queue worker.
- Doc 15 Phase C2b: migration 000133 adds izSuite's credit (`subscription_credits`, and `adjustment` on the invoices) and gives the roles the credits and the billing reports. The credit notices go through the queue worker.
- Doc 15 Phase C3a: migration 000134 moves each plan's prices into `package_prices`, in force from the start, records on each subscription the price it pays, and drops the two price columns from `packages`. Look over each plan's Prices card after it. The price-change e-mails go through the queue worker.
- Doc 15 Phase C3b: migration 000135 adds `promotions`, the invoices' `discount`, `promotion_id` and `promotion_code`, and gives the roles Admin > Promotions. Nothing else changes until a code is added.
- Subscription billing (Doc 13 Phase C) needs the queue worker running for its e-mails, Gotenberg for its PDFs, an active Bank Transfer payment method with the bank details (Admin > Payment Methods), and izSuite's details in Admin > Settings > Billing. Renewals and grace need the scheduler running (`php artisan schedule:work`, or a cron entry for `schedule:run` every minute), which runs `subscriptions:advance` hourly.
