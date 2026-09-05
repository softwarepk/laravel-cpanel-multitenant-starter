# Config rules

- Keep tenancy platform/domain/cPanel settings environment driven.
- Never hard-code production hostnames, account names, database credentials, API tokens, or SMTP secrets.
- Preserve separate central and tenant database connection concepts.
- Any change to cache/session/queue/filesystem configuration must be reviewed for tenant isolation effects.
