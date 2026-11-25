<?php

declare(strict_types=1);

/*
 * This file is part of Alengo\SuluHttpCacheBundle.
 *
 * (c) alengo
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Alengo\SuluHttpCacheBundle\Tests\Unit\EventSubscriber;

use Alengo\SuluHttpCacheBundle\EventSubscriber\CtaRefererSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CtaRefererSubscriber::class)]
final class CtaRefererSubscriberTest extends TestCase
{
    use CtaCookieTestTrait;

    public function testStoresAnExternalReferer(): void
    {
        $cookie = $this->cookieOf(new CtaRefererSubscriber(), $this->request('https://partner.example/holzbau/'));

        self::assertSame('https://partner.example/holzbau/', $cookie?->getValue());
    }

    public function testIgnoresOwnPages(): void
    {
        self::assertNull($this->cookieOf(new CtaRefererSubscriber(), $this->request('https://www.hasslacher.test/de/produkte')));
    }

    /**
     * The query of a foreign page may hold search terms or tokens of that site; the value is stored with form
     * submissions and mailed. The page itself is what attribution needs.
     */
    public function testDropsQueryAndFragment(): void
    {
        $cookie = $this->cookieOf(new CtaRefererSubscriber(), $this->request('https://partner.example/suche?q=max+mustermann&token=abc#top'));

        self::assertSame('https://partner.example/suche', $cookie?->getValue());
    }

    public function testLongReferersAreShortened(): void
    {
        $cookie = $this->cookieOf(new CtaRefererSubscriber(), $this->request('https://partner.example/' . \str_repeat('a', 4000)));

        self::assertNotNull($cookie);
        self::assertSame(CtaRefererSubscriber::MAX_LENGTH, \strlen((string) $cookie->getValue()));
    }

    private function request(string $referer): Request
    {
        return Request::create('https://www.hasslacher.test/de', server: ['HTTP_REFERER' => $referer]);
    }
}
