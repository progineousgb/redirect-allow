# Changelog

## v1.1.0 (2026-10-22)

Behavior change: an allowed single-slash path is now returned as a relative URL, not an absolute URL on the current host.

- `/dashboard?tab=2#top` now returns `/dashboard?tab=2#top`. Before, it returned `https://<current host>/dashboard?tab=2#top`.
- A single-slash path is no longer resolved against the request URL, so the `Host` header cannot redirect it to another site. It is parsed against a placeholder origin and only the path, query, and fragment are kept.
- A single-slash path is now also kept on a plain http site, such as `http://localhost` in development. Before, it used the fallback.
- A path that normalizes to two leading slashes, such as `/..//evil.example`, is now rejected. Before, it returned `https://<current host>//evil.example`.
- `//`, `/\`, backslashes, tabs, newlines, and other control characters that turn a path into another host are still rejected.
- Targets without a leading slash, absolute URLs, and ports work as before.

## v1.0.0 (2026-10-22)

First tagged release. Allowlist the host of a Laravel redirect target and fall back when it is not allowed.

- Keep redirect targets on hosts the application has listed.
- Fix: a Unicode allowlist entry never matched, because the target host is compared in ASCII (punycode). Entries and Unicode targets are now converted to ASCII.
- Fix: any number of trailing dots was stripped from a host. Only one trailing dot is ignored now, and two are rejected.
- An allowlist entry that is not a hostname, such as a URL, a host with a port, or `*.example.com`, now throws `InvalidArgumentException` instead of silently never matching.
- Tests for backslashes, control characters, encoded characters, userinfo tricks, IDN lookalikes, IP literals, ports, and the Laravel response and validation rule.
- README: the current-host note about the `Host` header and `TrustHosts`, port behavior, and setup steps for Linux, macOS, and Windows.
- PHPStan level 6 and a CI workflow for PHP 8.3 to 8.5.
