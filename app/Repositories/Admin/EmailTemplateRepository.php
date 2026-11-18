<?php

namespace App\Repositories\Admin;

use App\Models\Admin\EmailTemplate;
use App\Models\Admin\EmailTemplateVersion;
use Illuminate\Database\Eloquent\Collection;

/** The platform e-mails written in the admin, and their versions (Doc 15 Phase F2). */
class EmailTemplateRepository
{
    /** Every template, with who saved it last. */
    public function all(): Collection
    {
        return EmailTemplate::query()->with('editor:id,first_name,last_name')->get();
    }

    public function find(string $key, string $language): ?EmailTemplate
    {
        return EmailTemplate::query()->where('key', $key)->where('language', $language)->first();
    }

    /** The active one for $key in the first of $languages that has one. */
    public function activeIn(string $key, array $languages): ?EmailTemplate
    {
        return EmailTemplate::query()->where('key', $key)->whereIn('language', $languages)->where('status', EmailTemplate::ACTIVE)->get()
            ->sortBy(fn (EmailTemplate $template) => array_search($template->language, $languages, true))
            ->first();
    }

    /** $data as $key's template in $language - a new one, or the next version of it - with its version kept. */
    public function save(string $key, string $language, array $data, ?int $by): EmailTemplate
    {
        $template = $this->find($key, $language) ?? new EmailTemplate(['key' => $key, 'language' => $language, 'version' => 0]);

        $template->fill([...$data, 'version' => $template->version + 1, 'updated_by' => $by])->save();

        EmailTemplateVersion::create([
            'email_template_id' => $template->id,
            'version'           => $template->version,
            'subject'           => $template->subject,
            'body'              => $template->body,
            'button'            => $template->button,
            'status'            => $template->status,
            'created_by'        => $by,
        ]);

        return $template;
    }

    /** $template's versions, newest first, each with who saved it. */
    public function versions(EmailTemplate $template): Collection
    {
        return $template->versions()->with('creator:id,first_name,last_name')->get();
    }

    public function findVersion(EmailTemplate $template, int $version): ?EmailTemplateVersion
    {
        return $template->versions()->where('version', $version)->first();
    }
}
