<?php

namespace OVAC\Guardrails;

use Illuminate\Support\ServiceProvider;

/**
 * GuardrailsServiceProvider boots routes, views, config and publishable assets
 * for the ovac/guardrails package when embedded in an application.
 */
class GuardrailsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/guardrails.php', 'guardrails');
    }

    public function boot(): void
    {
        // One-time support message in console after install
        if ($this->app->runningInConsole()) {
            $this->maybeShowSupportMessage();
        }

        // Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $web = __DIR__.'/../routes/web.php';
        if (file_exists($web)) {
            $this->loadRoutesFrom($web);
        }

        // Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'guardrails');

        // Publishes
        $this->publishes([
            __DIR__.'/../config/guardrails.php' => config_path('guardrails.php'),
        ], 'guardrails-config');

        $migrations = __DIR__.'/../database/migrations/';
        if (is_dir($migrations)) {
            $this->publishes([
                $migrations => database_path('migrations'),
            ], 'guardrails-migrations');
        }

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/guardrails'),
        ], 'guardrails-views');

        if (is_dir(__DIR__.'/../resources/assets')) {
            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/guardrails'),
            ], 'guardrails-assets');
        }

        // Docs
        if (is_dir(__DIR__.'/../resources/docs')) {
            $this->publishes([
                __DIR__.'/../README.md' => base_path('docs/guardrails/README.md'),
                __DIR__.'/../resources/docs' => base_path('docs/guardrails'),
            ], 'guardrails-docs');
        }
    }

    /**
     * Print a one-time support message in console asking for a star/sponsorship.
     */
    protected function maybeShowSupportMessage(): void
    {
        if (!config('guardrails.support.motd', true)) {
            return;
        }
        try {
            $dir = storage_path('app/vendor/guardrails');
            $ack = $dir.'/.support_ack';
            if (is_file($ack)) {
                return;
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $repo = (string) config('guardrails.support.github_repo', 'ovac/guardrails');
            $sponsor = (string) config('guardrails.support.github_sponsor', 'ovac4u');

            $lines = [
                '',
                ' Thank you for installing Guardrails! 💚',
                ' If it helps you, please consider:',
                '  - Starring the repo: https://github.com/'.$repo,
                '  - Sponsoring on GitHub: https://github.com/sponsors/'.$sponsor,
                '',
            ];

            // Write to stdout in console context
            foreach ($lines as $l) {
                echo $l.PHP_EOL;
            }

            @file_put_contents($ack, 'shown');
        } catch (\Throwable $e) {
            // Never block app boot on this
        }
    }
}
