<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Require at least one organization membership and a valid current tenant in session.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $tenantIds = $user->tenants()->pluck('tenants.id');

        if ($tenantIds->isEmpty()) {
            return redirect()->route('organization.create');
        }

        $current = session('current_tenant_id');

        if ($current === null || ! $tenantIds->contains((int) $current)) {
            session(['current_tenant_id' => (int) $tenantIds->first()]);
        }

        return $next($request);
    }
}
