<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAnnouncementsRequest;
use App\Http\Requests\Admin\SaveAnnouncementRequest;
use App\Services\Admin\AnnouncementAdminService;
use App\Services\Shared\Announcements;
use Illuminate\Http\Request;

/** Admin > Announcements (Doc 15 section 15.21 - Phase F1): banners for businesses, or for izSuite's admins, between their days. */
class AnnouncementController extends Controller
{
    public function __construct(
        protected AnnouncementAdminService $announcements,
        protected Announcements $shown,
    ) {}

    public function index(ListAnnouncementsRequest $request)
    {
        try {
            return view('admin.announcements.index', ['announcements' => $this->announcements->page($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.announcements.create', $this->announcements->form());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveAnnouncementRequest $request)
    {
        try {
            $announcement = $this->announcements->create($request->validated(), $request->user());

            return $this->adminSuccess($request, "\"{$announcement->title}\" is added.", route('admin.announcements.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.announcements.edit', $this->announcements->form($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveAnnouncementRequest $request, int $id)
    {
        try {
            $announcement = $this->announcements->update($id, $request->validated(), $request->user());

            return $this->adminSuccess($request, "\"{$announcement->title}\" is saved.", route('admin.announcements.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->announcements->delete($id, $request->user());

            return $this->adminSuccess($request, 'The announcement is deleted.', route('admin.announcements.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** An announcement for izSuite's admins, put away by the admin for themselves. */
    public function dismiss(Request $request, int $id)
    {
        try {
            $this->shown->dismiss($id, $request->user());

            return $this->adminSuccess($request, 'Dismissed.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
