---
name: tenancy-development
description: "Use for stancl/tenancy, tenant resolution, database-per-tenant migrations, domains, provisioning, lifecycle, storage, cache, queues, and isolation tests."
license: MIT
---

# Multi-Tenancy Development

Read `docs/TENANCY-ARCHITECTURE.md` before changing tenancy infrastructure.

Core invariants:

- central DB contains control-plane metadata only;
- every tenant has its own database and users;
- ordinary project schema belongs in `database/migrations/tenant`;
- tenant context is host/domain driven and initialized before tenant application state;
- files/cache/queued work must preserve tenant isolation;
- suspended tenants are blocked;
- cPanel operations stay behind infrastructure contracts/services;
- destructive tenant/database/domain teardown remains explicit and guarded.

Do not replace database isolation with pervasive `tenant_id` scopes. Add cross-tenant/security tests whenever a change can affect a boundary.
