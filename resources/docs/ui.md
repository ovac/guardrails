title: UI & Assets
description: The bundled approval dashboard and how to customize it.

# UI & Assets

Guardrails ships a minimal Blade view for reviewing pending requests at `/{page_prefix}` (default `staff/guardrails`). It calls the JSON API and renders steps with Approve buttons.

## Publish Views & Assets

```bash
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-views
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-assets
```

Views are published to `resources/views/vendor/guardrails` and assets to `public/vendor/guardrails`.

## Layout Integration

Set `views.layout` and `views.section` in `config/guardrails.php` to render the page inside your app layout.

## Customization Tips

- Tailor the request list by modifying `index.blade.php` after publishing.
- Harden buttons with your authorization logic if you deviate from default permissions.
- Hook into the API to extend payloads (e.g., append approvable previews).

