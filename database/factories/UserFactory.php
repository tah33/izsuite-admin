<?php

namespace Database\Factories;

use App\Models\Admin\Role;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Stated rather than guessed: Factory derives the model name from its own
     * class, which would give App\User instead of App\Models\User\User.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // role_id is NOT NULL, so the factory has to supply one. The
            // frontend role is the harmless default; states and explicit
            // attributes override it where a test needs an admin.
            'role_id'           => fn () => Role::firstOrCreate(
                ['slug' => 'user'],
                ['name' => 'User', 'permissions' => null],
            )->id,
            // users.name was split into first_name/last_name by the
            // replace_users_name_with_first_name migration; the column is gone.
            'first_name'        => fake()->firstName(),
            'last_name'         => fake()->lastName(),
            'username'          => fake()->unique()->userName(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
