<?php

namespace App\Support;

/**
 * One service as Admin > Service Health finds it (Doc 15 section 15.23 -
 * Phase D3a): OK, Warning, Failing - both alerts - or Not used; a line on
 * what it found, and facts beside it (label => value).
 */
final class ServiceStatus
{
    public const OK      = 'ok';

    public const WARNING = 'warning';

    public const FAILING = 'failing';

    public const UNUSED  = 'unused';

    public const LABELS  = [
        self::OK      => 'OK',
        self::WARNING => 'Warning',
        self::FAILING => 'Failing',
        self::UNUSED  => 'Not used',
    ];

    /** The services Service Health checks, by key - which an incident may name too (Phase D3b). */
    public const NAMES   = [
        'app'       => 'The App',
        'scheduler' => 'Scheduler',
        'queue'     => 'Background Jobs',
        'database'  => 'Database',
        'cache'     => 'Cache and Sessions',
        'storage'   => 'File Storage',
        'pdf'       => 'PDF Maker',
        'mail'      => 'E-mail',
        'payments'  => 'Card Payments',
        'imports'   => 'Imports and Exports',
        'security'  => 'Sign-ins and Security',
        'sms'       => 'Text Messages',
        'rates'     => 'Exchange Rates',
    ];

    public readonly string $name;

    /** @param array<string, string> $facts */
    public function __construct(
        public readonly string $key,
        public readonly string $state,
        public readonly string $summary,
        public readonly array $facts = [],
    ) {
        $this->name = self::NAMES[$key] ?? $key;
    }

    /** A warning or a failure: the Overview shows it. */
    public function alerting(): bool
    {
        return in_array($this->state, [self::WARNING, self::FAILING], true);
    }
}
