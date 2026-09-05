# Database rules

- Central schema lives in `database/migrations` and is reserved for tenancy/control-plane metadata.
- Tenant application schema lives in `database/migrations/tenant`.
- Each tenant has a separate database. Do not introduce shared-schema `tenant_id` scoping for ordinary business models.
- Keep central and tenant migrations independently deployable.
- Use transactions for multi-record critical writes where the active connection supports them.
