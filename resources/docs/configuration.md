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
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    | Choose which Laravel auth guard represents your reviewers/approvers.
    | Defaults to your application's auth.defaults.guard (typically 'web').
    */
    'auth' => [
        'guard' => env('GUARDRAILS_AUTH_GUARD', config('auth.defaults.guard', 'web')),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Configure the API endpoint under which Guardrails exposes its endpoints
    | and the middleware stack protecting those routes.
    |
    */
    'route_prefix' => env('GUARDRAILS_ROUTE_PREFIX', 'guardrails/api'),

    /*
    | The middleware stack for API routes.
    */
    'middleware' => [
        'api', 'auth:'.env('GUARDRAILS_AUTH_GUARD', config('auth.defaults.guard', 'web')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Page
    |--------------------------------------------------------------------------
    |
    | Configure the browser-facing page (a minimal UI bundled by the package)
    | and the middleware stack protecting it.
    |
    */
    'page_prefix' => env('GUARDRAILS_PAGE_PREFIX', 'guardrails'),

    /*
    | The middleware stack for the web page route.
    */
    'web_middleware' => ['web', 'auth:'.env('GUARDRAILS_AUTH_GUARD', config('auth.defaults.guard', 'web'))],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | You may optionally specify a layout name and the section where the
    | bundled view should inject its content.
    |
    */
    'views' => [
        // Parent layout view name, or null to render the standalone Guardrails UI
        'layout' => env('GUARDRAILS_LAYOUT', null),
        // Section name inside the layout to yield content to
        'section' => env('GUARDRAILS_SECTION', 'content'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Abilities checked before viewing approval requests or signing steps.
    | These should map to your authorization layer (e.g. Spatie permissions).
    |
    */
    'permissions' => [
        // View the approvals dashboard or list via API
        'view' => 'approvals.manage',
        // Approve or sign a step
        'sign' => 'approvals.manage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Signing Policy
    |--------------------------------------------------------------------------
    |
    | Customize how Guardrails resolves role membership when evaluating signer
    | rules. Provide a closure that receives the authenticated user and returns
    | an array of role identifiers, or leave null to use built-in fallbacks.
    |
    */
    'signing' => [
        'resolve_roles_using' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Controller Interceptor
    |--------------------------------------------------------------------------
    |
    | Toggle for the controller helper that routes mutations through Guardrails
    | when enabled (see InteractsWithGuardrail trait).
    |
    */
    'controller' => [
        'enabled' => env('GUARDRAILS_CONTROLLER_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurable Approval Flows (Controller Interceptor)
    |--------------------------------------------------------------------------
    |
    | Optional overrides for controller-level flows. Keys map to
    | guardrails.flows.<feature>.<action>. Each value is an array of steps
    | shaped like FlowBuilder output (signers, threshold, meta).
    |
    | Example:
    | 'flows' => [
    |     'orders' => [
    |         'approve' => [
    |             [
    |                 'name' => 'Risk Review',
    |                 'threshold' => 1,
    |                 'signers' => [
    |                     'guard' => 'web',
    |                     'permissions' => ['orders.approve'],
    |                     'permissions_mode' => 'any',
    |                     'roles' => [],
    |                     'roles_mode' => 'all',
    |                 ],
|                 'meta' => [
|                     'include_initiator' => false,
|                     'preapprove_initiator' => true,
|                     'hint' => 'Ops review before approving.',
|                 ],
|             ],
|         ],
|     ],
| ],
*/
'flows' => [
    // empty by default; opt in per feature/action
],

// You can also flatten the key if you prefer:
// 'flows' => [
//     'orders.approve' => [
//         ['name' => 'Risk Review', 'threshold' => 1, 'signers' => [...], 'meta' => []],
//     ],
// ],
// Single-step shorthand (no double brackets) is also allowed:
// 'flows' => [
//     'orders.approve' => [
//         'name' => 'Risk Review',
//         'threshold' => 1,
//         'signers' => ['guard' => 'web', 'permissions' => ['orders.approve']],
//     ],
// ],

    /*
    |--------------------------------------------------------------------------
    | Support & Sponsorship
    |--------------------------------------------------------------------------
    |
    | When enabled, the package prints a short one-time message in the
    | console after installation (first artisan run) asking users to star
    | the repo or sponsor. You can disable this if undesired.
    |
    */
    'support' => [
        'motd' => env('GUARDRAILS_SUPPORT_MOTD', true),
        'github_repo' => 'ovac/guardrails',
        'github_sponsor' => 'ovac4u',
    ],
];
```

Use `guardrailFlow('<feature>.<action>', $fallback, ['summary' => '...'])` in your controllers to read `guardrails.flows` entries. Config values win when present, otherwise your fallback is used, and the meta defaults merge onto every step.

Notes

- `auth.guard`: Defaults to `auth.defaults.guard` (usually `web`). Set `GUARDRAILS_AUTH_GUARD` or edit the config if approvals should use another guard (e.g. `sanctum`, `api`).
- `route_prefix`: Base path for the JSON API; adjust to match your application's namespace.
- `middleware`: Guardrails defaults to `['api','auth:{guard}`]. Replace or extend this array to match your middleware stack.
- `page_prefix`: Browser-facing route for the review UI.
- `views.layout` / `views.section`: Provide a layout if you want the bundled page to yield into your app shell. Leave `layout` `null` to serve the standalone UI, or keep it `null` and include `@include('guardrails::panel')` wherever you want the panel to appear inside your own view.
- `permissions.view` and `permissions.sign`: Abilities consulted by the routes and UI (map to your policy layer).
- `flows`: Optional controller overrides keyed as `<feature>.<action>` (e.g., `orders.approve`). Pull them in with `guardrailFlow('orders.approve', $fallback, ['summary' => 'Order #'.$order->id])`; config wins when present, otherwise the fallback is used, and meta defaults are merged onto every step.
- `signing.resolve_roles_using`: Supply a closure if you need to resolve roles from a custom source (see [Signing Policy Reference](./signing-policy.md)).

## Authentication helper

Guardrails ships a small helper (`OVAC\\Guardrails\\Support\\Auth`) that centralizes guard resolution:

- `Auth::guardName()` returns the configured guard, falling back to `auth.defaults.guard` or `web`.
- `Auth::guard()` resolves the framework guard instance.
- `Auth::user()` / `Auth::check()` mirror Laravel’s helpers but respect the Guardrails configuration.
- `Auth::providerModelClass()` and `Auth::findUserById()` resolve users via the guard’s provider, handling both Eloquent models and custom classes.

You can rely on these helpers when writing custom integrations (policies, events) so your code stays in sync with the guard Guardrails uses.

Additional options:

- `controller.enabled`: Gate the controller helper so you can opt-out globally during development or certain environments.
- `support.*`: Controls the one-time console message asking teams to star or sponsor the package.
