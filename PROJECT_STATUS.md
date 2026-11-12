# izSuite project status

What is done, what comes next, and how to carry on from another PC.

- **Last updated:** 2026-11-12
- **Code:** Doc 14 is done. Its last phase, E2, is committed: frontend `b29f546` on `nuxt`, and on `main` the admin commit right after `8e38b43`.
- **Tests:** 718 backend tests pass (last full run 2026-11-12, on this code, on the second PC).
- **Next step:** Doc 15, starting with its gap check (see [What is next](#what-is-next)). Trying card payments with a Stripe test account (Doc 13 Phase D2) is still worth doing.

Update this file at the end of every phase and commit it with that phase.

## Starting on another PC

1. Do the [setup](#setting-up-a-new-pc) below.
2. Open `izsuite-admin` in Claude Code and say:

   > Read PROJECT_STATUS.md, then start the Doc 15 gap check.

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
| **15** | **izSuite System Administration Portal** | **Next: the gap check. Parts exist** |
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

On a server, the Stripe webhook needs two events: `checkout.session.completed` and `payment_intent.succeeded`.

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

### 3. Doc 15: izSuite System Administration Portal (next: the gap check)

It starts with a gap check, since much was built along the way. The admin panel exists (Clients with Login as, Menu Builder, Packages, apps, Subscription Invoices and Taxes, Payment Methods), but was never checked against this doc.

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

- **No passwords or keys in chat or in files.** Test logins are in `database/seeders/UserSeeder.php`.

### Things that catch you out

- After changing jobs or listeners: `docker exec izsuite-api php artisan queue:restart`. Web requests pick up PHP changes by themselves.
- A new frontend component can show up as an unknown tag, with no error. `docker restart izsuite-frontend` fixes it. The same restart is needed after changing `nuxt.config.ts` or removing a component's `<style>` block.
- Install npm packages inside the container: `docker compose exec -T frontend npm install ...`.
- The frontend has no `vue-tsc` or `eslint`, and Pint is not installed despite `pint.json`. So the browser check is what catches frontend mistakes.
- Dev mail is `MAIL_MAILER=log`, so invitation links are in `/app/storage/logs/laravel.log`.

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
- Each sign-in keeps its IP address and browser (`ip_address` and `user_agent` on `personal_access_tokens`), named by `App\Support\DeviceName`. Sign-ins from before E1 show as "Unknown device". Login as sessions are listed too, marked "izSuite support".
- Nobody changes the role they hold, not even its permissions (`RoleService::update`), and the role page shows it read-only. Changing one's own role or locations was already refused.
- Two-factor authentication is an authenticator app (TOTP: six digits, 30 seconds, one step either side), worked out in `App\Support\Totp` with no package. The secret is kept encrypted, the ten recovery codes only as hashes (`App\Support\RecoveryCodes`), and each code works once (`two_factor_last_step`). The QR code is drawn in the browser by the `qrcode` npm package.
- Setting it up, new recovery codes and turning it off each need the password (`TwoFactorService`), so an open session cannot switch it on against its owner.
- With it on, the password step answers with a five-minute challenge instead of a session (`SignInChallenges`, in the cache); `POST /auth/login/two-factor` takes the app's code or a recovery code. Five wrong codes end the challenge. Login as from the admin panel does not ask.
- The `reset-password` rate limit counts a signed-in request by its user's e-mail. It used to put every request without an e-mail - all password changes - in one bucket shared by everybody.

**Apps and plans (Doc 13)**

- An app's page (`/apps-center/{family}`) takes its full description, features and support link from the admin app form. What it adds comes from the code: sidebar pages from `menu_items`, reports from `ReportCatalog::namesFor`, permissions from `Permissions::groupLabelsOf`, and Dashboard sections and quick actions from the frontend's `utils/appContributions.ts`, which the Dashboard itself reads.
- `AppCatalogSeeder` writes the Lite apps' descriptions only where they are empty, so an admin's words are kept. Run it after migrating, as for any deploy.
- Each edition names the lowest active plan that includes it (`PackageRepository::lowestNames`).
- Turning apps on and off needs Manage Apps (`settings.apps`); the owner always may. Turning one off shows its impact first (`GET /apps/{app}/deactivation`). The apps that need it can go with it (`with_dependents`), each fully off, the furthest first; without that it is refused, as before.
- The public Explore Apps and Pricing pages read `GET /catalog`, which needs no sign-in. The hard-coded catalog and price calculator are gone.
- A plan's prices are on `packages` (`price_currency`, `price_monthly`, `price_yearly`), with `trial_days`, `grace_days` and `removes_branding`. An empty price means not announced, and the plan then says "Price to be announced". Billing Starter is free (0). Migration 000112 set Standard and Premium to remove the branding. Trial and grace days are kept for Phase C and do nothing yet.
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
- Who turns off someone's two-factor authentication when they lose both their phone and their recovery codes? Today nobody can; Doc 15's support tools would be the place.
- Should View Activity Log keep needing every values and details permission (built in D2)? The other way is to mask them in the log, but masking cannot reach amounts written into an entry's text, such as "Recorded payment PAY-0001 of USD 100.00".
- Should a business over its plan's limits have a deadline to choose what stays active, after which izSuite chooses (the newest users and locations first)? Doc 13 does not say, so today it stays over, with nothing more added, until the owner chooses.
- Should internal notes stay editable after a document is issued or recorded? Today they change only with the rest of the form, and comments carry anything later.
- May a full-access role delete anyone's comment? Today only its author can, and comments are never edited.
- Should Managers type document numbers by hand? Manager roles made from now on may, since a Manager holds everything outside Settings. Roles made before may not until it is ticked.
- Should a completed payment's files need Reverse to be removed (built in E2)? The other way is to treat a payment's files like a draft's, under Record Payment.

## Deploying to a server

- Run `php artisan migrate`, then `php artisan db:seed --class=AppCatalogSeeder`. **Never run the full `DatabaseSeeder` on a server:** it adds demo users.
- The host nginx needs `client_max_body_size` of at least 120M, and the image must be rebuilt for the upload limits.
- A database that still has `workspaces.staff_id` links and has not yet run migration 000060 would lose them. Check this on any older server.
- Subscription billing (Doc 13 Phase C) needs the queue worker running for its e-mails, Gotenberg for its PDFs, an active Bank Transfer payment method with the bank details (Admin > Payment Methods), and izSuite's details in Admin > Settings > Billing. Renewals and grace need the scheduler running (`php artisan schedule:work`, or a cron entry for `schedule:run` every minute), which runs `subscriptions:advance` hourly.
