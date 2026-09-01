<?php

namespace Database\Seeders;

use App\Models\Admin\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'id'          => Role::SUPER_ADMIN_ID,
                'name'        => 'Super Admin',
                'permissions' => null,
            ]
        );

        Role::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name'        => 'Admin',
                'permissions' => [
                    'admin.overview',
                    'admin.profile',
                    'admin.profile.update',
                    'admin.profile.password',
                    'admin.profile.destroy',
                    'admin.recruiters.index',
                    'admin.recruiters.show',
                    'admin.recruiters.edit',
                    'admin.recruiters.update',
                    'admin.recruiters.toggle-status',
                    'admin.recruiters.enable-affiliate',
                    'admin.candidates.index',
                    'admin.candidates.show',
                    'admin.candidates.edit',
                    'admin.candidates.update',
                    'admin.candidates.toggle-status',
                    'admin.candidates.enable-affiliate',
                    'admin.affiliates.index',
                    'admin.affiliates.show',
                    'admin.affiliates.discount',
                    'admin.affiliates.referrals.discount',
                    'admin.staff.index',
                    'admin.staff.create',
                    'admin.staff.store',
                    'admin.staff.edit',
                    'admin.staff.update',
                    'admin.staff.toggle-status',
                    'admin.staff.destroy',
                    'admin.roles.index',
                    'admin.roles.create',
                    'admin.roles.store',
                    'admin.roles.edit',
                    'admin.roles.update',
                    'admin.roles.destroy',
                    'admin.tickets.index',
                    'admin.tickets.create',
                    'admin.tickets.store',
                    'admin.tickets.show',
                    'admin.tickets.update',
                    'admin.tickets.reply',
                    'admin.pages.index',
                    'admin.pages.create',
                    'admin.pages.store',
                    'admin.pages.edit',
                    'admin.pages.update',
                    'admin.pages.destroy',
                    'admin.how-it-works.index',
                    'admin.how-it-works.create',
                    'admin.how-it-works.store',
                    'admin.how-it-works.edit',
                    'admin.how-it-works.update',
                    'admin.how-it-works.destroy',
                    'admin.tour-guides.index',
                    'admin.tour-guides.create',
                    'admin.tour-guides.store',
                    'admin.tour-guides.edit',
                    'admin.tour-guides.update',
                    'admin.tour-guides.destroy',
                    'admin.testimonials.index',
                    'admin.testimonials.create',
                    'admin.testimonials.store',
                    'admin.testimonials.edit',
                    'admin.testimonials.update',
                    'admin.testimonials.destroy',
                    'admin.companies.index',
                    'admin.companies.create',
                    'admin.companies.store',
                    'admin.companies.edit',
                    'admin.companies.update',
                    'admin.companies.destroy',
                    'admin.faqs.index',
                    'admin.faqs.create',
                    'admin.faqs.store',
                    'admin.faqs.edit',
                    'admin.faqs.update',
                    'admin.faqs.destroy',
                    'admin.contact-messages.index',
                    'admin.contact-messages.show',
                    'admin.contact-messages.reply',
                    'admin.activity-logs.index',
                    'admin.subscriptions.index',
                    'admin.subscriptions.create',
                    'admin.subscriptions.store',
                    'admin.subscriptions.invoice',
                    'admin.subscriptions.pause',
                    'admin.subscriptions.resume',
                    'admin.plans.index',
                    'admin.plans.create',
                    'admin.plans.store',
                    'admin.plans.edit',
                    'admin.plans.update',
                    'admin.plans.destroy',
                    'admin.credit-packs.index',
                    'admin.credit-packs.create',
                    'admin.credit-packs.store',
                    'admin.credit-packs.edit',
                    'admin.credit-packs.update',
                    'admin.credit-packs.destroy',
                    'admin.payment-methods.index',
                    'admin.payment-methods.create',
                    'admin.payment-methods.store',
                    'admin.payment-methods.edit',
                    'admin.payment-methods.update',
                    'admin.payment-methods.destroy',
                    'admin.settings.index',
                    'admin.settings.update',
                    'admin.settings.test-mail',
                    'admin.languages.index',
                    'admin.languages.create',
                    'admin.languages.store',
                    'admin.languages.edit',
                    'admin.languages.update',
                    'admin.languages.destroy',
                    'admin.languages.translate',
                    'admin.languages.save-translations',
                    'admin.currencies.index',
                    'admin.currencies.create',
                    'admin.currencies.store',
                    'admin.currencies.edit',
                    'admin.currencies.update',
                    'admin.currencies.destroy',
                    'admin.currencies.save-formatting',
                    'admin.switch-language',
                    'admin.switch-currency',
                ],
            ]
        );

        Role::updateOrCreate(
            ['slug' => 'staff'],
            [
                'name'        => 'Staff',
                'permissions' => [
                    'admin.overview',
                    'admin.profile',
                    'admin.profile.update',
                    'admin.profile.password',
                    'admin.tickets.index',
                    'admin.tickets.create',
                    'admin.tickets.store',
                    'admin.tickets.show',
                    'admin.tickets.update',
                    'admin.tickets.reply',
                    'admin.activity-logs.index',
                    'admin.switch-language',
                    'admin.switch-currency',
                ],
            ]
        );

        Role::firstOrCreate(
            ['slug' => 'recruiter'],
            [
                'name'        => 'Recruiter',
                'permissions' => null,
            ]
        );

        Role::firstOrCreate(
            ['slug' => 'candidate'],
            [
                'name'        => 'Candidate',
                'permissions' => null,
            ]
        );
    }
}
