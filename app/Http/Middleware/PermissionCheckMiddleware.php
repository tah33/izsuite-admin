<?php

namespace App\Http\Middleware;

use App\Models\Admin\Role;
use App\Services\Admin\AdminAuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionCheckMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return $next($request);
        }

        $user        = auth()->user();

        if ($user->role_id == Role::SUPER_ADMIN_ID) {
            return $next($request);
        }

        // The Overview, and each admin's own account: their profile and
        // password, and their two-factor sign-in (Doc 15 section 15.2) - and
        // putting away an announcement for themselves (Phase F1).
        if ($request->routeIs('admin.overview', 'admin.profile', 'admin.profile.update', 'admin.profile.password', 'admin.two-factor.*', 'admin.announcements.dismiss')) {
            return $next($request);
        }

        $permissions = collect($user->role->permissions ?? []);
        $routeName   = $request->route()->getName();

        if ($permissions->contains($routeName)) {
            return $next($request);
        }

        if (str_ends_with($routeName, '.store')) {
            $checkName = str_replace('.store', '.create', $routeName);
            if ($permissions->contains($checkName)) {
                return $next($request);
            }
        }

        if (str_ends_with($routeName, '.update')) {
            $editName = str_replace('.update', '.edit', $routeName);
            if ($permissions->contains($editName)) {
                return $next($request);
            }
        }

        return $this->deny($request);
    }

    protected function deny(Request $request): Response
    {
        // Kept in the audit log (Doc 15 section 15.26): what was tried, and that it was refused.
        app(AdminAuditLogService::class)->record('denied', 'Tried a page their role does not allow: '.$request->route()->getName(), null, ['method' => $request->method(), 'path' => '/'.$request->path()], null, 'refused');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
        }

        abort(403, 'Permission denied.');
    }
}
