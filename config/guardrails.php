<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    | Choose which Laravel auth guard represents your reviewers/approvers.
    | Defaults to 'staff' but you can set to 'web', 'api', or any custom guard.
    */
    'auth' => [
        'guard' => env('GUARDRAILS_AUTH_GUARD', 'staff'),
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
    'route_prefix' => env('GUARDRAILS_ROUTE_PREFIX', 'staff/v1/guardrails'),

    /*
    | The middleware stack for API routes.
    */
    'middleware' => [
        'api', 'auth:staff', 'idempotent', 'scope.staff.country',
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
    'page_prefix' => env('GUARDRAILS_PAGE_PREFIX', 'staff/guardrails'),

    /*
    | The middleware stack for the web page route.
    */
    'web_middleware' => ['web', 'auth:staff'],

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
    | Controller Interceptor
    |--------------------------------------------------------------------------
    |
    | Toggle for the controller helper that routes mutations through Guardrails
    | when enabled (see InteractsWithActorApproval trait).
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
