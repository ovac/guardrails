title: UI & Assets
description: The bundled approval dashboard and how to customize it.

# UI & Assets

Guardrails ships a production-ready Blade view for reviewing pending requests at `/{page_prefix}` (default `guardrails`). The template now runs two ways:

- **Standalone** (no layout configured) – renders a full HTML document with Guardrails-branded styling and loads Alpine.js from the CDN automatically.
- **Embedded** (layout configured) – you can drop the Guardrails panel inside any dashboard by including the shared partial and letting the view push the required assets onto your stacks.

## Publish Views & Assets

```bash
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-views
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-assets
```

Views are published to `resources/views/vendor/guardrails` and assets to `public/vendor/guardrails`.

## Layout Integration

Set `views.layout` and `views.section` in `config/guardrails.php` to render the page inside your app layout. Make sure your layout yields the relevant stacks:

```blade
<!-- resources/views/layouts/app.blade.php -->
<head>
    <!-- ... -->
    @stack('styles')
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
```

If `views.layout` is left `null`, Guardrails renders the opinionated standalone UI so you can host the screen as-is or iframe/embed it in other tools.

### Embedding the panel manually

If you would rather place the approval panel inside a custom screen (for example alongside other stats), include the shared partial and keep the stacks in place so the JavaScript loads:

```blade
{{-- resources/views/admin/approvals.blade.php --}}
@extends('layouts.app')

@section('content')
  <h1>Pending Approvals</h1>
  @include('guardrails::panel')
@endsection

@push('styles')
  {{-- your layout should already yield @stack('styles') so Guardrails can inject its CSS --}}
@endpush

@push('scripts')
  {{-- Guardrails pushes Alpine + helper scripts here automatically --}}
@endpush
```

Behind the scenes the `guardrails::panel` partial and the standalone page use the same Alpine component, so you get identical behaviour whichever route you take.

## Customization Tips

- Tailor the request list by editing `resources/views/vendor/guardrails/index.blade.php` after publishing. The Alpine component lives inside `guardrailsComponent()`.
- Need different fonts or colours? Override the CSS block in the published view or swap in your design system when embedding.
- Harden buttons with your authorization logic if you deviate from default permissions—use policies or middleware to guard the controller action.
- Hook into the API to extend payloads (e.g., append approvable previews) or add additional actions alongside the Approve button.
