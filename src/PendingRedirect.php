<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow;

use Illuminate\Http\RedirectResponse;
use LogicException;
use Stringable;

final class PendingRedirect
{
    /** @var list<string|Stringable> */
    private array $hosts = [];

    private ?string $fallback = null;

    private ?string $base = null;

    public function __construct(private readonly ?string $target)
    {
    }

    /**
     * @param list<string|Stringable> $hosts
     */
    public function hosts(array $hosts): self
    {
        $this->hosts = $hosts;

        return $this;
    }

    public function fallback(string|Stringable $fallback): self
    {
        $this->fallback = (string) $fallback;

        return $this;
    }

    public function base(string|Stringable $base): self
    {
        $this->base = (string) $base;

        return $this;
    }

    public function url(): string
    {
        if ($this->fallback === null) {
            throw new LogicException('A fallback URL is required.');
        }

        return (new Decider())->decide(
            $this->target,
            $this->hosts,
            $this->fallback,
            $this->currentBase(),
        );
    }

    public function send(): RedirectResponse
    {
        return new RedirectResponse($this->url());
    }

    private function currentBase(): string
    {
        if ($this->base !== null && trim($this->base) !== '') {
            return $this->base;
        }

        if (function_exists('request')) {
            $request = request();
            if (is_object($request) && method_exists($request, 'fullUrl')) {
                $full = $request->fullUrl();
                if (is_string($full) && trim($full) !== '') {
                    return $full;
                }
            }
        }

        throw new LogicException('An absolute current URL is required.');
    }
}
