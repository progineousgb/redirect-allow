<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Progineous\RedirectAllow\PendingRedirect to(string|\Stringable|null $target)
 */
final class RedirectAllow extends Facade
{
    /**
     * @return class-string<\Progineous\RedirectAllow\RedirectAllow>
     */
    protected static function getFacadeAccessor()
    {
        return \Progineous\RedirectAllow\RedirectAllow::class;
    }
}
