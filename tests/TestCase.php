<?php

namespace Tests;

use App\Models\Central\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The current tenant for testing.
     */
    protected ?Tenant $tenant = null;

    /**
     * Set up the tenant context for testing.
     */
    protected function setUpTenant(): void
    {
        // Create a test tenant
        $this->tenant = Tenant::factory()->active()->create([
            'database' => 'tenant_test_'.uniqid(),
        ]);

        // Initialize tenant context
        $tenantManager = app(TenantManager::class);
        $tenantManager->provision($this->tenant);
        $tenantManager->initialize($this->tenant);
    }

    /**
     * Clean up tenant after test.
     */
    protected function tearDownTenant(): void
    {
        if ($this->tenant) {
            $tenantManager = app(TenantManager::class);
            $tenantManager->deprovision($this->tenant);
            $this->tenant->forceDelete();
        }
    }

    /**
     * Make a request with tenant header.
     */
    protected function withTenant(?Tenant $tenant = null)
    {
        $tenant = $tenant ?? $this->tenant;

        return $this->withHeader('X-Tenant-ID', $tenant?->id);
    }
}
