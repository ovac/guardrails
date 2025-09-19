---
title: Getting Started
description: Install, configure and run Guardrails in minutes.
tags: [laravel, approvals, guardrails]
---

# Getting Started

Guardrails plugs a human approval layer into your Laravel app so risky actions pause until the right people sign off.

## Install

1. Register provider in `config/app.php`:

```php
OVAC\\Guardrails\\GuardrailsServiceProvider::class,
```

2. Publish and migrate:

```bash
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-config
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-migrations
php artisan migrate
```

3. Optional: publish views and docs

```bash
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-views
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-docs
```

## Configure

Edit `config/guardrails.php` to set API prefixes, middleware, and policy names.
Guardrails defaults to your app’s `auth.defaults.guard` (typically `web`). Set `GUARDRAILS_AUTH_GUARD` if approvals should use another guard such as `sanctum`.

## UI

Visit `/{page_prefix}` (default `guardrails`) for a minimal review UI.
