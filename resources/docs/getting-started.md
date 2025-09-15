---
title: Getting Started
description: Install, configure and run Guardrails in minutes.
tags: [laravel, approvals, guardrails]
---

# Getting Started

Guardrails adds human‑in‑the‑loop approvals to your Laravel admin/ops apps.

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

## UI

Visit `/{page_prefix}` (default `staff/guardrails`) for a minimal review UI.

