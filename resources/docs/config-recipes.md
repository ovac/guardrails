title: Config Recipes
description: Practical configuration patterns and toggles.

# Config Recipes

## Route & Middleware

```php
// config/guardrails.php
return [
  'route_prefix' => env('GUARDRAILS_ROUTE_PREFIX', 'guardrails/api'),
  'middleware' => [
    'api',
    'auth:'.env('GUARDRAILS_AUTH_GUARD', config('auth.defaults.guard', 'web')),
    'throttle:60,1',
  ],
  'page_prefix' => env('GUARDRAILS_PAGE_PREFIX', 'guardrails'),
  'web_middleware' => [
    'web',
    'auth:'.env('GUARDRAILS_AUTH_GUARD', config('auth.defaults.guard', 'web')),
  ],
];
```

## Permissions Mapping

```php
'permissions' => [
  'view' => 'approvals.view',
  'sign' => 'approvals.sign',
],
```

## Disable Controller Helper Globally

```php
'controller' => [
  'enabled' => env('GUARDRAILS_CONTROLLER_ENABLED', true),
],
```

## Layout Integration

```php
'views' => [
  'layout' => 'layouts.app',
  'section' => 'content',
],
```

## Support Message

```php
'support' => [
  'motd' => env('GUARDRAILS_SUPPORT_MOTD', true),
],
```

## Configurable Controller Flows (one-liner)

Let ops override steps while your controller stays tiny. `guardrailFlow()` checks `guardrails.flows.<feature>.<action>` first, then uses your fallback, and merges meta defaults (like `summary`) onto every step. Use either nested arrays or a flat dot key—and if it's a single step you can skip the double brackets.

```php
// config/guardrails.php (optional override)
'flows' => [
  // flat dot key (cleaner)
  'orders.approve' => [[
    'name' => 'Ops Review',
    'threshold' => 1,
    'signers' => [
      'guard' => 'web',
      'permissions' => ['orders.approve'],
      'permissions_mode' => 'any',
      'roles' => [],
      'roles_mode' => 'all',
    ],
    'meta' => [
      'include_initiator' => false,
      'preapprove_initiator' => true,
      'hint' => 'Ops must sign off before approval.',
    ],
  ]],

  // or nested if you prefer:
  'orders' => [
    'approve' => [[
      'name' => 'Ops Review',
      'threshold' => 1,
      'signers' => [
        'guard' => 'web',
        'permissions' => ['orders.approve'],
        'permissions_mode' => 'any',
        'roles' => [],
        'roles_mode' => 'all',
      ],
      'meta' => [
        'include_initiator' => false,
        'preapprove_initiator' => true,
        'hint' => 'Ops must sign off before approval.',
      ],
    ]],
  ],

  // single-step shorthand (no extra brackets)
  // 'orders.approve' => [
  //   'name' => 'Ops Review',
  //   'threshold' => 1,
  //   'signers' => ['guard' => 'web', 'permissions' => ['orders.approve']],
  // ],
],
```

```php
// Controller
$flow = $this->guardrailFlow('orders.approve', $fallbackFlow, ['summary' => 'Order #'.$order->id]);
// pass $flow into guardrailIntercept(...)
```
