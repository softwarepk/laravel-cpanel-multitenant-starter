# Route rules

- Central Control Center routes belong on configured central hostnames only.
- Tenant application routes require successful tenant initialization from the request host.
- Keep central administrator authentication separate from tenant Fortify authentication.
- Prefer named routes.
- Test that central hostnames cannot expose tenant application routes and tenant hostnames cannot expose central administration routes.
