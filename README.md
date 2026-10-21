# redirect-allow

## What it is

redirect-allow decides where a Laravel redirect may send the browser. You pass the target, a list of hosts, and a fallback. A path that starts with one slash, or an https URL whose host is on the list, is kept. Every other target is replaced with the fallback. The request `Host` header is never used.

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
);
```

```php
use Progineous\RedirectAllow\Rules\AllowedRedirect;

'next' => ['nullable', 'string', new AllowedRedirect(
    hosts: ['accept.paymob.com'],
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
    ->hosts(['accept.paymob.com'])
    ->fallback(route('dashboard'))
    ->send();
```

In Laravel, the host of `config('app.url')` is your own host. The facade and the validation rule add it to the list for you, so `https://app.example/settings` is allowed when `APP_URL=https://app.example`. Pass `appUrl:` to the rule to use a different URL. Outside Laravel, `Decider` and `new RedirectAllow()` only allow the hosts you list. Pass your own host to `new RedirectAllow('app.example')` if you want it.

The request URL and its `Host` header are not read anywhere. A request with a spoofed `Host` header gets the same decision as an honest one.

## Relative paths and listed hosts

Null, empty, and whitespace use the fallback.

A relative target that does not start with one slash, such as `dashboard`, `./x`, `../x`, `?a=1`, or `#frag`, uses the fallback. It is not resolved against any URL.

A path that starts with a single slash, such as `/dashboard?tab=2#top`, is returned as a relative URL: the path, query, and fragment as the WHATWG parser normalized them. Dot segments are resolved and unsafe characters are percent-encoded, so `/a/../b` becomes `/b`. The browser resolves the result against the page it is on, and nothing from the request changes it. A path that the parser would move to another host, such as `/\evil.example` or `/<tab>/evil.example`, is rejected. A path that normalizes to two leading slashes, such as `/..//evil.example`, is rejected too.

A protocol-relative value that starts with `//` is rejected, and the fallback is used.

An absolute URL must use https. Any other scheme is rejected. A URL with a username or password in the authority is rejected. The host must match the allowlist. A list entry is a full hostname, such as `accept.paymob.com`, or a suffix with a leading dot, such as `.example.com`. The dotted form allows subdomains of that host only. It does not allow the name itself, and it does not allow a different host that merely contains the same text. `example.com.evil.test` does not match `example.com`.

A Unicode hostname, in the target or in the list, is compared in its ASCII (punycode) form, so `m\u00FCnchen.de` and `xn--mnchen-3ya.de` are the same host. A lookalike host written in another script is a different host and is rejected.

A list entry must be a hostname. A URL such as `https://accept.paymob.com`, a host with a port, a path, userinfo, or a wildcard such as `*.example.com` throws an `InvalidArgumentException`, because it would never match.

The port is not checked. An allowed host is allowed on any port, for example `https://accept.paymob.com:8443/`.

Host comparison ignores letter case. One trailing dot, the DNS root, is ignored. Two trailing dots are rejected. A trailing dot does not make a host match a different site. When the target is allowed, the result is the parser's URL, not the raw input. When it is rejected, the result is the fallback. Bad input does not throw.

## Limits

This is not a WAF. It does not protect email links, `window.location`, or an OAuth provider `redirect_uri` registration. A host you add to the list is trusted by you.
