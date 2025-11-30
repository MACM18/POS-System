<?php

namespace Tests\Unit;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_be_created(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Test Company',
            'email' => 'test@example.com',
        ]);
    }

    public function test_tenant_status_methods(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => Tenant::STATUS_PENDING,
        ]);

        $this->assertFalse($tenant->isActive());

        $tenant->activate();
        $tenant->refresh();

        $this->assertTrue($tenant->isActive());
        $this->assertNotNull($tenant->activated_at);

        $tenant->suspend();
        $tenant->refresh();

        $this->assertFalse($tenant->isActive());
        $this->assertEquals(Tenant::STATUS_SUSPENDED, $tenant->status);
    }

    public function test_tenant_trial_methods(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($tenant->isOnTrial());
        $this->assertFalse($tenant->hasTrialExpired());

        $tenant->update(['trial_ends_at' => now()->subDay()]);
        $tenant->refresh();

        $this->assertFalse($tenant->isOnTrial());
        $this->assertTrue($tenant->hasTrialExpired());
    }

    public function test_tenant_settings(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => ['currency' => 'USD'],
        ]);

        $this->assertEquals('USD', $tenant->getSetting('currency'));
        $this->assertNull($tenant->getSetting('non_existent'));
        $this->assertEquals('default', $tenant->getSetting('non_existent', 'default'));

        $tenant->setSetting('timezone', 'Asia/Colombo');
        $tenant->refresh();

        $this->assertEquals('Asia/Colombo', $tenant->getSetting('timezone'));
    }

    public function test_tenant_scopes(): void
    {
        Tenant::factory()->count(3)->create(['status' => Tenant::STATUS_ACTIVE]);
        Tenant::factory()->count(2)->create(['status' => Tenant::STATUS_PENDING]);
        Tenant::factory()->create(['slug' => 'test-company']);

        $this->assertEquals(3, Tenant::active()->count());
        $this->assertEquals(2, Tenant::pending()->count());
        $this->assertEquals(1, Tenant::bySlug('test-company')->count());
    }

    public function test_tenant_database_name_is_hidden(): void
    {
        $tenant = Tenant::factory()->create();

        $array = $tenant->toArray();

        $this->assertArrayNotHasKey('database', $array);
    }
}
