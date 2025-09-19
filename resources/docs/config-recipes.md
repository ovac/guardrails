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
