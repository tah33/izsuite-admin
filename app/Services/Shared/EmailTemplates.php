<?php

namespace App\Services\Shared;

use App\Models\Admin\EmailTemplate;
use App\Repositories\Admin\EmailTemplateRepository;
use App\Support\EmailTemplateKinds;

/**
 * The platform e-mails' words as the admin wrote them (Doc 15 section 15.20
 * - Phase F2). An e-mail asks for its key in its language: the active
 * template there - else in English - with its variables filled in, or none,
 * and then it sends the words built into the code.
 */
class EmailTemplates
{
    public const FALLBACK = 'en';

    public function __construct(
        private EmailTemplateRepository $templates,
    ) {}

    /**
     * $key's active words in $language, for $variables.
     *
     * @return array{subject: string, paragraphs: list<string>, button: ?string, link: ?string}|null
     */
    public function for(string $key, ?string $language, array $variables): ?array
    {
        $template = $this->templates->activeIn($key, array_values(array_unique(array_filter([$language, self::FALLBACK]))));

        return $template ? $this->fill($template, $variables) : null;
    }

    /**
     * $template's words with $variables filled in - {plan} becomes the plan's
     * name; one not given stays as written - its message as paragraphs, split
     * on blank lines, and the button for an e-mail with a link.
     *
     * @return array{subject: string, paragraphs: list<string>, button: ?string, link: ?string}
     */
    public function fill(EmailTemplate $template, array $variables): array
    {
        $values = collect($variables)->filter(fn ($value) => is_scalar($value) || $value === null)->mapWithKeys(fn ($value, $name) => ['{'.$name.'}' => (string) $value])->all();
        $link   = $variables['link'] ?? null;

        return [
            'subject'    => strtr($template->subject, $values),
            'paragraphs' => array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', strtr($template->body, $values)) ?: []), 'strlen')),
            'button'     => $link ? ($template->button ?: EmailTemplateKinds::button($template->key) ?? 'Open izSuite') : null,
            'link'       => $link,
        ];
    }
}
