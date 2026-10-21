<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow;

use Stringable;

final class RedirectAllow
{
    public function to(null|string|Stringable $target): PendingRedirect
    {
        return new PendingRedirect($target === null ? null : (string) $target);
    }
}
