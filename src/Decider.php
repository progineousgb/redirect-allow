<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow;

use InvalidArgumentException;
use Stringable;
use Throwable;
use Uri\WhatWg\Url;

final class Decider
{
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
        if ($host === '' || $this->hostsDisagree($parsed, $host)) {
            return $fallback;
        }

        $baseHost = $this->normalizeHost((string) $baseUrl->getAsciiHost());
        $relativePath = str_starts_with($target, '/') && !str_starts_with($target, '//');
        if ($relativePath && $host === $baseHost) {
            return $url->toAsciiString();
        }

        if ($this->hostAllowed($host, $hosts)) {
            return $url->toAsciiString();
        }

        return $fallback;
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
        $host = $this->normalizeHost($suffix ? substr($raw, 1) : $raw);
        if ($host === '') {
            return null;
        }

        return ['suffix' => $suffix, 'host' => $host];
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return rtrim($host, '.');
    }
}
