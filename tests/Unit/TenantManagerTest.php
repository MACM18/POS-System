<?php

namespace Tests\Unit;

use App\Models\Central\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagerTest extends TestCase
{
    use RefreshDatabase;

    protected TenantManager $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantManager = app(TenantManager::class);
    }

    public function test_tenant_manager_is_singleton(): void
    {
        $manager1 = app(TenantManager::class);
        $manager2 = app(TenantManager::class);

        $this->assertSame($manager1, $manager2);
    }

    public function test_has_no_tenant_initially(): void
    {
        $this->assertFalse($this->tenantManager->hasTenant());
        $this->assertNull($this->tenantManager->current());
    }

    public function test_can_initialize_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->tenantManager->initialize($tenant);

        $this->assertTrue($this->tenantManager->hasTenant());
        $this->assertEquals($tenant->id, $this->tenantManager->current()->id);
    }

    public function test_can_terminate_tenant_context(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->tenantManager->initialize($tenant);

        $this->assertTrue($this->tenantManager->hasTenant());

        $this->tenantManager->terminate();

        $this->assertFalse($this->tenantManager->hasTenant());
    }

    public function test_generates_unique_slug(): void
    {
        // Skip this test if PostgreSQL is not available (requires actual DB creation)
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL for database provisioning');
        }

        // Create first tenant
        $tenant1 = $this->tenantManager->createTenant([
            'name' => 'Test Company',
            'email' => 'test1@example.com',
        ]);

        // Clean up the database we just created
        $this->tenantManager->deprovision($tenant1);

        // Create second tenant with same name
        $tenant2 = $this->tenantManager->createTenant([
            'name' => 'Test Company',
            'email' => 'test2@example.com',
        ]);

        // Clean up
        $this->tenantManager->deprovision($tenant2);

        $this->assertNotEquals($tenant1->slug, $tenant2->slug);
    }

    public function test_database_name_is_sanitized(): void
    {
        $tenant = Tenant::factory()->create([
            'database' => 'tenant_test-special!@#$%chars',
        ]);

        // The sanitize method should remove special characters
        $sanitized = $this->callProtectedMethod(
            $this->tenantManager,
            'sanitizeDatabaseName',
            ['tenant_test-special!@#$%chars']
        );

        $this->assertEquals('tenant_testspecialchars', $sanitized);
    }

    public function test_default_settings_are_applied(): void
    {
        $defaults = $this->callProtectedMethod(
            $this->tenantManager,
            'getDefaultSettings',
            []
        );

        $this->assertArrayHasKey('timezone', $defaults);
        $this->assertArrayHasKey('currency', $defaults);
        $this->assertArrayHasKey('date_format', $defaults);
    }

    /**
     * Call protected method using reflection.
     */
    protected function callProtectedMethod($object, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
