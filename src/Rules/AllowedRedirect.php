<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Progineous\RedirectAllow\AppHost;
use Progineous\RedirectAllow\RedirectAllow;
use Stringable;

final class AllowedRedirect implements ValidationRule
{
    private const REJECTED = "\0";

    /**
     * @param list<string|Stringable> $hosts Hosts that absolute https targets may use.
     * @param string|null $appUrl The application's own URL. When it is null, the host
     *                            of config('app.url') is used if Laravel is running.
     */
    public function __construct(
        private readonly array $hosts = [],
        private readonly ?string $appUrl = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !$value instanceof Stringable) {
            $fail('The :attribute is not an allowed redirect.');

            return;
        }

        $appHost = $this->appUrl !== null ? AppHost::fromUrl($this->appUrl) : AppHost::fromConfig();
        $pending = (new RedirectAllow($appHost))
            ->to($value)
            ->hosts($this->hosts)
            ->fallback(self::REJECTED);

        if ($pending->url() === self::REJECTED) {
            $fail('The :attribute is not an allowed redirect.');
        }
    }
}
