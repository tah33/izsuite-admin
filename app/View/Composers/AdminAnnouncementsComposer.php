<?php

namespace App\View\Composers;

use App\Services\Shared\Announcements;
use Illuminate\View\View;

/** The announcements for izSuite's admins, above every page of the admin panel (Doc 15 section 15.21 - Phase F1). */
class AdminAnnouncementsComposer
{
    public function __construct(
        private Announcements $announcements,
    ) {}

    public function compose(View $view): void
    {
        $admin = auth()->user();

        $view->with('adminAnnouncements', $admin ? $this->announcements->forAdmins($admin) : collect());
    }
}
