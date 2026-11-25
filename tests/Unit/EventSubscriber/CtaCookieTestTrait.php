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

use FOS\HttpCache\SymfonyCache\CacheEvent;
use FOS\HttpCache\SymfonyCache\CacheInvalidation;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait CtaCookieTestTrait
{
    /**
     * Runs a request through PRE_HANDLE and POST_HANDLE of the subscriber and returns the written cookie.
     */
    private function cookieOf(object $subscriber, Request $request): ?Cookie
    {
        $kernel = $this->createStub(CacheInvalidation::class);
        $response = new Response();

        \assert(\method_exists($subscriber, 'preHandle') && \method_exists($subscriber, 'postHandle'));
        $subscriber->preHandle(new CacheEvent($kernel, $request));
        $subscriber->postHandle(new CacheEvent($kernel, $request, $response));

        return $response->headers->getCookies()[0] ?? null;
    }
}
