# Livewire rules

- Keep Livewire components thin; move substantial business/provisioning logic into Actions/Services.
- Reuse Flux and `x-ui.*` primitives.
- Tenant Livewire requests must retain correct tenant host/context.
- Do not create a separate internal API solely for this application's own Livewire frontend.
