<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow;

use Illuminate\Container\Container;
use Throwable;
use Uri\WhatWg\Url;

/**
 * The application's own host, read from config('app.url').
 *
 * The request Host header is never used.
 *
 * @internal
 */
final class AppHost
{
    public static function fromUrl(?string $appUrl): ?string
    {
        if ($appUrl === null || trim($appUrl) === '') {
            return null;
        }

        try {
            $errors = [];
            $url = Url::parse(trim($appUrl), null, $errors);
        } catch (Throwable) {
            return null;
        }

        if (!$url instanceof Url) {
            return null;
        }

        $host = $url->getAsciiHost();

        return is_string($host) && $host !== '' ? $host : null;
    }

    public static function fromConfig(): ?string
    {
        if (!class_exists(Container::class)) {
            return null;
        }

        try {
            $container = Container::getInstance();
            if (!$container->bound('config')) {
                return null;
            }
            $value = $container->make('config')->get('app.url');
        } catch (Throwable) {
            return null;
        }

        return is_string($value) ? self::fromUrl($value) : null;
    }
}
