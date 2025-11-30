# POS System - AI Coding Instructions

## Architecture Overview

This is a **multi-tenant SaaS POS system** using the **database-per-tenant** isolation pattern:

-   **Central DB** (`pgsql` connection): Stores tenant registry in `tenants` table
-   **Tenant DBs** (`tenant` connection): Each tenant gets an isolated PostgreSQL database with POS tables
-   **Tenant Resolution**: Subdomain → `X-Tenant-ID` header → `?tenant=` query param (dev only)

## Critical Patterns

### Tenant Context

All tenant-scoped code runs through `TenantManager` which configures the `tenant` DB connection dynamically:

```php
// Accessing current tenant
app(TenantManager::class)->current();

// Running code in tenant context
$tenantManager->run($tenant, fn() => Product::all());
```

### Model Connections

-   **Central models** (`app/Models/Central/`): Use default `pgsql` connection
-   **Tenant models** (`app/Models/Tenant/`): Extend `TenantModel` base class which sets `$connection = 'tenant'`
-   All models use **UUID primary keys** via `HasUuids` trait

### Database Transactions

Always specify the connection for tenant transactions:

```php
DB::connection('tenant')->beginTransaction();
// ... operations
DB::connection('tenant')->commit();
```

## File Conventions

| Path                          | Purpose                                                     |
| ----------------------------- | ----------------------------------------------------------- |
| `database/migrations/`        | Central DB migrations                                       |
| `database/migrations/tenant/` | Tenant DB migrations (run via `TenantManager::provision()`) |
| `database/factories/Central/` | Factories for central models                                |
| `database/factories/Tenant/`  | Factories for tenant models                                 |

## Testing

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test
docker-compose exec app php artisan test tests/Feature/TenantApiTest.php
```

Tests use `RefreshDatabase` trait. For tenant-scoped tests, provision a test tenant first:

```php
$tenant = Tenant::factory()->active()->create();
app(TenantManager::class)->initialize($tenant);
```

## API Route Groups

Routes in `routes/api.php` follow two patterns:

1. **Central routes** (no middleware): `/api/tenants/*` for admin management
2. **Tenant routes** (`middleware('tenant')`): All POS operations require tenant context

## Common Commands

```bash
# Start development environment
docker-compose up -d

# Run central migrations
docker-compose exec app php artisan migrate

# Clear all caches
docker-compose exec app php artisan optimize:clear

# Check code style
./vendor/bin/pint --test
```

## Sales Workflow

The `SaleController::store()` method demonstrates the core POS flow:

1. Validate stock availability for all items
2. Create `Sale` record (status: pending)
3. Create `SaleItem` records and decrement `Product` stock
4. Create `InventoryMovement` records for audit trail
5. Mark sale as completed if fully paid

Cancellation restores stock via `Sale::cancel()` which iterates items and calls `Product::incrementStock()`.

# Additional Instructions

Maintain a log file in a check list style for all major changes made to the codebase. This will help in tracking modifications and understanding the evolution of the project over time. Add all the features implemented, planned implents, and sub sequent changes made to the codebase in a structured manner. This will serve as a changelog for features of the POS system. Use the file name: CHANGELOG.md
