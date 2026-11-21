<?php

namespace App\Http\Middleware;

use App\Services\Api\Ecommerce\StorefrontCustomerAuth;
use App\Services\Api\Ecommerce\StoreVisitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A storefront customer's own routes (Doc 19 Phase E1): signed in by the
 * token their browser keeps, sent as X-Customer-Token - never a business
 * user's token - for an active account of the store's business. The sign-in
 * is the request's `storeCustomerToken`, its account `storeCustomer`.
 *
 * `store.customer:optional` lets a visitor not signed in through as well. From
 * Phase E3 the store's own routes take it, and its account is the request's
 * StoreVisitor - for that request alone - so what the store shows and its
 * prices follow it.
 */
class EnsureStoreCustomer
{
    public function __construct(
        protected StorefrontCustomerAuth $auth,
        protected StoreVisitor $visitor,
    ) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $token  = $request->header('X-Customer-Token');
        $signIn = is_string($token) && preg_match('/^[A-Za-z0-9]{40,100}$/', $token)
            ? $this->auth->fromToken((string) $request->route('code'), $token)
            : null;

        if (! $signIn && $mode !== 'optional') {
            return response()->json(['message' => 'Sign in to your account.'], 401);
        }

        $request->attributes->set('storeCustomerToken', $signIn);
        $request->attributes->set('storeCustomer', $signIn?->customer);
        $this->visitor->signIn($signIn?->customer);

        try {
            return $next($request);
        } finally {
            $this->visitor->signIn(null);
        }
    }
}
