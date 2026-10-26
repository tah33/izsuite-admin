<?php

namespace App\Repositories\User;

use Illuminate\Support\Facades\DB;

/**
 * The rows a password reset goes through: the emailed code
 * (password_reset_otps), then the single-use token it buys
 * (password_reset_tokens). One of each per email address.
 */
class PasswordResetRepository
{
    public function findCode(string $email): ?object
    {
        return DB::table('password_reset_otps')->where('email', $email)->first();
    }

    public function insertCode(array $row): void
    {
        DB::table('password_reset_otps')->insert($row);
    }

    public function deleteCode(string $email): void
    {
        DB::table('password_reset_otps')->where('email', $email)->delete();
    }

    public function findToken(string $email): ?object
    {
        return DB::table('password_reset_tokens')->where('email', $email)->first();
    }

    /** The address's token row, made or replaced. */
    public function saveToken(string $email, array $values): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], $values);
    }

    public function deleteToken(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
