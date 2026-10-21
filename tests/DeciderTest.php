<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow\Tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Progineous\RedirectAllow\Decider;
use Progineous\RedirectAllow\RedirectAllow;
use Stringable;
use Uri\WhatWg\Url;

final class DeciderTest extends TestCase
{
    private const FALLBACK = 'https://app.example/home';

    #[DataProvider('decisions')]
    public function test_decision(string|null $target, array $hosts, string $expected): void
    {
        $this->assertSame($expected, $this->decide($target, $hosts));
    }

    public function test_allowed_result_is_the_parsed_url_not_the_raw_input(): void
    {
        $raw = 'https://Accept.PAYMOB.com/x';
        $result = $this->decide($raw, ['accept.paymob.com']);

        $this->assertSame('https://accept.paymob.com/x', $result);
        $this->assertNotSame($raw, $result);
    }

    public function test_fluent_api_returns_the_decided_url(): void
    {
        $url = (new RedirectAllow())
            ->to('https://Accept.PAYMOB.com/x')
            ->hosts(['accept.paymob.com'])
            ->fallback(self::FALLBACK)
            ->url();

        $this->assertSame('https://accept.paymob.com/x', $url);
    }

    public function test_stringable_target_is_accepted(): void
    {
        $target = new class implements Stringable {
            public function __toString(): string
            {
                return '/dashboard';
            }
        };

        $url = (new RedirectAllow())
            ->to($target)
            ->hosts([])
            ->fallback(self::FALLBACK)
            ->url();

        $this->assertSame('/dashboard', $url);
    }

    public function test_fallback_is_required(): void
    {
        $this->expectException(LogicException::class);

        (new RedirectAllow())
            ->to('/dashboard')
            ->url();
    }

    public function test_bad_input_does_not_throw(): void
    {
        $decider = new Decider();

        foreach (['::::', 'https://', 'https://%', "\0"] as $target) {
            $this->assertSame(self::FALLBACK, $decider->decide($target, ['accept.paymob.com'], self::FALLBACK));
        }
    }

    #[DataProvider('invalidAllowlistEntries')]
    public function test_an_allowlist_entry_that_is_not_a_hostname_is_a_configuration_error(string $entry): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->decide('https://accept.paymob.com/', [$entry]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidAllowlistEntries(): array
    {
        return [
            'a url' => ['https://accept.paymob.com'],
            'a wildcard' => ['*.example.com'],
            'a host with a port' => ['accept.paymob.com:443'],
            'a host with a path' => ['accept.paymob.com/cb'],
            'a host with userinfo' => ['user@accept.paymob.com'],
        ];
    }

    public function test_an_ipv6_allowlist_entry_matches(): void
    {
        $this->assertSame('https://[::1]/', $this->decide('https://[::1]/', ['[::1]']));
    }

    public function test_a_relative_path_needs_no_base_url(): void
    {
        $decider = new Decider();

        $this->assertSame('/dashboard?a=1', $decider->decide('/dashboard?a=1', [], self::FALLBACK));
        $this->assertSame(self::FALLBACK, $decider->decide('/\\evil.example', ['evil.example'], self::FALLBACK));
    }

    public function test_laravel_wiring_is_present_without_booting_the_framework(): void
    {
        $root = dirname(__DIR__);
        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('progineousgb/redirect-allow', $composer['name']);
        $this->assertSame(
            'Allowlist the host of a Laravel redirect target and fall back when it is not allowed.',
            $composer['description']
        );
        $this->assertSame('>=8.3', $composer['require']['php']);
        $this->assertArrayHasKey('league/uri-polyfill', $composer['require']);
        $this->assertArrayNotHasKey('illuminate/support', $composer['require']);
        $this->assertStringContainsString('^12.0 || ^13.0', $composer['suggest']['illuminate/support']);
        $this->assertSame(
            'Progineous\\RedirectAllow\\RedirectAllowServiceProvider',
            $composer['extra']['laravel']['providers'][0]
        );
        $this->assertSame(
            'Progineous\\RedirectAllow\\Facades\\RedirectAllow',
            $composer['extra']['laravel']['aliases']['RedirectAllow']
        );

        $provider = (string) file_get_contents($root . '/src/RedirectAllowServiceProvider.php');
        $this->assertStringContainsString('extends ServiceProvider', $provider);
        $this->assertStringContainsString('RedirectAllow::class', $provider);

        $facade = (string) file_get_contents($root . '/src/Facades/RedirectAllow.php');
        $this->assertStringContainsString('extends Facade', $facade);
        $this->assertStringContainsString('RedirectAllow::class;', $facade);

        $pending = (string) file_get_contents($root . '/src/PendingRedirect.php');
        $this->assertStringContainsString('function send(', $pending);
        $this->assertStringContainsString('RedirectResponse', $pending);

        $rule = (string) file_get_contents($root . '/src/Rules/AllowedRedirect.php');
        $this->assertStringContainsString('implements ValidationRule', $rule);
        $this->assertStringContainsString('function validate(', $rule);

        $this->assertTrue(class_exists(Url::class));
    }

    /**
     * @param list<string> $hosts
     */
    private function decide(string|null $target, array $hosts): string
    {
        return (new Decider())->decide($target, $hosts, self::FALLBACK);
    }

    /**
     * @return array<string, array{0: string|null, 1: list<string>, 2: string}>
     */
    public static function decisions(): array
    {
        $fallback = self::FALLBACK;
        $listed = ['accept.paymob.com'];

        return [
            'null uses the fallback' => [null, $listed, $fallback],
            'empty string uses the fallback' => ['', $listed, $fallback],
            'spaces use the fallback' => ['   ', $listed, $fallback],
            'tabs and newlines use the fallback' => ["\n\t  ", $listed, $fallback],
            'a single-slash path is returned as a relative url' => ['/dashboard', ['accept.paymob.com'], '/dashboard'],
            'a single-slash path keeps its query' => ['/dashboard?next=1', [], '/dashboard?next=1'],
            'surrounding space is trimmed from a relative path' => ['  /dashboard  ', [], '/dashboard'],
            'a protocol-relative url is rejected even when its host is listed' => ['//evil.example/phish', ['evil.example'], $fallback],
            'a spaced protocol-relative url is rejected' => ['  //evil.example/phish', ['evil.example'], $fallback],
            'a listed host is allowed' => ['https://accept.paymob.com/cb', $listed, 'https://accept.paymob.com/cb'],
            'a listed host keeps the query and fragment' => ['https://accept.paymob.com/cb?ok=1#done', $listed, 'https://accept.paymob.com/cb?ok=1#done'],
            'a bare listed host is returned as the parser wrote it' => ['https://accept.paymob.com', $listed, 'https://accept.paymob.com/'],
            'a non-default port stays on a listed host' => ['https://accept.paymob.com:8443/cb', $listed, 'https://accept.paymob.com:8443/cb'],
            'a different host is rejected' => ['https://evil.example/phish', $listed, $fallback],
            'javascript is rejected' => ['javascript:alert(1)', $listed, $fallback],
            'an uppercase javascript scheme is rejected' => ['JAVASCRIPT:alert(1)', $listed, $fallback],
            'a data scheme is rejected' => ['data:text/html,hi', $listed, $fallback],
            'a file scheme is rejected' => ['file:///etc/passwd', $listed, $fallback],
            'http is rejected when the host is listed' => ['http://accept.paymob.com/cb', $listed, $fallback],
            'a username in the authority is rejected' => ['https://user@accept.paymob.com/return', $listed, $fallback],
            'a username and password are rejected' => ['https://user:secret@accept.paymob.com/', $listed, $fallback],
            'a password in the authority is rejected' => ['https://:secret@accept.paymob.com/', $listed, $fallback],
            'an empty username in the authority is rejected' => ['https://@accept.paymob.com/', $listed, $fallback],
            'an at sign in the path is allowed' => ['https://accept.paymob.com/@user', $listed, 'https://accept.paymob.com/@user'],
            'host comparison is case-insensitive' => ['https://Accept.PAYMOB.com/x', $listed, 'https://accept.paymob.com/x'],
            'an uppercase scheme is allowed for a listed host' => ['HTTPS://accept.paymob.com/cb', $listed, 'https://accept.paymob.com/cb'],
            'a dotted suffix allows a subdomain' => ['https://a.example.com/', ['.example.com'], 'https://a.example.com/'],
            'a dotted suffix allows a nested subdomain' => ['https://foo.bar.example.com/x', ['.example.com'], 'https://foo.bar.example.com/x'],
            'a dotted suffix does not allow the apex' => ['https://example.com/', ['.example.com'], $fallback],
            'a dotted suffix does not allow a lookalike host' => ['https://example.com.evil.test/', ['.example.com'], $fallback],
            'a dotted suffix does not allow a name that only contains the text' => ['https://notexample.com/', ['.example.com'], $fallback],
            'a dotted suffix does not allow a different site with extra labels' => ['https://sub.example.com.evil.test/', ['.example.com'], $fallback],
            'a full hostname does not allow its subdomain' => ['https://a.example.com/', ['example.com'], $fallback],
            'a full hostname matches regardless of case' => ['https://EXAMPLE.com/', ['example.com'], 'https://example.com/'],
            'a trailing dot still matches that host' => ['https://accept.paymob.com./ok', $listed, 'https://accept.paymob.com./ok'],
            'a trailing dot does not match a shorter different site' => ['https://example.com.evil.test./', ['example.com'], $fallback],
            'a trailing dot does not match another suffix' => ['https://example.com./', ['evil.test'], $fallback],
            'a trailing dot matches the same longer host' => ['https://example.com.evil.test./', ['example.com.evil.test'], 'https://example.com.evil.test./'],
            'a dotted suffix ignores case' => ['https://Pay.Example.COM/return', ['.Example.COM'], 'https://pay.example.com/return'],
            'an allowlist entry with a trailing dot matches the host' => ['https://accept.paymob.com/cb', ['accept.paymob.com.'], 'https://accept.paymob.com/cb'],
            'a listed host is allowed when the parsers agree' => ['https://allowed.example/ok', ['allowed.example'], 'https://allowed.example/ok'],
            'the fallback is used when the parsers disagree on the host' => ['https://allowed.example\\.evil.example/', ['allowed.example'], $fallback],
            'a slash path that resolves to another host is rejected' => ['/\\evil.example', $listed, $fallback],
            'garbage uses the fallback' => ['::::', $listed, $fallback],
            'a bare relative path is rejected' => ['dashboard', ['app.example'], $fallback],
            'a dot relative path is rejected' => ['./dashboard', ['app.example'], $fallback],
            'a parent relative path is rejected' => ['../x', ['app.example'], $fallback],
            'a query-only target is rejected' => ['?a=1', ['app.example'], $fallback],
            'a fragment-only target is rejected' => ['#frag', ['app.example'], $fallback],
            'a relative path that looks like a host is rejected' => ['accept.paymob.com/cb', $listed, $fallback],
            'a zero-width space before a slash path is rejected' => ["\u{200B}/dashboard", ['app.example'], $fallback],
            'a byte order mark before a protocol-relative url is rejected' => ["\u{FEFF}//evil.example", ['app.example'], $fallback],
            'https without slashes on another host is rejected' => ['https:evil.example', $listed, $fallback],
            'https with one slash on another host is rejected' => ['https:/evil.example', $listed, $fallback],
            'two trailing dots do not match the host' => ['https://accept.paymob.com../ok', $listed, $fallback],
            'a unicode allowlist entry matches the punycode target' => ['https://xn--bcher-kva.de/', ["b\u{00FC}cher.de"], 'https://xn--bcher-kva.de/'],
            'a unicode target matches a unicode allowlist entry' => ["https://b\u{00FC}cher.de/", ["b\u{00FC}cher.de"], 'https://xn--bcher-kva.de/'],
            'a unicode target matches a punycode allowlist entry' => ["https://m\u{00FC}nchen.de/", ['xn--mnchen-3ya.de'], 'https://xn--mnchen-3ya.de/'],
            'a cyrillic lookalike host is rejected' => ["https://\u{0430}pp.example/", ['app.example'], $fallback],
            'an ideographic full stop does not join a listed host' => ["https://evil.example\u{3002}accept.paymob.com/", $listed, $fallback],
            'a backslash after a slash path is rejected' => ['/\\/evil.example', $listed, $fallback],
            'a tab inside a protocol-relative url is rejected' => ["/\t/evil.example", $listed, $fallback],
            'a newline inside a protocol-relative url is rejected' => ["/\n/evil.example", $listed, $fallback],
            'a nul before a protocol-relative url is rejected' => ["\0//evil.example", $listed, $fallback],
            'an ideographic space before a protocol-relative url is rejected' => ["\u{3000}//evil.example", $listed, $fallback],
            'a double backslash is rejected' => ['\\\\evil.example', $listed, $fallback],
            'https with a backslash authority is rejected' => ['https:\\\\evil.example', $listed, $fallback],
            'a mixed case https scheme on another host is rejected' => ['HtTpS://evil.example/', $listed, $fallback],
            'userinfo with a backslash is rejected' => ['https://evil.example\\@accept.paymob.com/', $listed, $fallback],
            'an encoded at sign does not create userinfo' => ['https://accept.paymob.com%40evil.example/', $listed, $fallback],
            'an encoded dot does not join hosts' => ['https://accept.paymob.com%2eevil.example/', $listed, $fallback],
            'a port before an at sign is rejected' => ['https://accept.paymob.com:443@evil.example/', $listed, $fallback],
            'a fragment at sign keeps the real host' => ['https://evil.example#@accept.paymob.com', $listed, $fallback],
            'a query at sign keeps the real host' => ['https://evil.example?@accept.paymob.com', $listed, $fallback],
            'an out of range port is rejected' => ['https://accept.paymob.com:99999/', $listed, $fallback],
            'a javascript url with a listed host is rejected' => ['javascript://accept.paymob.com/%0aalert(1)', $listed, $fallback],
            'a javascript scheme with a tab is rejected' => ["java\tscript:alert(1)", $listed, $fallback],
            'a javascript scheme with leading space is rejected' => [' javascript:alert(1)', $listed, $fallback],
            'a blob url is rejected' => ['blob:https://accept.paymob.com/x', $listed, $fallback],
            'a websocket url is rejected' => ['wss://accept.paymob.com/', $listed, $fallback],
            'an ipv4 literal is rejected when not listed' => ['https://127.0.0.1/', $listed, $fallback],
            'a hex ipv4 literal is rejected when not listed' => ['https://0x7f.1/', $listed, $fallback],
            'an ipv6 literal is rejected when not listed' => ['https://[::1]/', $listed, $fallback],
            'a dot segment path that becomes a double slash is rejected' => ['/..//evil.example', [], $fallback],
            'a dot segment before a backslash is rejected' => ['/./\\evil.example', [], $fallback],
            'a relative path keeps its fragment' => ['/a/b?x=1#top', [], '/a/b?x=1#top'],
            'dot segments are resolved in a relative path' => ['/a/../b', [], '/b'],
            'a relative path is encoded by the parser' => ["/caf\u{00E9} x", [], '/caf%C3%A9%20x'],
            'an encoded double slash stays a path' => ['/%2F/evil.example', [], '/%2F/evil.example'],
            'an encoded backslash stays a path' => ['/%5Cevil.example', [], '/%5Cevil.example'],
            'a carriage return inside a protocol-relative url is rejected' => ["/\r/evil.example", [], $fallback],
            'a relative path with a backslash and tab is rejected' => ["/\t\\evil.example", [], $fallback],
            'a path without a slash is rejected when the current host is not listed' => ['evil.example', $listed, $fallback],
        ];
    }
}
