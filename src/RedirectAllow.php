<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow;

use Stringable;

final class RedirectAllow
{
    /**
     * @param string|null $appHost The application's own host. In Laravel the service
     *                             provider passes the host of config('app.url').
     */
    public function __construct(private readonly ?string $appHost = null)
    {
    }

    public function to(null|string|Stringable $target): PendingRedirect
    {
        return new PendingRedirect($target === null ? null : (string) $target, $this->appHost);
    }
}
