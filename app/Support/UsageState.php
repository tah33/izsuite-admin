<?php

namespace App\Support;

use App\Services\User\Workspaces\PlanLimits;

/**
 * Where a business stands against a limit, as Admin > Usage says it (Doc 15
 * section 15.18 - Phase D2): ok; warning from the warning share of it; full
 * at it; over past it. A limit of one at one - Billing Starter's one user -
 * is ok, as Admin > Businesses has it. UsageRepository's filters ask the
 * same in SQL.
 */
final class UsageState
{
    public const OK      = 'ok';

    public const WARNING = 'warning';

    public const FULL    = 'full';

    public const OVER    = 'over';

    /** Worst last. */
    public const ORDER   = [self::OK, self::WARNING, self::FULL, self::OVER];

    public const LABELS  = [
        self::OK      => 'OK',
        self::WARNING => 'Nearing its limit',
        self::FULL    => 'At its limit',
        self::OVER    => 'Past its limit',
    ];

    /** $used users or locations of $limit (null: no limit). */
    public static function count(int $used, ?int $limit): string
    {
        return match (true) {
            $limit === null                                                    => self::OK,
            $used > $limit                                                     => self::OVER,
            $limit > 1 && $used === $limit                                     => self::FULL,
            $limit > 1 && $used * 100 >= $limit * PlanLimits::WARNING_PERCENT  => self::WARNING,
            default                                                            => self::OK,
        };
    }

    /** $bytes of storage of $limitBytes (null: no limit). */
    public static function storage(int $bytes, ?int $limitBytes): string
    {
        return match (true) {
            $limitBytes === null                                               => self::OK,
            $bytes > $limitBytes                                               => self::OVER,
            $bytes === $limitBytes                                             => self::FULL,
            $bytes * 100 >= $limitBytes * self::storageWarning()               => self::WARNING,
            default                                                            => self::OK,
        };
    }

    /** The worst of $states. */
    public static function worst(string ...$states): string
    {
        return self::ORDER[max(array_map(fn (string $state) => array_search($state, self::ORDER, true), $states ?: [self::OK]))];
    }

    /** $used as a share of $limit, to one decimal - null without a limit. */
    public static function percent(int $used, ?int $limit): ?float
    {
        return $limit ? round($used / $limit * 100, 1) : null;
    }

    /** The share of a plan's storage that warns: Settings' files.warning_percent. */
    public static function storageWarning(): int
    {
        return (int) config('files.warning_percent', 80);
    }
}
