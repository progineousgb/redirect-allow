<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Progineous\RedirectAllow\RedirectAllow;
use Stringable;

final class AllowedRedirect implements ValidationRule
{
    private const REJECTED = "\0";

    /**
     * @param list<string|Stringable> $hosts
     */
    public function __construct(
        private readonly array $hosts,
        private readonly ?string $base = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !$value instanceof Stringable) {
            $fail('The :attribute is not an allowed redirect.');

            return;
        }

        $pending = (new RedirectAllow())
            ->to($value)
            ->hosts($this->hosts)
            ->fallback(self::REJECTED);

        if ($this->base !== null) {
            $pending->base($this->base);
        }

        if ($pending->url() === self::REJECTED) {
            $fail('The :attribute is not an allowed redirect.');
        }
    }
}
