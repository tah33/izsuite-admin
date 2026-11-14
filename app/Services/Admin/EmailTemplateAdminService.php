<?php

namespace App\Services\Admin;

use App\Mail\EmailTemplateTest;
use App\Models\Admin\EmailTemplate;
use App\Models\User\User;
use App\Repositories\Admin\EmailTemplateRepository;
use App\Repositories\Admin\LanguageRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\EmailTemplates;
use App\Support\EmailTemplateKinds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Admin > E-mail Templates (Doc 15 section 15.20 - Phase F2): each platform
 * e-mail, written per language. Saving makes the next version; an earlier
 * one can be restored as the newest. Active, a template is sent in place of
 * the built-in words; as a draft it can be previewed and tested first.
 * Every change is in the audit log.
 */
class EmailTemplateAdminService
{
    public function __construct(
        protected EmailTemplateRepository $templates,
        protected LanguageRepository $languages,
        protected EmailTemplates $words,
    ) {}

    /**
     * Every e-mail by group, each with its templates by language, and the languages.
     *
     * @return array{groups: array<string, list<array{key: string, name: string, mandatory: bool, templates: array<string, EmailTemplate>}>>, languages: \Illuminate\Support\Collection}
     */
    public function index(): array
    {
        $saved  = $this->templates->all()->groupBy('key');
        $groups = [];

        foreach (EmailTemplateKinds::ALL as $key => [$name, $group, $mandatory]) {
            $groups[$group][] = ['key' => $key, 'name' => $name, 'mandatory' => $mandatory, 'templates' => ($saved[$key] ?? collect())->keyBy('language')->all()];
        }

        return ['groups' => $groups, 'languages' => $this->languages->getActive()];
    }

    /** The form for $key in $language: the e-mail, its template if saved, its variables and versions. */
    public function form(string $key, string $language): array
    {
        $template = $this->templates->find($this->known($key), $this->language($language)->code);

        return [
            'key'       => $key,
            'name'      => EmailTemplateKinds::name($key),
            'language'  => $this->language($language),
            'template'  => $template,
            'variables' => EmailTemplateKinds::variables($key),
            'button'    => EmailTemplateKinds::button($key),
            'versions'  => $template ? $this->templates->versions($template) : collect(),
        ];
    }

    public function save(string $key, string $language, array $data, User $admin): EmailTemplate
    {
        $this->known($key);
        $code     = $this->language($language)->code;
        $template = DB::transaction(fn () => $this->templates->save($key, $code, [
            'subject' => $data['subject'],
            'body'    => $data['body'],
            'button'  => EmailTemplateKinds::button($key) ? ($data['button'] ?? null) : null,
            'status'  => $data['status'],
        ], $admin->id));

        ActivityLogService::record('updated', "Saved e-mail \"".EmailTemplateKinds::name($key)."\" ({$code}) as version {$template->version}, {$template->status}", $template, null, $admin);

        return $template;
    }

    /** Version $version saved again, as the newest. */
    public function restore(string $key, string $language, int $version, User $admin): EmailTemplate
    {
        $template = $this->templates->find($this->known($key), $this->language($language)->code) ?? abort(404);
        $old      = $this->templates->findVersion($template, $version) ?? abort(404);

        return $this->save($key, $language, $old->only(['subject', 'body', 'button', 'status']), $admin);
    }

    /** The template's words with their samples, as the e-mail shows them. */
    public function preview(string $key, string $language): string
    {
        return view('emails.template', ['words' => $this->sampled($key, $language)])->render();
    }

    /** The template, with its samples, sent to $admin's own address. @return string where it went */
    public function test(string $key, string $language, User $admin): string
    {
        Mail::to($admin->email)->send(new EmailTemplateTest($this->sampled($key, $language)));

        ActivityLogService::record('created', "Sent a test of e-mail \"".EmailTemplateKinds::name($key)."\" ({$language}) to {$admin->email}", null, null, $admin);

        return $admin->email;
    }

    /** @return array{subject: string, paragraphs: list<string>, button: ?string, link: ?string} */
    private function sampled(string $key, string $language): array
    {
        $template = $this->templates->find($this->known($key), $this->language($language)->code)
            ?? throw ValidationException::withMessages(['template' => 'Save the template first: the built-in words are what is sent until then.']);

        return $this->words->fill($template, EmailTemplateKinds::variables($key));
    }

    private function known(string $key): string
    {
        return EmailTemplateKinds::exists($key) ? $key : abort(404);
    }

    private function language(string $code): \App\Models\Admin\Language
    {
        return $this->languages->findActiveByCode($code) ?? abort(404);
    }
}
