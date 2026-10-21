<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Progineous\RedirectAllow\RedirectAllow;
use Progineous\RedirectAllow\RedirectAllowServiceProvider;
use Progineous\RedirectAllow\Rules\AllowedRedirect;

final class LaravelTest extends TestCase
{
    private const FALLBACK = 'https://app.example/home';

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    /**
     * A container with config('app.url') and a request whose Host header is $host.
     */
    private function boot(string $host, string $appUrl = 'https://app.example'): Container
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository(['app' => ['url' => $appUrl]]));
        $app->instance('request', Request::create('https://'.$host.'/login', 'GET', [], [], [], ['HTTP_HOST' => $host]));
        (new RedirectAllowServiceProvider($app))->register();

        return $app;
    }

    private function decide(Container $app, string $target, array $hosts = ['accept.paymob.com']): string
    {
        return $app->make(RedirectAllow::class)->to($target)->hosts($hosts)->fallback(self::FALLBACK)->url();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function targets(): array
    {
        return [
            'slash path' => ['/dashboard?a=1'],
            'listed host' => ['https://accept.paymob.com/cb'],
            'app host' => ['https://app.example/settings'],
            'spoofed host' => ['https://evil.example/phish'],
            'bare relative' => ['dashboard'],
            'parent relative' => ['../x'],
            'query only' => ['?next=1'],
            'fragment only' => ['#top'],
            'backslash path' => ['/\\evil.example'],
            'protocol relative' => ['//evil.example'],
        ];
    }

    #[DataProvider('targets')]
    public function test_a_spoofed_host_header_gives_the_same_result(string $target): void
    {
        $honest = $this->decide($this->boot('app.example'), $target);
        $spoofed = $this->decide($this->boot('evil.example'), $target);

        $this->assertSame($honest, $spoofed);
        $this->assertStringNotContainsString('evil.example', $spoofed);
    }

    #[DataProvider('targets')]
    public function test_the_validation_rule_ignores_a_spoofed_host_header(string $target): void
    {
        $this->boot('app.example');
        $honest = $this->failures(new AllowedRedirect(['accept.paymob.com']), $target);
        $this->boot('evil.example');
        $spoofed = $this->failures(new AllowedRedirect(['accept.paymob.com']), $target);

        $this->assertSame($honest, $spoofed);
    }

    public function test_the_app_url_host_is_allowed(): void
    {
        $app = $this->boot('evil.example', 'https://app.example');

        $this->assertSame('https://app.example/settings', $this->decide($app, 'https://app.example/settings', []));
        $this->assertSame(self::FALLBACK, $this->decide($app, 'https://evil.example/settings', []));
    }

    public function test_a_different_app_url_changes_the_allowed_host(): void
    {
        $app = $this->boot('app.example', 'https://shop.example/');

        $this->assertSame('https://shop.example/cart', $this->decide($app, 'https://shop.example/cart', []));
        $this->assertSame(self::FALLBACK, $this->decide($app, 'https://app.example/cart', []));
    }

    public function test_the_validation_rule_uses_the_app_url_host(): void
    {
        $this->boot('evil.example', 'https://app.example');

        $this->assertSame([], $this->failures(new AllowedRedirect(), 'https://app.example/settings'));
        $this->assertSame(['next'], $this->failures(new AllowedRedirect(), 'https://evil.example/settings'));
    }

    public function test_the_validation_rule_accepts_an_explicit_app_url(): void
    {
        $this->assertSame([], $this->failures(new AllowedRedirect([], 'https://shop.example'), 'https://shop.example/cart'));
        $this->assertSame(['next'], $this->failures(new AllowedRedirect([], 'https://shop.example'), 'https://app.example/cart'));
    }

    public function test_without_laravel_only_listed_hosts_are_allowed(): void
    {
        $pending = (new RedirectAllow())->to('https://app.example/x')->fallback(self::FALLBACK);

        $this->assertSame(self::FALLBACK, $pending->url());
    }

    public function test_send_returns_a_redirect_to_the_decided_url(): void
    {
        $response = (new RedirectAllow())
            ->to('https://evil.example/phish')
            ->hosts(['accept.paymob.com'])
            ->fallback(self::FALLBACK)
            ->send();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(self::FALLBACK, $response->getTargetUrl());
    }

    public function test_send_keeps_a_slash_path_relative(): void
    {
        $this->boot('evil.example');
        $response = $this->bootedSend('/dashboard');

        $this->assertSame('/dashboard', $response->headers->get('Location'));
    }

    public function test_validation_rule_fails_for_a_host_that_is_not_listed(): void
    {
        $this->assertSame(['next'], $this->failures(new AllowedRedirect(['accept.paymob.com']), 'https://evil.example/'));
    }

    public function test_validation_rule_passes_for_a_listed_host_and_a_slash_path(): void
    {
        $rule = new AllowedRedirect(['accept.paymob.com']);

        $this->assertSame([], $this->failures($rule, 'https://accept.paymob.com/cb'));
        $this->assertSame([], $this->failures($rule, '/dashboard'));
    }

    public function test_validation_rule_fails_for_a_relative_target_without_a_slash(): void
    {
        $this->assertSame(['next'], $this->failures(new AllowedRedirect([], 'https://app.example'), 'dashboard'));
    }

    public function test_validation_rule_fails_for_a_value_that_is_not_a_string(): void
    {
        $this->assertSame(['next'], $this->failures(new AllowedRedirect(), ['/dashboard']));
    }

    private function bootedSend(string $target): RedirectResponse
    {
        return Container::getInstance()->make(RedirectAllow::class)->to($target)->fallback(self::FALLBACK)->send();
    }

    /**
     * @return list<string>
     */
    private function failures(AllowedRedirect $rule, mixed $value): array
    {
        $failed = [];
        $rule->validate('next', $value, function () use (&$failed): void {
            $failed[] = 'next';
        });

        return $failed;
    }
}
