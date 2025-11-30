<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_tenants(): void
    {
        Tenant::factory()->count(5)->create();

        $response = $this->getJson('/api/tenants');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_can_create_tenant(): void
    {
        $response = $this->postJson('/api/tenants', [
            'name' => 'New Company',
            'email' => 'new@company.com',
            'plan' => 'basic',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Tenant created successfully')
            ->assertJsonPath('tenant.name', 'New Company');

        $this->assertDatabaseHas('tenants', [
            'name' => 'New Company',
            'email' => 'new@company.com',
        ]);
    }

    public function test_create_tenant_validates_required_fields(): void
    {
        $response = $this->postJson('/api/tenants', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_create_tenant_validates_unique_email(): void
    {
        Tenant::factory()->create(['email' => 'existing@company.com']);

        $response = $this->postJson('/api/tenants', [
            'name' => 'New Company',
            'email' => 'existing@company.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_show_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->getJson("/api/tenants/{$tenant->id}");

        $response->assertOk()
            ->assertJsonPath('id', $tenant->id)
            ->assertJsonPath('name', $tenant->name);
    }

    public function test_show_returns_404_for_non_existent_tenant(): void
    {
        $response = $this->getJson('/api/tenants/non-existent-id');

        $response->assertNotFound();
    }

    public function test_can_update_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->putJson("/api/tenants/{$tenant->id}", [
            'name' => 'Updated Company',
            'plan' => 'professional',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Tenant updated successfully')
            ->assertJsonPath('tenant.name', 'Updated Company')
            ->assertJsonPath('tenant.plan', 'professional');
    }

    public function test_can_delete_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->deleteJson("/api/tenants/{$tenant->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Tenant deleted successfully');

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_can_activate_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => Tenant::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/tenants/{$tenant->id}/activate");

        $response->assertOk()
            ->assertJsonPath('message', 'Tenant activated successfully')
            ->assertJsonPath('tenant.status', Tenant::STATUS_ACTIVE);
    }

    public function test_can_suspend_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $response = $this->postJson("/api/tenants/{$tenant->id}/suspend");

        $response->assertOk()
            ->assertJsonPath('message', 'Tenant suspended successfully')
            ->assertJsonPath('tenant.status', Tenant::STATUS_SUSPENDED);
    }

    public function test_can_filter_tenants_by_status(): void
    {
        Tenant::factory()->count(3)->create(['status' => Tenant::STATUS_ACTIVE]);
        Tenant::factory()->count(2)->create(['status' => Tenant::STATUS_PENDING]);

        $response = $this->getJson('/api/tenants?status=active');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_tenants_by_plan(): void
    {
        Tenant::factory()->count(2)->create(['plan' => Tenant::PLAN_BASIC]);
        Tenant::factory()->count(3)->create(['plan' => Tenant::PLAN_PROFESSIONAL]);

        $response = $this->getJson('/api/tenants?plan=professional');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
