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

`send()` uses the current request URL as the base for a relative path. Call `base()` with an absolute URL when the decision runs outside a request, or when you want a different base.

## Relative paths and listed hosts

Null, empty, and whitespace use the fallback.

A path that starts with a single slash, such as `/dashboard`, is resolved against the current absolute URL. It is allowed when that resolution stays on the current host and the result is https. The value used is the URL the WHATWG parser produced.

A protocol-relative value that starts with `//` is rejected, and the fallback is used.

An absolute URL must use https. Any other scheme is rejected. A URL with a username or password in the authority is rejected. The host must match the allowlist. A list entry is a full hostname, such as `accept.paymob.com`, or a suffix with a leading dot, such as `.example.com`. The dotted form allows subdomains of that host only. It does not allow the name itself, and it does not allow a different host that merely contains the same text. `example.com.evil.test` does not match `example.com`.

Host comparison ignores letter case. A trailing dot does not make a host match a different site. When the target is allowed, the result is the parser's URL, not the raw input. When it is rejected, the result is the fallback. Bad input does not throw.

## Limits

This is not a WAF. It does not protect email links, `window.location`, or an OAuth provider `redirect_uri` registration. A host you add to the list is trusted by you.
