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

    public function __construct(
        private readonly ?string $target,
        private readonly ?string $appHost = null,
    ) {
    }

    /**
     * Hosts that absolute https targets may use. The application's own host,
     * from config('app.url'), is added for you in Laravel.
     *
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

    public function url(): string
    {
        if ($this->fallback === null) {
            throw new LogicException('A fallback URL is required.');
        }

        $hosts = $this->hosts;
        if ($this->appHost !== null) {
            $hosts[] = $this->appHost;
        }

        return (new Decider())->decide($this->target, $hosts, $this->fallback);
    }

    public function send(): RedirectResponse
    {
        return new RedirectResponse($this->url());
    }
}
