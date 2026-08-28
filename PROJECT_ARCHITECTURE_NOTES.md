# IZSuite Admin

## Project Architecture & Folder Structure Notes

**Baseline captured:** 31 July 2026  
**Workspace:** C:\laragon\www\izsuite-admin

> এই file-টি project-এর living architecture reference। Folder structure, namespace, module boundary বা refactor নিয়ে কাজ করার আগে এই document পড়তে হবে এবং পরিবর্তনের পরে update করতে হবে।

## 1. Project purpose

শুধু নিচের ৪টি layer-এর code role/domain ভিত্তিকভাবে organise করা হবে:

- Admin
- User
- Frontend
- Billing
- Accounting

Shared code আলাদা Shared, Common, Support অথবা existing Laravel infrastructure folder-এ থাকবে। অন্য সব folder-এর বর্তমান structure অপরিবর্তিত থাকবে।

## 2. Project snapshot

| Item | Current value |
|---|---|
| Framework | Laravel 12 |
| PHP | ^8.2 |
| Autoloading | PSR-4: App -> app/; Database Factories -> database/factories/; Database Seeders -> database/seeders/ |
| Frontend build | Vite / npm |
| Authentication/API | Laravel Sanctum; auth, web, api এবং admin route files |
| Major packages | Octane, Dompdf, Sanctum, Pusher, PDF parser |
| Other folders | Migration, Database, Seeder, Request, Resource, View ইত্যাদি বর্তমান structure-এই থাকবে |

## 3. Current structure observed

এটি refactor-এর আগের snapshot; এটি target structure নয়।

| Area | Current state |
|---|---|
| Controllers | app/Http/Controllers/Admin আছে; Api, Auth এবং root-level controller-ও আছে |
| Models | app/Models-এ model files mostly flat |
| Services | app/Services/Admin, Api, Invoices, Support আংশিকভাবে grouped |
| Repositories | app/Repositories-এ files mostly flat |
| Requests / Resources | Admin, Api, Auth এবং ContactMessages subfolders আংশিকভাবে আছে |
| Views | resources/views/admin-এর ভিতরে feature folders আছে; auth, emails, pdf, components আলাদা |
| Migrations / Seeders | database/migrations এবং database/seeders mostly flat |
| Shared infrastructure | Middleware, Providers, Events, Listeners, Mail, QueryFilters, Support আলাদা আছে |

## 4. Target module pattern

একই business module-এর code বিভিন্ন layer-এ consistent domain folder-এ থাকবে।

| Layer | Target pattern |
|---|---|
| Controllers | app/Http/Controllers/{Admin|User|Frontend|Billing|Accounting|Api|Auth}/ |
| Models | app/Models/{Admin|User|Frontend|Billing|Accounting}/ |
| Repositories | app/Repositories/{Admin|User|Frontend|Billing|Accounting}/ |
| Services | app/Services/{Admin|User|Frontend|Billing|Accounting}/ |
| Requests | app/Http/Requests/{Admin|User|Frontend|Billing|Accounting}/ |
| Resources | app/Http/Resources/{Admin|User|Frontend|Billing|Accounting}/ |
| Other areas | Migration, Database, Seeder, Request, Resource, View ইত্যাদিতে domain pattern ব্যবহার করা হবে না |

## 5. Proposed structure

    app/
    ├── Http/Controllers/Admin, User, Frontend, Billing, Accounting, Api, Auth
    ├── Http/Requests
    ├── Http/Resources
    ├── Models/Admin, User, Frontend, Billing, Accounting, Shared
    ├── Repositories/Admin, User, Frontend, Billing, Accounting, Shared
    └── Services/Admin, User, Frontend, Billing, Accounting, Shared

    database/                 # বর্তমান structure অপরিবর্তিত
    resources/views/          # বর্তমান structure অপরিবর্তিত

## 6. Example

    app/Http/Controllers/Admin/TicketController.php
    app/Http/Controllers/User/ProfileController.php
    app/Http/Controllers/Frontend/PageController.php
    app/Http/Controllers/Billing/InvoiceController.php
    app/Http/Controllers/Accounting/TransactionController.php
    app/Models/Billing/Invoice.php
    app/Repositories/Admin/TicketRepository.php
    app/Services/Accounting/TransactionService.php
    app/Http/Requests/Admin/Ticket/StoreTicketRequest.php  # এই layer pattern-এর বাইরে
    # Migration, Seeder, Request, Resource, View ইত্যাদি বর্তমান structure-এই থাকবে

## 7. Initial mapping from current code

| Current area | Likely target domain | Note |
|---|---|---|
| app/Http/Controllers/Admin/* | Admin | Admin controllers together থাকবে; namespace/import update করতে হবে |
| app/Services/Admin/* | Admin | Already partially aligned |
| resources/views/admin/* | admin | Already feature-oriented |
| Subscription, Plan, PaymentMethod, Invoice | Billing | Business ownership confirm করে move করতে হবে |
| Ticket, TicketMessage, Department | Admin or User | Workflow ownership দেখে সিদ্ধান্ত নিতে হবে; model duplicate করা যাবে না |
| Page, ContentItem, Category, App, AppCategory | Frontend or Admin | Management layer ও public read layer আলাদা হতে পারে |
| ActivityLog | Shared or Admin | একাধিক module ব্যবহার করলে Shared-এ থাকবে |
| User, Role, Setting, Language, Currency | Shared / Auth / Admin | Cross-cutting; dependency review ছাড়া move করা যাবে না |
| app/Repositories/* | Model/service-এর একই domain | Refactor শেষে কোনো repository unclassified থাকবে না |

## 8. Rules

1. Folder name এবং PHP namespace exactly match করতে হবে।
2. PHP folder-এর জন্য StudlyCase এবং Blade view folder-এর জন্য lowercase ব্যবহার করতে হবে।
3. একটি business feature-এর Controller, Request, Resource, Service, Repository, Model, View এবং tests একই domain boundary-তে থাকবে।
4. Shared code জোর করে Admin/User folder-এ রাখা যাবে না।
5. শুধু controller Admin হওয়ার কারণে model-কে Admin-এ move করা যাবে না; User, API বা Frontend dependency check করতে হবে।
6. Move করার সময় namespace, imports, routes, policies, service bindings, factories, seeders এবং tests update করতে হবে।
7. Migration, Database, Seeder, Request, Resource এবং View-এর বর্তমান structure এই refactor-এ অপরিবর্তিত থাকবে।
8. Canonical spelling হলো **Billing**, **Belling নয়**।
9. নতুন domain বা shared dependency যোগ হলে এই document update করতে হবে।

## 9. Refactor checklist

- [ ] সব file inventory করে domain ownership নির্ধারণ করা
- [ ] একবারে একটি domain refactor করা
- [ ] Target directory তৈরি করা
- [ ] Namespace এবং references update করা
- [ ] Routes ও middleware grouping update করা
- [ ] Composer autoload/cache update করা, যদি প্রয়োজন হয়
- [ ] Formatting, static checks এবং tests চালানো
- [ ] Admin, User, Frontend, Billing এবং Accounting flow manually verify করা
- [ ] Final structure এখানে update করা

## 10. Important status

এই document অনুযায়ী Controller, Model, Repository এবং Service layer refactor করা হয়েছে। Migration, Database, Seeder, Request, Resource এবং View layer এই কাজের scope-এর বাইরে এবং অপরিবর্তিত রাখা হয়েছে।

## 11. Update log

| Date | Change | Status |
|---|---|---|
| 31 July 2026 | Initial architecture notes created from current project snapshot | Baseline |
| 28 August 2026 | Controller, Model, Repository এবং Service domain folders added; other layers unchanged | Refactored |
