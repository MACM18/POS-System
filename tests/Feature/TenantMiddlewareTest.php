<?php

namespace Tests\Feature;

use App\Http\Middleware\TenantMiddleware;
use App\Models\Central\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected TenantMiddleware $middleware;

    protected TenantManager $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantManager = app(TenantManager::class);
        $this->middleware = new TenantMiddleware($this->tenantManager);
    }

    public function test_returns_404_when_tenant_not_found(): void
    {
        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Tenant not found', $response->getContent());
    }

    public function test_returns_403_when_tenant_inactive(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => Tenant::STATUS_SUSPENDED,
        ]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', $tenant->id);

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('not active', $response->getContent());
    }

    public function test_passes_when_tenant_is_active(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', $tenant->id);

        $response = $this->middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_sets_tenant_in_request_attributes(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', $tenant->id);

        $this->middleware->handle($request, function ($req) use ($tenant) {
            $this->assertEquals($tenant->id, $req->attributes->get('tenant')->id);

            return response('OK');
        });
    }

    public function test_initializes_tenant_manager(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', $tenant->id);

        $this->middleware->handle($request, fn () => response('OK'));

        $this->assertTrue($this->tenantManager->hasTenant());
        $this->assertEquals($tenant->id, $this->tenantManager->current()->id);
    }

    public function test_resolves_tenant_from_query_param_in_local(): void
    {
        $this->app['env'] = 'local';

        $tenant = Tenant::factory()->active()->create(['slug' => 'test-tenant']);

        $request = Request::create('/api/test?tenant=test-tenant', 'GET');

        $this->middleware->handle($request, function ($req) use ($tenant) {
            $this->assertEquals($tenant->id, $req->attributes->get('tenant')->id);

            return response('OK');
        });
    }
}
