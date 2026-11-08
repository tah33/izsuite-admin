<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doc 12 Phase E2: the Activity Log keeps no secret, not even hashed
 * (SettingChanges). Before, a changed password - or a sign-in code - went
 * into an entry's changes with its previous and new hash; those changes are
 * taken out of every entry. Nothing else in an entry is touched, and nothing
 * taken out can come back.
 */
return new class extends Migration
{
    private const SECRETS = [
        'password', 'remember_token', 'verification_otp', 'verification_otp_expires_at',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_step',
    ];

    public function up(): void
    {
        DB::table('activity_logs')
            ->where(function ($query) {
                foreach (self::SECRETS as $secret) {
                    $query->orWhere('properties', 'like', '%"'.$secret.'"%');
                }
            })
            ->orderBy('id')
            ->each(function (object $log) {
                $properties = json_decode($log->properties ?? 'null', true);

                if (! is_array($properties['changes'] ?? null)) {
                    return;
                }

                $kept       = array_values(array_filter($properties['changes'], fn ($change) => ! in_array($change['setting'] ?? null, self::SECRETS, true)));

                if (count($kept) !== count($properties['changes'])) {
                    $properties['changes'] = $kept;
                    DB::table('activity_logs')->where('id', $log->id)->update(['properties' => json_encode($properties)]);
                }
            });
    }

    public function down(): void
    {
        // What was taken out is gone for good.
    }
};
