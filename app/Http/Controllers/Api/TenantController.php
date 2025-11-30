<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->plan, fn ($q, $plan) => $q->where('plan', $plan))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($tenants);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'domain' => 'nullable|string|unique:tenants,domain',
            'plan' => 'nullable|in:free,basic,professional,enterprise',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = $this->tenantManager->createTenant($validator->validated());

            return response()->json([
                'message' => 'Tenant created successfully',
                'tenant' => $tenant,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified tenant.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        return response()->json($tenant);
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:tenants,email,'.$id,
            'domain' => 'sometimes|string|unique:tenants,domain,'.$id,
            'plan' => 'sometimes|in:free,basic,professional,enterprise',
            'status' => 'sometimes|in:active,inactive,suspended,pending',
            'settings' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant->update($validator->validated());

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        try {
            // Deprovision the database
            $this->tenantManager->deprovision($tenant);

            // Delete the tenant record
            $tenant->delete();

            return response()->json([
                'message' => 'Tenant deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate a tenant.
     */
    public function activate(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        $tenant->activate();

        return response()->json([
            'message' => 'Tenant activated successfully',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * Suspend a tenant.
     */
    public function suspend(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        $tenant->suspend();

        return response()->json([
            'message' => 'Tenant suspended successfully',
            'tenant' => $tenant->fresh(),
        ]);
    }
}
