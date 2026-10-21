# Changelog

## v2.0.0 (2026-10-22)

Breaking changes. Nothing reads the request URL or its `Host` header any more.

- A relative target that does not start with one slash, such as `dashboard`, `./x`, `../x`, `?a=1`, or `#frag`, now uses the fallback. Before, it was resolved against the request URL and kept when that host was listed.
- `Decider::decide()` takes three arguments: target, hosts, fallback. The fourth `$base` argument is gone.
- `PendingRedirect::base()` is removed, and `url()` no longer reads `request()->fullUrl()`.
- `AllowedRedirect` takes `hosts` and an optional `appUrl`. The `base` argument is gone.
- In Laravel, the host of `config('app.url')` is added to the list by the facade and by the validation rule. Outside Laravel, only listed hosts are allowed.
- `//`, `/\`, backslashes, control characters, userinfo, and the other bypasses from v1 are still rejected. Ports work as before.

### Upgrade from v1

- `(new Decider())->decide($target, $hosts, $fallback, $base)` becomes `(new Decider())->decide($target, $hosts, $fallback)`.
- `RedirectAllow::to($next)->hosts([...])->fallback($url)->base($base)->send()` becomes the same chain without `->base($base)`.
- `new AllowedRedirect(hosts: [...], base: $base)` becomes `new AllowedRedirect(hosts: [...])`, or `new AllowedRedirect(hosts: [...], appUrl: 'https://app.example')` outside Laravel.
- Remove `$request->host()` from your host lists. Set `APP_URL` to your site's https URL instead.
- If you sent relative targets such as `dashboard` or `?tab=2`, send `/dashboard` or `/current/path?tab=2` instead.

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
