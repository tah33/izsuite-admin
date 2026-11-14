<?php

namespace App\Http\Middleware;

use App\Enums\DocumentType;
use App\Services\User\Apps\WorkspaceApps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sales document kind needs the edition of Sales that makes it (Doc 16
 * Phase B): sales orders, the full Sales app's. On Sales Lite an order stays
 * readable - GET and HEAD, so one opened from its invoice or the Documents
 * page shows, with its PDF - and nothing changes it (section 16.2: "Advanced
 * Sales records remain preserved and available as read-only").
 *
 *   Route::prefix('{kind}')->middleware('sales.kind')
 *
 * Runs after `app:sales`, which lets in Sales Lite or Sales.
 */
class EnsureSalesKindEdition
{
    public function handle(Request $request, Closure $next): Response
    {
        $type      = DocumentType::fromKind((string) $request->route('kind'));
        $workspace = $request->attributes->get('workspace');

        if ($type && $workspace && ! $request->isMethodSafe() && ! WorkspaceApps::for($workspace)->has($type->family(), $type->edition())) {
            abort(403, "{$type->label()}s belong to the Sales app. Turn it on in the Apps Center to change them.");
        }

        return $next($request);
    }
}
