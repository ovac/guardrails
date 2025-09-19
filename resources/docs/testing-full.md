title: Full Testing Guide
description: Unit, feature, mock, and end-to-end testing with Testbench.

# Full Testing Guide

Guardrails is tested with Pest + Orchestra Testbench using an in-memory sqlite database. This guide explains how to run the suite and how to test your app-specific flows.

## Running the Suite

```bash
composer update
./vendor/bin/pest --coverage
```

## What’s Covered

- Unit tests: builder, signing policy, auth helper.
- Feature tests: route registration, capture via trait, approve via API, controller interceptor.
- Events: faked and asserted for capture and completion.

## Structure

- Tests use a stub `users` provider model and a `posts` table.
- Guard is set to `web` for simplicity in tests; the package uses your configured guard.

## Writing Your App Tests

1) Use in-memory sqlite or test DB; load Guardrails migrations and your own tables.
2) Create a model with the `Guardrail` trait and declare guarded attributes.
3) `actingAs($user, config('guardrails.auth.guard'))` to test capture/approve flows.
4) Event testing: `Event::fake([...])` and `Event::assertDispatched(...)`.

## CI

GitHub Actions workflow `run-tests.yml` runs the suite across PHP 8.1–8.3 and uploads coverage to Codecov.

## Related Guides

- [Model Guarding Guide](./usage-models.md) — Set up fixtures that mirror your production models.
- [Controller Interception Guide](./usage-controllers.md) — Cover interceptor flows in feature tests.
- [Advanced Flows](./advanced.md) — Know what behaviours to assert for dynamic policies.
- [Common Patterns](./patterns.md) — Use sample flows as starting points for new specs.
