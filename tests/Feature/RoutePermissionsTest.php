<?php

namespace Tests\Feature;

use App\Support\Permissions;
use App\Support\RoutePermissions;
use Closure;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every business route says what it needs (Doc 12 Phase A): one missing from
 * RoutePermissions is open to full access only - this lists it - and every
 * permission named is one a role can hold.
 */
class RoutePermissionsTest extends TestCase
{
    /** What {kind} and {to} stand for in the routes that use them. */
    private const FILLS = [
        '{kind}' => ['quotations', 'proformas', 'invoices', 'credit_notes', 'contacts', 'items'],
        '{to}'   => ['proformas', 'invoices'],
    ];

    public function test_every_business_route_names_its_permission(): void
    {
        $guarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('access', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->getName())
            ->values();

        $this->assertGreaterThan(200, $guarded->count());
        $this->assertSame([], $guarded->reject(fn (?string $name) => $name !== null && array_key_exists($name, RoutePermissions::map()))->values()->all());
    }

    public function test_every_permission_named_is_one_a_role_can_hold(): void
    {
        $unknown = [];

        foreach (RoutePermissions::map() as $name => $needs) {
            if ($needs instanceof Closure || $needs === RoutePermissions::MEMBER) {
                continue;
            }

            foreach ((array) $needs as $permission) {
                $placeholder = collect(array_keys(self::FILLS))->first(fn (string $key) => str_contains($permission, $key));
                $candidates  = $placeholder ? array_map(fn (string $fill) => str_replace($placeholder, $fill, $permission), self::FILLS[$placeholder]) : [$permission];

                if (! collect($candidates)->contains(fn (string $candidate) => Permissions::exists($candidate))) {
                    $unknown[] = "{$name}: {$permission}";
                }
            }
        }

        $this->assertSame([], $unknown);
    }
}
