---
name: pest-testing
description: "Use for Pest tests, feature tests, tenant isolation tests, and local quality-gate work."
license: MIT
---

# Pest Testing

Prefer focused feature tests for application and tenancy behavior. For tenant-boundary changes, test hostile cases such as guessed identifiers, wrong hostnames, suspended tenants, storage/cache/queue context, and central-vs-tenant data placement.

Never call a real cPanel account from automated tests. Substitute infrastructure contracts with fakes/mocks.

Run focused tests first, then `composer ci:check`; local output is authoritative when GitHub Actions capacity is unavailable.
