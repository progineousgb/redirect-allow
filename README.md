# redirect-allow

## What it is

redirect-allow decides where a Laravel redirect may send the browser. You pass the target, a list of hosts, and a fallback. A relative path on the current site, or an https URL whose host is on the list, is kept. Every other target is replaced with the fallback.

Use it for the URL a person returns to after login, and for the URL a payment page sends them back to.

## What you get

- `RedirectAllow::to()` builds a redirect and `send()` returns a Laravel redirect response.
- `url()` returns the decided URL as a string when you do not want the response yet.
- `Progineous\RedirectAllow\Decider` is the same decision in pure PHP. It does not boot a framework.
- `Progineous\RedirectAllow\Rules\AllowedRedirect` is a validation rule for a form request.

```php
use Progineous\RedirectAllow\Decider;

$next = (new Decider())->decide(
    $target,
    ['app.example', 'accept.paymob.com'],
    'https://app.example/home',
    'https://app.example/login',
);
```

```php
use Progineous\RedirectAllow\Rules\AllowedRedirect;

'next' => ['nullable', 'string', new AllowedRedirect(
    hosts: [$request->host(), 'accept.paymob.com'],
)],
```

## Install

The package is not on Packagist. Install it from GitHub:

```bash
composer config repositories.redirect-allow vcs https://github.com/progineousgb/redirect-allow
composer require progineousgb/redirect-allow:dev-main
```

### Linux

Install PHP 8.3 or newer and Composer from your distribution or from the official PHP packages, then run the two commands above in your project. On Debian or Ubuntu: `sudo apt install php-cli php-xml php-mbstring unzip`, then install Composer from https://getcomposer.org/download/.

### macOS

With Homebrew: `brew install php composer`, then run the two commands above in your project.

### Windows

Install PHP 8.3 or newer from https://windows.php.net/download/ and add it to `PATH`, then install Composer with the Windows installer from https://getcomposer.org/download/. Run the two commands above in PowerShell or Command Prompt, in your project folder.

### Run the tests

```bash
git clone https://github.com/progineousgb/redirect-allow
cd redirect-allow
composer install
composer test
composer analyse
```

PHP 8.3 or newer is required. The decision uses `Uri\WhatWg\Url`. PHP 8.5 provides that class. On PHP 8.3 and 8.4, `league/uri-polyfill` provides it.

Laravel is optional. On Laravel 12 or 13, `illuminate/support` registers the service provider and the `RedirectAllow` facade.

## Usage

```php
use Progineous\RedirectAllow\Facades\RedirectAllow;

return RedirectAllow::to($request->string('next'))
    ->hosts([
        $request->host(),
        'accept.paymob.com',
    ])
    ->fallback(route('dashboard'))
    ->send();
```

`send()` uses the current request URL as the base for a target that is not a single-slash path, such as `dashboard` or `?tab=2`. Call `base()` with an absolute URL when the decision runs outside a request, or when you want a different base. A single-slash path does not use the base.

## Relative paths and listed hosts

Null, empty, and whitespace use the fallback.

A path that starts with a single slash, such as `/dashboard?tab=2#top`, is returned as a relative URL: the path, query, and fragment as the WHATWG parser normalized them. Dot segments are resolved and unsafe characters are percent-encoded, so `/a/../b` becomes `/b`. The browser resolves the result against the page it is on, so the `Host` header of the request cannot change where it points. A path that the parser would move to another host, such as `/\evil.example` or `/<tab>/evil.example`, is rejected. A path that normalizes to two leading slashes, such as `/..//evil.example`, is rejected too.

A protocol-relative value that starts with `//` is rejected, and the fallback is used.

An absolute URL must use https. Any other scheme is rejected. A URL with a username or password in the authority is rejected. The host must match the allowlist. A list entry is a full hostname, such as `accept.paymob.com`, or a suffix with a leading dot, such as `.example.com`. The dotted form allows subdomains of that host only. It does not allow the name itself, and it does not allow a different host that merely contains the same text. `example.com.evil.test` does not match `example.com`.

A Unicode hostname, in the target or in the list, is compared in its ASCII (punycode) form, so `m\u00FCnchen.de` and `xn--mnchen-3ya.de` are the same host. A lookalike host written in another script is a different host and is rejected.

A list entry must be a hostname. A URL such as `https://accept.paymob.com`, a host with a port, a path, userinfo, or a wildcard such as `*.example.com` throws an `InvalidArgumentException`, because it would never match.

The port is not checked. An allowed host is allowed on any port, for example `https://accept.paymob.com:8443/`.

Host comparison ignores letter case. One trailing dot, the DNS root, is ignored. Two trailing dots are rejected. A trailing dot does not make a host match a different site. When the target is allowed, the result is the parser's URL, not the raw input. When it is rejected, the result is the fallback. Bad input does not throw.

## The current host

A single-slash path never depends on the request. It stays a relative URL on whatever site served the page.

A target without a leading slash, such as `dashboard` or `../x`, is resolved against the base URL, and then it has to pass the host list like any absolute URL. In a request, that base comes from the `Host` header. If you list `$request->host()`, enable Laravel's `TrustHosts` middleware, or list your own hostname directly instead.

## Limits

This is not a WAF. It does not protect email links, `window.location`, or an OAuth provider `redirect_uri` registration. A host you add to the list is trusted by you.
