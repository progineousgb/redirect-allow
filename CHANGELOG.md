# Changelog

## v1.0.0 (2026-10-22)

First tagged release. Allowlist the host of a Laravel redirect target and fall back when it is not allowed.

- Keep redirect targets on hosts the application has listed.
- Fix: a Unicode allowlist entry never matched, because the target host is compared in ASCII (punycode). Entries and Unicode targets are now converted to ASCII.
- Fix: any number of trailing dots was stripped from a host. Only one trailing dot is ignored now, and two are rejected.
- An allowlist entry that is not a hostname, such as a URL, a host with a port, or `*.example.com`, now throws `InvalidArgumentException` instead of silently never matching.
- Tests for backslashes, control characters, encoded characters, userinfo tricks, IDN lookalikes, IP literals, ports, and the Laravel response and validation rule.
- README: the current-host note about the `Host` header and `TrustHosts`, port behavior, and setup steps for Linux, macOS, and Windows.
- PHPStan level 6 and a CI workflow for PHP 8.3 to 8.5.
