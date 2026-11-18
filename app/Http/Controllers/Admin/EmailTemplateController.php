<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveEmailTemplateRequest;
use App\Services\Admin\EmailTemplateAdminService;
use Illuminate\Http\Request;

/** Admin > E-mail Templates (Doc 15 section 15.20 - Phase F2): the platform's e-mails written per language, with preview, test delivery and version history. */
class EmailTemplateController extends Controller
{
    public function __construct(
        protected EmailTemplateAdminService $templates,
    ) {}

    public function index()
    {
        try {
            return view('admin.email-templates.index', $this->templates->index());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(string $key, string $language)
    {
        try {
            return view('admin.email-templates.edit', $this->templates->form($key, $language));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveEmailTemplateRequest $request, string $key, string $language)
    {
        try {
            $template = $this->templates->save($key, $language, $request->validated(), $request->user());

            return $this->adminSuccess($request, "Saved as version {$template->version}, ".($template->isActive() ? 'active: it is sent from now.' : 'a draft: the built-in words are still sent.'), route('admin.email-templates.edit', [$key, $language]));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The template with its samples, as the e-mail shows them. */
    public function preview(string $key, string $language)
    {
        try {
            return response($this->templates->preview($key, $language));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The template with its samples, sent to the admin's own address. */
    public function test(Request $request, string $key, string $language)
    {
        try {
            return $this->adminSuccess($request, 'A test is on its way to '.$this->templates->test($key, $language, $request->user()).'.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** An earlier version saved again as the newest. */
    public function restore(Request $request, string $key, string $language, int $version)
    {
        try {
            $template = $this->templates->restore($key, $language, $version, $request->user());

            return $this->adminSuccess($request, "Version {$version} is restored as version {$template->version}.", route('admin.email-templates.edit', [$key, $language]));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
