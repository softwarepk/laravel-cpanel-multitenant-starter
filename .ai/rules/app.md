# App rules

- Use Eloquent directly by default; do not add repository layers speculatively.
- Put substantial provisioning/lifecycle/state-transition logic in Actions or Services, not controllers/views.
- Central models manage tenancy/control-plane metadata only.
- Tenant application models operate inside tenant context and tenant databases.
- Use policies/authorization for protected tenant resources.
