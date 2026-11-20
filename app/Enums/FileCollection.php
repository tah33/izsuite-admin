<?php

namespace App\Enums;

/**
 * The kinds of file a business keeps, each a collection of a record's files:
 * a contact's attachments (Doc 03 section 5), an item's image and a digital
 * product's files (Doc 04 sections 6 and 12), the business's logo, and an
 * online product's pictures (Doc 19 Phase B1).
 */
enum FileCollection: string
{
    case Attachments = 'attachments';
    case Image = 'image';
    case DigitalFiles = 'digital_files';
    case Logo = 'logo';
    // A document template's signature or stamp image (Doc 06 section 14).
    case Signature = 'signature';
    // Doc 19 Phase B1: the pictures a storefront shows of a product, beside its item's image.
    case Gallery = 'gallery';
    // Doc 19 Phase C4a: a storefront's own pictures - its logo and favicon - that its theme shows.
    case Branding = 'branding';

    /**
     * Shown on pages and documents, so anyone with the address may see it.
     * The others download through a short-lived link (Doc 04 section 12:
     * download links are not shown publicly).
     */
    public function isPublic(): bool
    {
        return $this === self::Image || $this === self::Logo || $this === self::Gallery || $this === self::Branding;
    }

    /** One per record: a new one replaces it. */
    public function isSingle(): bool
    {
        return $this === self::Image || $this === self::Logo || $this === self::Signature;
    }

    /** A picture: kept with a thumbnail beside it. */
    public function hasThumbnails(): bool
    {
        return $this === self::Image || $this === self::Gallery;
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
            self::Gallery      => 5 * 1024,
            self::Branding     => 2 * 1024,
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
            self::Gallery      => ['jpg', 'jpeg', 'png', 'webp'],
            self::Branding     => ['jpg', 'jpeg', 'png', 'webp'],
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
            self::Gallery      => 'picture',
            self::Branding     => 'picture',
        };
    }
}
