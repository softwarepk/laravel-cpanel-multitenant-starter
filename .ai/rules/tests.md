# Test rules

- Prefer Pest feature tests for request/authorization/tenancy behavior.
- Test central and tenant contexts explicitly.
- For isolation, create at least two tenant databases when the behavior could cross a boundary.
- Test direct/guessed IDs and URLs for protected tenant resources, not only normal navigation.
- cPanel API calls must be faked/mocked in automated tests; never contact a real hosting account from the test suite.
- Local `composer ci:check` is authoritative when GitHub Actions capacity is unavailable.
