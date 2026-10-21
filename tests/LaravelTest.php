<?php

declare(strict_types=1);

namespace Progineous\RedirectAllow\Tests;

use Illuminate\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;
use Progineous\RedirectAllow\RedirectAllow;
use Progineous\RedirectAllow\Rules\AllowedRedirect;

final class LaravelTest extends TestCase
{
    public function test_send_returns_a_redirect_to_the_decided_url(): void
    {
        $response = (new RedirectAllow())
            ->to('https://evil.example/phish')
            ->hosts(['accept.paymob.com'])
            ->fallback('https://app.example/home')
            ->base('https://app.example/login')
            ->send();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://app.example/home', $response->getTargetUrl());
    }

    public function test_validation_rule_fails_for_a_host_that_is_not_listed(): void
    {
        $this->assertSame(['next'], $this->failures(new AllowedRedirect(['accept.paymob.com'], 'https://app.example/'), 'https://evil.example/'));
    }

    public function test_validation_rule_passes_for_a_listed_host_and_a_relative_path(): void
    {
        $rule = new AllowedRedirect(['accept.paymob.com'], 'https://app.example/');

        $this->assertSame([], $this->failures($rule, 'https://accept.paymob.com/cb'));
        $this->assertSame([], $this->failures($rule, '/dashboard'));
    }

    public function test_validation_rule_fails_for_a_value_that_is_not_a_string(): void
    {
        $this->assertSame(['next'], $this->failures(new AllowedRedirect([], 'https://app.example/'), ['/dashboard']));
    }

    /**
     * @return list<string>
     */
    private function failures(AllowedRedirect $rule, mixed $value): array
    {
        $failed = [];
        $rule->validate('next', $value, function () use (&$failed): void {
            $failed[] = 'next';
        });

        return $failed;
    }
}
