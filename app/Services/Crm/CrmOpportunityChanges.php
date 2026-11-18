<?php

namespace App\Services\Crm;

/**
 * What a step changed on an opportunity, as its timeline shows it (Doc 17
 * Phase D2b): its stage by name, its status, probability and the like - in
 * place of the columns its save touched, which would show ids and times.
 */
final class CrmOpportunityChanges
{
    /**
     * $pairs as the Activity Log keeps changes: [setting => [from, to]], each
     * that differs - in the order given.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $pairs
     * @return list<array{setting: string, from: string|null, to: string|null}>
     */
    public static function of(array $pairs): array
    {
        $changes = [];

        foreach ($pairs as $setting => [$from, $to]) {
            $from = $from === null || $from === '' ? null : (string) $from;
            $to   = $to === null || $to === '' ? null : (string) $to;

            if ($from !== $to) {
                $changes[] = ['setting' => $setting, 'from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }
}
