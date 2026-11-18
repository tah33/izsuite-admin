<?php

namespace App\Mail\Concerns;

use App\Services\Shared\EmailTemplates;
use Illuminate\Mail\Mailables\Content;

/**
 * For a platform e-mail the admin can write (Doc 15 section 15.20 - Phase
 * F2): its words from the active template for its key in its language,
 * when there is one - else those built into its own view. The mail names
 * its key (EmailTemplateKinds) and gives the values of its variables.
 */
trait UsesEmailTemplate
{
    abstract protected function templateKey(): string;

    /** @return array<string, mixed> variable => value, {link} among them for an e-mail with one */
    abstract protected function templateVariables(): array;

    /** @return array{subject: string, paragraphs: list<string>, button: ?string, link: ?string}|null the admin's words, if any */
    protected function templated(): ?array
    {
        return app(EmailTemplates::class)->for($this->templateKey(), $this->locale ?? app()->getLocale(), $this->templateVariables());
    }

    /** The admin's words in izSuite's e-mail layout. */
    protected function templatedContent(array $words): Content
    {
        return new Content(view: 'emails.template', with: ['words' => $words]);
    }
}
