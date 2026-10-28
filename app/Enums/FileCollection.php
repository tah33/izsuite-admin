<?php

namespace App\Enums;

/**
 * The kinds of file a business keeps, each a collection of a record's files:
 * a contact's attachments (Doc 03 section 5), an item's image and a digital
 * product's files (Doc 04 sections 6 and 12), and the business's logo.
 */
enum FileCollection: string
{
    case Attachments = 'attachments';
    case Image = 'image';
    case DigitalFiles = 'digital_files';
    case Logo = 'logo';
    // A document template's signature or stamp image (Doc 06 section 14).
    case Signature = 'signature';

    /**
     * Shown on pages and documents, so anyone with the address may see it.
     * The others download through a short-lived link (Doc 04 section 12:
     * download links are not shown publicly).
     */
    public function isPublic(): bool
    {
        return $this === self::Image || $this === self::Logo;
    }

    /** One per record: a new one replaces it. */
    public function isSingle(): bool
    {
        return $this === self::Image || $this === self::Logo || $this === self::Signature;
    }

    /** The largest file, in kilobytes. */
    public function maxKilobytes(): int
    {
        return match ($this) {
            self::Attachments  => 10 * 1024,
            self::Image        => 2 * 1024,
            self::DigitalFiles => 100 * 1024,
            self::Logo         => 2 * 1024,
            self::Signature    => 1024,
        };
    }

    /** The file types allowed, by extension - checked against the content, not the name. Null for any. */
    public function extensions(): ?array
    {
        return match ($this) {
            self::Attachments  => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'csv', 'txt', 'zip'],
            self::Image        => ['jpg', 'jpeg', 'png', 'webp'],
            self::DigitalFiles => null,
            self::Logo         => ['jpg', 'jpeg', 'png', 'webp'],
            self::Signature    => ['jpg', 'jpeg', 'png', 'webp'],
        };
    }

    /** What one is called in messages and the activity log. */
    public function noun(): string
    {
        return match ($this) {
            self::Attachments  => 'attachment',
            self::Image        => 'image',
            self::DigitalFiles => 'file',
            self::Logo         => 'logo',
            self::Signature    => 'signature',
        };
    }
}
