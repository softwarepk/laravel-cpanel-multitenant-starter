# Blade/Livewire view rules

- Keep tenant-facing settings/profile/security behavior tenant-local.
- Keep central administrator screens under the central layout/auth surface.
- Avoid coupling tenant views to central models or vice versa.
- Use named routes so host/context behavior remains explicit in tests.
