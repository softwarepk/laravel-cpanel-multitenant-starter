# GitHub Guardrails

## Branching and pull requests

- Keep `main` releasable.
- Make implementation changes on focused branches such as `feature/...`, `fix/...`, or `chore/...`.
- Changes normally enter `main` through a pull request.
- Prefer squash merge unless preserving separate commits has real value.

## Verification

Local verification is authoritative when GitHub Actions capacity is unavailable.

Before merge, run:

```bash
composer ci:check
npm run build
```

Use `composer fix` when appropriate for safe Rector/Pint cleanup, then re-run the quality gate.

Do not claim tests pass until the local output confirms it.

## Multi-tenant review expectations

Changes touching tenancy infrastructure require an isolation-focused review. Reviewers should consider:

- central vs tenant DB boundaries;
- host/domain resolution;
- authentication surface (central admin vs tenant user);
- tenant storage/cache/queue context;
- suspended tenant behavior;
- destructive provisioning/deprovisioning operations;
- whether project business data accidentally moved into central storage;
- whether direct/guessed identifiers could cross tenant boundaries.

## Production deployment

Merging code and deploying production are separate actions. A merge does not imply a production migration has run.

When tenant schema changes, deployment must run both central and tenant migrations as applicable.

## Secrets

Do not commit `.env`, cPanel tokens, SMTP passwords, production database credentials, private keys, or other operational secrets. Test fixtures must use fake values.
