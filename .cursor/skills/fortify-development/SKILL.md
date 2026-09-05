---
name: fortify-development
description: "Use for Laravel authentication work: login, registration, password reset, verification, password changes, or Fortify configuration/actions."
license: MIT
metadata:
  author: laravel
---

# Laravel Fortify Development

Fortify provides the tenant application's authentication backend. Before changing authentication, inspect `config/fortify.php`, `app/Actions/Fortify/`, and `App\Providers\FortifyServiceProvider`.

Central Control Center authentication is deliberately separate from tenant Fortify authentication. Do not merge central administrators into tenant users for convenience.

Keep tenant authentication server-rendered with Blade/Livewire unless a product explicitly requires a different architecture. Preserve rate limiting, validation, password confirmation, and verification semantics.

Authentication and authorization are separate concerns: Fortify proves tenant-user identity; Policies/authorization rules protect tenant resources and operations.
