<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow;

use InvalidArgumentException;
use Stringable;
use Throwable;
use Uri\WhatWg\Url;

final class Decider
{
    private const PLACEHOLDER_HOST = 'relative.invalid';

    private const PLACEHOLDER = 'https://relative.invalid/';

    /**
     * Return the WHATWG URL when the target is allowed, otherwise the fallback.
     *
     * Bad input uses the fallback. It does not throw.
     *
     * @param list<string|Stringable> $hosts
     */
    public function decide(?string $target, array $hosts, string $fallback, string $base): string
    {
        if ($target === null || preg_match('/^\s*$/u', $target) === 1) {
            return $fallback;
        }

        $target = preg_replace('/^\s+|\s+$/u', '', $target) ?? $target;
        if ($target === '' || str_starts_with($target, '//')) {
            return $fallback;
        }

        $baseUrl = $this->baseUrl($base);

        if (str_starts_with($target, '/')) {
            return $this->relativePath($target) ?? $fallback;
        }

        try {
            $errors = [];
            $url = Url::parse($target, $baseUrl, $errors);
        } catch (Throwable) {
            return $fallback;
        }

        if (!$url instanceof Url || $this->scheme($url) !== 'https') {
            return $fallback;
        }

        $parsed = $this->parsed($target);
        if ($this->authorityHasUserinfo($url, $parsed) || $this->parsedSchemeIsNotHttps($parsed)) {
            return $fallback;
        }

        $rawHost = $url->getAsciiHost();
        if (!is_string($rawHost) || $rawHost === '') {
            return $fallback;
        }

        $host = $this->normalizeHost($rawHost);
        if ($host === '' || str_ends_with($host, '.') || $this->hostsDisagree($parsed, $host)) {
            return $fallback;
        }

        if ($this->hostAllowed($host, $hosts)) {
            return $url->toAsciiString();
        }

        return $fallback;
    }

    /**
     * A path that starts with one slash is returned as a relative URL: path, query,
     * and fragment. It is parsed against a placeholder origin, not the request URL,
     * so the Host header cannot change where it points. If the parser moves it to
     * another host (for example /\\evil.example), or the normalized path starts with
     * two slashes (for example /..//evil.example), it is rejected.
     */
    private function relativePath(string $target): ?string
    {
        try {
            $errors = [];
            $url = Url::parse($target, Url::parse(self::PLACEHOLDER), $errors);
        } catch (Throwable) {
            return null;
        }

        if (!$url instanceof Url || $url->getAsciiHost() !== self::PLACEHOLDER_HOST) {
            return null;
        }

        if ($url->getUsername() !== null || $url->getPassword() !== null || $url->getPort() !== null) {
            return null;
        }

        $path = $url->getPath();
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        $query = $url->getQuery();
        $fragment = $url->getFragment();

        return $path . ($query !== null ? '?' . $query : '') . ($fragment !== null ? '#' . $fragment : '');
    }

    private function baseUrl(string $base): Url
    {
        try {
            $errors = [];
            $url = Url::parse($base, null, $errors);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The current absolute URL could not be parsed.', 0, $exception);
        }

        if (!$url instanceof Url) {
            throw new InvalidArgumentException('The current absolute URL could not be parsed.');
        }

        $host = $url->getAsciiHost();
        if ($this->scheme($url) === '' || !is_string($host) || $host === '') {
            throw new InvalidArgumentException('The current absolute URL could not be parsed.');
        }

        return $url;
    }

    private function scheme(Url $url): string
    {
        return strtolower(rtrim($url->getScheme(), ':'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsed(string $target): ?array
    {
        $parsed = parse_url($target);

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @param array<string, mixed>|null $parsed
     */
    private function authorityHasUserinfo(Url $url, ?array $parsed): bool
    {
        if ($url->getUsername() !== null || $url->getPassword() !== null) {
            return true;
        }

        if ($parsed === null) {
            return false;
        }

        return array_key_exists('user', $parsed) || array_key_exists('pass', $parsed);
    }

    /**
     * @param array<string, mixed>|null $parsed
     */
    private function parsedSchemeIsNotHttps(?array $parsed): bool
    {
        if ($parsed === null || !isset($parsed['scheme']) || !is_string($parsed['scheme'])) {
            return false;
        }

        return strtolower($parsed['scheme']) !== 'https';
    }

    /**
     * Both parsers named a host. They have to be the same host.
     *
     * @param array<string, mixed>|null $parsed
     */
    private function hostsDisagree(?array $parsed, string $host): bool
    {
        if ($parsed === null || !isset($parsed['host']) || !is_string($parsed['host'])) {
            return false;
        }

        $other = $this->normalizeHost($parsed['host']);
        if (preg_match('/[^\x00-\x7F]/', $other) === 1) {
            // A Unicode host is compared in its ASCII form. Only a plain Unicode
            // hostname converts; anything with other syntax stays as it is.
            $other = preg_match('~^[^/\\\\@:?#\s%\[\]]+$~u', $other) === 1 ? ($this->asciiHost($other) ?? $other) : $other;
        }

        return $other !== '' && $other !== $host;
    }

    /**
     * @param list<string|Stringable> $hosts
     */
    private function hostAllowed(string $host, array $hosts): bool
    {
        foreach ($hosts as $entry) {
            $normalized = $this->allowlistEntry($entry);
            if ($normalized === null) {
                continue;
            }

            if ($normalized['suffix']) {
                $suffix = $normalized['host'];
                if ($host !== $suffix && str_ends_with($host, '.' . $suffix)) {
                    return true;
                }

                continue;
            }

            if ($host === $normalized['host']) {
                return true;
            }
        }

        return false;
    }

    /**
     * An allowlist entry is a hostname, or a hostname with one leading dot.
     * A Unicode hostname is converted to its ASCII (punycode) form so it can match
     * the host the WHATWG parser produced. An entry that is not a hostname, such as
     * a URL, a host with a port, or a wildcard, is a configuration error.
     *
     * @return array{suffix: bool, host: string}|null
     */
    private function allowlistEntry(mixed $entry): ?array
    {
        if (!is_string($entry) && !$entry instanceof Stringable) {
            return null;
        }

        $raw = trim((string) $entry);
        if ($raw === '' || $raw === '.') {
            return null;
        }

        $suffix = str_starts_with($raw, '.') && !str_starts_with($raw, '..');
        $name = $suffix ? substr($raw, 1) : $raw;
        $bracketed = str_starts_with($name, '[') && str_ends_with($name, ']');
        if (!$bracketed && preg_match('~[/\\\\@:*?#\s%]~', $name) === 1) {
            throw new InvalidArgumentException(sprintf('Allowlist entry [%s] must be a hostname, such as example.com or .example.com.', $raw));
        }

        $host = $this->normalizeHost($name);
        if ($host === '') {
            return null;
        }

        if (!$bracketed) {
            $host = $this->asciiHost($host);
            if ($host === null) {
                throw new InvalidArgumentException(sprintf('Allowlist entry [%s] is not a valid hostname.', $raw));
            }
        }

        return ['suffix' => $suffix, 'host' => $host];
    }

    private function asciiHost(string $host): ?string
    {
        try {
            $errors = [];
            $url = Url::parse('https://' . $host . '/', null, $errors);
        } catch (Throwable) {
            return null;
        }

        if (!$url instanceof Url) {
            return null;
        }

        $ascii = $url->getAsciiHost();

        return is_string($ascii) && $ascii !== '' ? $this->normalizeHost($ascii) : null;
    }

    /**
     * Lowercase, drop IPv6 brackets, and drop one trailing dot (the DNS root).
     * A second trailing dot is kept, so an empty label never matches a real host.
     */
    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return str_ends_with($host, '.') ? substr($host, 0, -1) : $host;
    }
}
