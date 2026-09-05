# Tenancy-focused AI rules

When touching tenancy-sensitive files, preserve the database-per-tenant architecture and the central/tenant split.

## Database

- Normal project business migrations belong in `database/migrations/tenant`.
- Central migrations are only for tenants/domains/control-plane admins/settings/audit/provisioning metadata.
- Do not add `tenant_id` to every tenant business table as a substitute for database isolation.

## Routes and middleware

- Tenant context must be initialized from host/domain before tenant application state is used.
- Central routes must stay restricted to central hostnames.
- Suspended/failed tenants must not fall through into normal tenant application routes.

## Files, cache, queue

- Use Laravel storage/cache/queue APIs so tenancy bootstrappers can apply context.
- Do not hard-code tenant path prefixes in every feature.
- When adding a new queue backend or worker model, test tenant restoration explicitly.

## Provisioning

- Keep cPanel operations behind contracts/services.
- Keep provisioning retryable/idempotent where practical.
- Never automatically destroy a tenant database merely because an Eloquent tenant row is deleted.
- Record meaningful lifecycle/audit state for administrator-facing operations.

## Tests

Every change that can affect tenant boundaries needs at least one hostile/cross-tenant test in addition to happy-path coverage.
