# izSuite project status

What is done, what comes next, and how to carry on from another PC.

- **Last updated:** 2026-11-09
- **Code:** Doc 13 Phase C2b is committed: frontend `e7d6f59` on `nuxt`, and on `main` the admin commit right after `000444f`.
- **Tests:** 664 backend tests pass (last full run 2026-11-09, on this code).
- **Next step:** Doc 13 Phase D, once its decisions are made (see [What is next](#what-is-next)).

Update this file at the end of every phase and commit it with that phase.

## Starting on another PC

1. Do the [setup](#setting-up-a-new-pc) below.
2. Open `izsuite-admin` in Claude Code and say:

   > Read PROJECT_STATUS.md, then help me settle the decisions for Doc 13 Phase D.

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
| 11 | Business Setup and Settings | Done, except notification preferences (waits for Doc 14) |
| 12 | Users, Roles, Permissions and Locations | Done, except notification preferences (Doc 14) and package change rules (Doc 13) |
| **13** | **Apps Center, Packages, Subscriptions and Billing** | **In progress: A, B and C done. D (online payments) left** |
| 14 | Shared Platform Services | Not started as a doc, parts exist |
| 15 | izSuite System Administration Portal | Not started as a doc, parts exist |
| 16 to 21 | Sales, CRM, POS, eCommerce, Customer Subscriptions, Marketing and Loyalty | Written, not started |
| 22 to 52 | Planned in Doc 00 | Not written yet |

Stock, full Sales, OCR and full Accounting features are left out of the Lite apps on purpose. They wait for their own docs.

## What is next

### 1. Doc 13: Apps Center, Packages, Subscriptions and Billing (next: Phase D)

The gap check was done on 2026-11-08. Doc 13 is split into Phases A to D, with B in two parts and C in three (C1, C2a, C2b). Phases A to C are built (see [Decisions worth knowing](#decisions-worth-knowing)); D waits for your decisions.

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

**Phase D: online payments.** Your chosen provider, with secure tokens, payment status by webhook, retries and the grace period, and payment methods managed through the provider. Billing currency and subscription tax follow the provider and the billing address.

**Waits:** notifications (section 19) for Doc 14. Usage charges such as SMS, AI, extra storage and API (section 17) wait until those services exist. Translated app names wait for Doc 14, and admin-side configuration beyond packages and the catalog waits for Doc 15.

**Needs your decisions before Phase D:** the payment provider and whether it handles subscription tax (Paddle does, as merchant of record; Stripe does not).

**Careful:** the old `plans`, `subscriptions`, `invoices` and `payment_methods` tables and admin screens come from an earlier recruitment product. They work per user account, not per business, and do not fit Doc 13. Phase C replaces them rather than reusing them, except the Bank Transfer payment method.

### 2. Doc 14 and Doc 15

Both start with a gap check, since much was built along the way.

- **Doc 14:** calculation, numbering, currency, tax, payment allocation and PDF and email delivery came with Sales Lite. The notification service does not exist yet, and notification preferences in Docs 11 and 12 wait for it.
- **Doc 15:** the admin panel exists (Clients with Login as, Menu Builder, Packages, apps), but was never checked against this doc.

### 3. The full apps

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
- Plan changes are `SubscriptionChanges`. An upgrade's credit is what the current period was paid (its paid invoice) times the days left, today counted, over the period's days, rounded down to the cent. It is never more than the new price, and nothing is credited across currencies. From a yearly plan an upgrade must stay yearly, so no months are lost. The invoice's kind is `upgrade`, and paying it starts a full period that day, cancelling any open renewal, scheduled downgrade or cancellation.
- While an upgrade waits for payment no renewal is issued. At the period's end an unpaid upgrade is cancelled and the usual renewal and grace follow.
- A downgrade is `scheduled_package_id` and `scheduled_cycle` on the subscription. The renewal is issued for that plan, and the business moves to it as the period ends, paid or not, so a grace runs on the lower plan.
- izSuite's details on invoices are the `billing_*` settings (Admin > Settings > Billing): company name, address, e-mail, tax ID and the days to pay (7). `SettingService` adds them on first visit.
- `/profile` answers in the usual envelopes, and the signed-in user (`UserResource`) carries only their name, e-mail, phone, image, language and time zone.

## Small things left

- **Doc 05:** the optional Responsible User on a payment account, and the Transfer Fee on a transfer, were never built.
- A disabled Settings section also disables its "Try again" button. Reloading the page works.
- Purchase and expense duplicate warnings can name document numbers from other locations, because that check covers the whole business.
- The top bar's location pick only lists and remembers a location. It does not set where new records go.
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

## Deploying to a server

- Run `php artisan migrate`, then `php artisan db:seed --class=AppCatalogSeeder`. **Never run the full `DatabaseSeeder` on a server:** it adds demo users.
- The host nginx needs `client_max_body_size` of at least 120M, and the image must be rebuilt for the upload limits.
- A database that still has `workspaces.staff_id` links and has not yet run migration 000060 would lose them. Check this on any older server.
- Subscription billing (Doc 13 Phase C) needs the queue worker running for its e-mails, Gotenberg for its PDFs, an active Bank Transfer payment method with the bank details (Admin > Payment Methods), and izSuite's details in Admin > Settings > Billing. Renewals and grace need the scheduler running (`php artisan schedule:work`, or a cron entry for `schedule:run` every minute), which runs `subscriptions:advance` hourly.
