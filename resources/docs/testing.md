title: Testing & Local Development
description: How to test Guardrails locally and in CI.

# Testing & Local Development

This package uses Pest with Orchestra Testbench.

## Run Tests

```bash
composer update
./vendor/bin/pest
```

## With Coverage

```bash
./vendor/bin/pest --coverage
```

## Static Analysis & Style

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
```

## App Integration Tips

- Use an in-memory sqlite database for speed.
- Seed approver accounts with the appropriate permissions/roles for realistic flows.
- Hit the HTTP routes with Testbench’s router to exercise the API end-to-end.
