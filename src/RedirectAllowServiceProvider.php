<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow;

use Illuminate\Support\ServiceProvider;

final class RedirectAllowServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton(RedirectAllow::class, static function ($app): RedirectAllow {
            $url = $app->bound('config') ? $app->make('config')->get('app.url') : null;

            return new RedirectAllow(AppHost::fromUrl(is_string($url) ? $url : null));
        });
    }
}
