<?php

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
        // Parent layout view name, or null to render standalone
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
