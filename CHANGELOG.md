# POS System Backend - Changelog

All notable changes to this project will be documented in this file.

> **Note**: This is the **backend API only** (Laravel + PostgreSQL).  
> Frontend will be built separately with Next.js in a dedicated repository.

---

### Planned Features

-   [ ] Receipt generation API (PDF/thermal printer format)
-   [ ] Multi-currency support with exchange rates
-   [ ] Email notifications for low stock alerts
-   [ ] Webhook system for external integrations
-   [ ] Batch product import/export (CSV/Excel)
-   [ ] Scheduled reports generation
-   [ ] API rate limiting per tenant plan
-   [ ] Audit logging for sensitive operations

### Frontend (Separate Repo - Next.js)

-   [ ] Admin dashboard for tenant management
-   [ ] POS terminal interface
-   [ ] Barcode scanner integration
-   [ ] Offline mode with sync
-   [ ] Reports and analytics dashboard
-   [ ] Customer loyalty program UI

---

## [0.1.1] - 2025-11-30

### Added - Redis Integration

-   [x] Redis 7 Alpine added to Docker stack
-   [x] PHP Redis extension installed in Dockerfile
-   [x] Cache driver switched from database to Redis
-   [x] Session driver switched from database to Redis
-   [x] Queue driver switched from database to Redis
-   [x] Broadcast driver configured for Redis
-   [x] Redis health check in docker-compose
-   [x] Persistent Redis data volume

---

## [0.1.0] - 2025-11-30

### Initial Release - Core Infrastructure

#### Multi-Tenant Architecture

-   [x] Database-per-tenant isolation pattern implemented
-   [x] `TenantManager` service for database provisioning/deprovisioning
-   [x] `TenantMiddleware` for request-based tenant resolution
    -   Subdomain resolution (e.g., `client1.posapp.com`)
    -   `X-Tenant-ID` header resolution
    -   Query parameter resolution (dev only)
-   [x] Central `Tenant` model with status management (pending, active, suspended)
-   [x] Tenant plan support (free, basic, professional, enterprise)

#### Authentication

-   [x] Laravel Sanctum integration for API token authentication
-   [x] `AuthController` with register, login, logout endpoints
-   [x] Password update functionality
-   [x] Token refresh mechanism

#### POS Domain Models

-   [x] `Category` model with hierarchical parent-child support
-   [x] `Product` model with:
    -   SKU and barcode tracking
    -   Stock quantity management
    -   Cost price and selling price
    -   Tax rate per product
    -   Low stock threshold alerts
-   [x] `Customer` model with:
    -   Contact information
    -   Loyalty points tracking
    -   Credit limit and balance
-   [x] `Sale` model with:
    -   Invoice number generation
    -   Multiple payment methods (cash, card, mobile, credit, mixed)
    -   Status workflow (pending → completed/cancelled/refunded)
    -   Discount support (fixed and percentage)
-   [x] `SaleItem` model linking products to sales
-   [x] `InventoryMovement` model for stock audit trail

#### API Endpoints

-   [x] `/api/health` - Health check endpoint
-   [x] `/api/tenants/*` - Central tenant management (CRUD + activate/suspend)
-   [x] `/api/auth/*` - Authentication endpoints
-   [x] `/api/products/*` - Product CRUD + stock management
-   [x] `/api/categories/*` - Category CRUD + tree structure
-   [x] `/api/customers/*` - Customer CRUD + purchase history
-   [x] `/api/sales/*` - Sales creation, viewing, cancellation
-   [x] `/api/sales-statistics` - Sales analytics
-   [x] `/api/sales-daily-report` - Daily sales report

#### Database Migrations

-   [x] Central database: `tenants` table
-   [x] Tenant database migrations:
    -   `users` - Tenant-scoped users
    -   `categories` - Product categories
    -   `products` - Inventory items
    -   `customers` - Customer records
    -   `sales` - Sales transactions
    -   `sale_items` - Line items for sales
    -   `inventory_movements` - Stock movement audit
    -   `personal_access_tokens` - Sanctum tokens

#### Testing Infrastructure

-   [x] PHPUnit configuration for PostgreSQL
-   [x] `TenantApiTest` - Tenant management tests
-   [x] `TenantMiddlewareTest` - Middleware resolution tests
-   [x] `TenantManagerTest` - Service layer tests
-   [x] `TenantModelTest` - Model unit tests
-   [x] `HealthCheckTest` - API health tests
-   [x] Model factories for all entities

#### DevOps & Infrastructure

-   [x] Docker configuration (`Dockerfile`, `docker-compose.yml`)
-   [x] PostgreSQL 15 database service
-   [x] GitHub Actions CI/CD workflow
    -   Test job with PostgreSQL service
    -   Code quality check (Laravel Pint)
    -   Security vulnerability scanning
    -   Deployment jobs (commented out until VPS ready)
-   [x] Environment configuration files (`.env.example`, `.env.testing`)

#### Documentation

-   [x] `.github/copilot-instructions.md` - AI coding assistant guidelines
-   [x] API endpoint documentation

---

## Version History

| Version | Date       | Description                                               |
| ------- | ---------- | --------------------------------------------------------- |
| 0.1.0   | 2025-11-30 | Initial release with core multi-tenant POS infrastructure |

---

## Contributing

When adding new features, please update this changelog with:

1. Feature description under the appropriate section
2. Mark as `[ ]` for planned or `[x]` for completed
3. Include the date when marking as complete
