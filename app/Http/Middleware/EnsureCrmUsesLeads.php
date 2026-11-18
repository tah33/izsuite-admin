<?php

namespace App\Http\Middleware;

use App\Models\User\Workspace;
use App\Services\Api\Crm\CrmSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the leads' routes (Doc 17 Phase B1) by Settings > CRM's mode
 * (section 17.5): working with opportunities only, a business keeps its
 * leads and can still read them - a notification's link still opens - but
 * adds and changes none.
 *
 * Runs after `workspace`, which picks the business.
 */
class EnsureCrmUsesLeads
{
    public const REFUSED = 'This business works with opportunities only, so leads are not added or changed. Settings → CRM can bring them back.';

    public function __construct(
        protected CrmSettingService $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('workspace');

        abort_if($workspace instanceof Workspace && ! $request->isMethodSafe() && ! $this->settings->usesLeads($workspace), 403, self::REFUSED);

        return $next($request);
    }
}
