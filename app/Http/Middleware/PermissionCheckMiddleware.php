<?php

namespace App\Http\Middleware;

use App\Models\Admin\Role;
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

        if ($request->routeIs('admin.overview')) {
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
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
        }

        abort(403, 'Permission denied.');
    }
}
