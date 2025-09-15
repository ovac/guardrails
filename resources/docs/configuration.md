title: Configuration Reference
description: Detailed explanation of every config option with defaults.

# Configuration Reference

Publish the config file and review available options:

```bash
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-config
```

Default config: `config/guardrails.php`

```php
return [
    // API route prefix and middleware
    'route_prefix' => env('GUARDRAILS_ROUTE_PREFIX', 'staff/v1/guardrails'),
    'middleware' => [
        'api', 'auth:staff', 'idempotent', 'scope.staff.country',
    ],

    // Web page prefix and middleware
    'page_prefix' => env('GUARDRAILS_PAGE_PREFIX', 'staff/guardrails'),
    'web_middleware' => ['web', 'auth:staff'],

    // Views
    'views' => [
        'layout' => env('GUARDRAILS_LAYOUT', null),
        'section' => env('GUARDRAILS_SECTION', 'content'),
    ],

    // Permissions used by routes and UI actions
    'permissions' => [
        'view' => 'approvals.manage',
        'sign' => 'approvals.manage',
    ],

    // Controller helper toggle
    'controller' => [
        'enabled' => env('GUARDRAILS_CONTROLLER_ENABLED', true),
    ],

    // Optional: one-time console support message
    'support' => [
        'motd' => env('GUARDRAILS_SUPPORT_MOTD', true),
        'github_repo' => 'ovac/guardrails',
        'github_sponsor' => 'ovac4u',
    ],
];
```

Notes

- route_prefix: The base path for the JSON API; keep it namespaced under your staff/admin APIs.
- middleware: Ensure your auth guard matches your staff guard.
- page_prefix: The browser-facing page where reviewers can see pending requests.
- views.layout and views.section: Provide a layout name if you want the bundled page to yield into an app layout.
- permissions.view and permissions.sign: These are consulted by the routes and the UI. Map to your authorization layer (Spatie permissions recommended).
- controller.enabled: Gate the controller helper so you can opt-out globally during development or certain environments.
- support.*: Controls the one-time console message asking to star/sponsor.

