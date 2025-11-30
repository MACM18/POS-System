<?php

namespace App\Services;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantManager
{
    /**
     * The current tenant.
     */
    protected ?Tenant $tenant = null;

    /**
     * The original database connection settings.
     */
    protected array $originalConnection = [];

    /**
     * Get the current tenant.
     */
    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Check if a tenant is currently active.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Initialize the tenant context.
     */
    public function initialize(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->configureConnection($tenant);

        Log::info('Tenant initialized', ['tenant_id' => $tenant->id, 'database' => $tenant->database]);
    }

    /**
     * Configure the database connection for the tenant.
     */
    protected function configureConnection(Tenant $tenant): void
    {
        // Store original connection settings
        $this->originalConnection = Config::get('database.connections.tenant', []);

        // Get base PostgreSQL connection settings
        $baseConfig = Config::get('database.connections.pgsql');

        // Configure tenant connection
        Config::set('database.connections.tenant', array_merge($baseConfig, [
            'database' => $tenant->database,
        ]));

        // Purge any existing tenant connection
        DB::purge('tenant');

        // Reconnect with new settings
        DB::reconnect('tenant');
    }

    /**
     * Terminate the tenant context.
     */
    public function terminate(): void
    {
        if ($this->tenant) {
            DB::purge('tenant');
            $this->tenant = null;
        }
    }

    /**
     * Provision a new tenant database.
     */
    public function provision(Tenant $tenant): bool
    {
        try {
            // Create the database
            $this->createDatabase($tenant->database);

            // Run tenant migrations
            $this->runMigrations($tenant);

            // Seed initial data if needed
            $this->seedDatabase($tenant);

            Log::info('Tenant provisioned successfully', ['tenant_id' => $tenant->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to provision tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            // Rollback - drop database if created
            $this->dropDatabase($tenant->database);

            throw $e;
        }
    }

    /**
     * Create a new database for the tenant.
     */
    protected function createDatabase(string $database): void
    {
        $database = $this->sanitizeDatabaseName($database);

        // Use the central connection to create the database
        DB::connection('pgsql')->statement("CREATE DATABASE \"{$database}\"");

        Log::info('Database created', ['database' => $database]);
    }

    /**
     * Drop a tenant database.
     */
    public function dropDatabase(string $database): void
    {
        $database = $this->sanitizeDatabaseName($database);

        try {
            // Terminate all connections to the database first
            DB::connection('pgsql')->statement("
                SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = '{$database}'
                AND pid <> pg_backend_pid()
            ");

            DB::connection('pgsql')->statement("DROP DATABASE IF EXISTS \"{$database}\"");

            Log::info('Database dropped', ['database' => $database]);
        } catch (\Exception $e) {
            Log::error('Failed to drop database', [
                'database' => $database,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run migrations for the tenant database.
     */
    protected function runMigrations(Tenant $tenant): void
    {
        // Temporarily switch to tenant connection
        $this->configureConnection($tenant);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        Log::info('Tenant migrations completed', ['tenant_id' => $tenant->id]);
    }

    /**
     * Seed the tenant database with initial data.
     */
    protected function seedDatabase(Tenant $tenant): void
    {
        // Configure connection for seeding
        $this->configureConnection($tenant);

        // Run tenant-specific seeders if they exist
        if (class_exists('Database\\Seeders\\Tenant\\TenantDatabaseSeeder')) {
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
                '--force' => true,
            ]);
        }
    }

    /**
     * Deprovision a tenant (delete database).
     */
    public function deprovision(Tenant $tenant): bool
    {
        try {
            $this->dropDatabase($tenant->database);

            Log::info('Tenant deprovisioned', ['tenant_id' => $tenant->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to deprovision tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create a new tenant with provisioning.
     */
    public function createTenant(array $data): Tenant
    {
        $slug = Str::slug($data['name']);
        $uniqueSlug = $this->generateUniqueSlug($slug);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $uniqueSlug,
            'domain' => $data['domain'] ?? "{$uniqueSlug}.posapp.com",
            'database' => $this->getDatabasePrefix().$uniqueSlug,
            'email' => $data['email'],
            'status' => Tenant::STATUS_PENDING,
            'plan' => $data['plan'] ?? Tenant::PLAN_FREE,
            'settings' => $data['settings'] ?? $this->getDefaultSettings(),
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Provision the database
        $this->provision($tenant);

        // Activate the tenant
        $tenant->activate();

        return $tenant->fresh();
    }

    /**
     * Generate a unique slug.
     */
    protected function generateUniqueSlug(string $slug): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the database name prefix.
     */
    protected function getDatabasePrefix(): string
    {
        return config('database.tenant_prefix', 'tenant_');
    }

    /**
     * Get default tenant settings.
     */
    protected function getDefaultSettings(): array
    {
        return [
            'timezone' => 'UTC',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'tax_rate' => 0,
            'invoice_prefix' => 'INV-',
        ];
    }

    /**
     * Sanitize database name to prevent SQL injection.
     */
    protected function sanitizeDatabaseName(string $name): string
    {
        // Only allow alphanumeric characters and underscores
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }

    /**
     * Check if a database exists.
     */
    public function databaseExists(string $database): bool
    {
        $database = $this->sanitizeDatabaseName($database);

        $result = DB::connection('pgsql')->select('
            SELECT 1 FROM pg_database WHERE datname = ?
        ', [$database]);

        return ! empty($result);
    }

    /**
     * Run a callback within a tenant context.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = $this->tenant;

        try {
            $this->initialize($tenant);

            return $callback($tenant);
        } finally {
            if ($previousTenant) {
                $this->initialize($previousTenant);
            } else {
                $this->terminate();
            }
        }
    }
}
