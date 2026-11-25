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

use Alengo\SuluHttpCacheBundle\EventSubscriber\CtaInterestsSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Anyone can send a visitor a link with tracking parameters. Their values end up in a cookie that is sent with every
 * later request (and stored with form submissions): without limits a crafted link could push the Cookie header past
 * the web server's limit, and the visitor would get "400 Bad Request" on every page for the cookie's lifetime.
 */
#[CoversClass(CtaInterestsSubscriber::class)]
final class CtaInterestsSubscriberTest extends TestCase
{
    use CtaCookieTestTrait;

    public function testStoresMatchedTrackingParameters(): void
    {
        $cookie = $this->cookieOf(new CtaInterestsSubscriber(), Request::create('/de?utm_source=newsletter&utm_campaign=herbst&page=2'));

        self::assertNotNull($cookie);
        self::assertSame('cta_interests', $cookie->getName());
        self::assertSame(['utm_source' => 'newsletter', 'utm_campaign' => 'herbst'], \json_decode((string) $cookie->getValue(), true));
    }

    public function testWritesNothingWithoutTrackingParameters(): void
    {
        self::assertNull($this->cookieOf(new CtaInterestsSubscriber(), Request::create('/de?page=2')));
    }

    public function testLongValuesAreShortened(): void
    {
        $cookie = $this->cookieOf(new CtaInterestsSubscriber(), Request::create('/de?utm_source=' . \str_repeat('a', 4000)));

        self::assertNotNull($cookie);
        $interests = \json_decode((string) $cookie->getValue(), true);
        self::assertIsArray($interests);
        self::assertIsString($interests['utm_source']);
        self::assertSame(CtaInterestsSubscriber::MAX_VALUE_LENGTH, \mb_strlen($interests['utm_source']));
    }

    public function testArrayValuesAreIgnored(): void
    {
        $cookie = $this->cookieOf(new CtaInterestsSubscriber(), Request::create('/de?utm_source[a][b][c]=x&utm_medium=mail'));

        self::assertNotNull($cookie);
        self::assertSame(['utm_medium' => 'mail'], \json_decode((string) $cookie->getValue(), true));
    }

    public function testCookieStaysSmallWithManyParameters(): void
    {
        $query = [];
        foreach (\range(1, 60) as $i) {
            $query['utm_' . \str_repeat(\chr(96 + ($i % 26) + 1), 1 + \intdiv($i, 26))] = \str_repeat('x', 150);
        }

        $cookie = $this->cookieOf(new CtaInterestsSubscriber(), Request::create('/de?' . \http_build_query($query)));

        self::assertNotNull($cookie);
        self::assertLessThanOrEqual(CtaInterestsSubscriber::MAX_COOKIE_LENGTH, \strlen((string) $cookie->getValue()));
        self::assertIsArray(\json_decode((string) $cookie->getValue(), true));
    }
}
