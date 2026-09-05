---
name: laravel-best-practices
description: "Use for Laravel architecture, models, migrations, validation, authorization, services, and deployment-sensitive backend work."
license: MIT
---

# Laravel Best Practices

Prefer Laravel-native conventions, Eloquent directly, focused Actions/Services for substantial workflows, policies for protected resources, migrations for schema, and transactions for critical multi-record writes.

In this starter always identify whether data is central control-plane data or tenant-owned application data before choosing a model/connection/migration location. Ordinary project business data belongs in tenant databases.

Avoid speculative repository layers, internal APIs solely for the Livewire frontend, and infrastructure dependencies without a concrete requirement.
