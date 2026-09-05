# Resource rules

- Keep shared frontend assets global; do not tenant-suffix Vite build assets.
- Tenant-owned uploads/documents must use tenancy-aware Laravel storage disks.
- Do not manually embed tenant IDs into every path when the filesystem bootstrapper already provides isolation.
