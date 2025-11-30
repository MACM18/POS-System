<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found.',
                'error' => 'invalid_tenant',
            ], 404);
        }

        if (!$tenant->isActive()) {
            return response()->json([
                'message' => 'Tenant account is not active.',
                'error' => 'tenant_inactive',
                'status' => $tenant->status,
            ], 403);
        }

        // Initialize tenant context
        $this->tenantManager->initialize($tenant);

        // Store tenant in request for later use
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve tenant from the request.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // Try to resolve from subdomain first
        $tenant = $this->resolveFromSubdomain($request);

        if ($tenant) {
            return $tenant;
        }

        // Try to resolve from header (useful for testing and API clients)
        $tenant = $this->resolveFromHeader($request);

        if ($tenant) {
            return $tenant;
        }

        // Try to resolve from query parameter (for development)
        if (app()->environment('local', 'testing')) {
            return $this->resolveFromQuery($request);
        }

        return null;
    }

    /**
     * Resolve tenant from subdomain.
     */
    protected function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Check if we have a subdomain (e.g., tenant1.posapp.com)
        if (count($parts) >= 3) {
            $subdomain = $parts[0];

            // Skip common non-tenant subdomains
            if (in_array($subdomain, ['www', 'api', 'admin', 'app'])) {
                return null;
            }

            return Tenant::where('slug', $subdomain)
                ->orWhere('domain', $host)
                ->first();
        }

        // Check for full custom domain
        return Tenant::where('domain', $host)->first();
    }

    /**
     * Resolve tenant from X-Tenant-ID header.
     */
    protected function resolveFromHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Resolve tenant from query parameter (development only).
     */
    protected function resolveFromQuery(Request $request): ?Tenant
    {
        $tenantSlug = $request->query('tenant');

        if (!$tenantSlug) {
            return null;
        }

        return Tenant::where('slug', $tenantSlug)->first();
    }

    /**
     * Handle tasks after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Clean up tenant context
        $this->tenantManager->terminate();
    }
}
