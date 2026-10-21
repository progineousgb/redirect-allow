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
        $this->app->singleton(RedirectAllow::class);
    }
}
