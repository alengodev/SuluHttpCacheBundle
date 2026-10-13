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

namespace Alengo\SuluHttpCacheBundle\EventSubscriber;

use FOS\HttpCache\SymfonyCache\CacheEvent;
use FOS\HttpCache\SymfonyCache\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores an external referer in a cookie (default "cta_referer") for marketing attribution.
 * The cookie name is injected from configuration.
 *
 * Wired onto the HTTP cache kernel. The cookie is written in POST_HANDLE — i.e. *after* the
 * reverse-proxy store decision — so it is set per response and never baked into a cached page
 * (which would leak one visitor's referer to the next). It therefore runs only behind the
 * caching proxy, not on a bare dev kernel.
 */
final class CtaRefererSubscriber implements EventSubscriberInterface
{
    public const DEFAULT_COOKIE_NAME = 'cta_referer';

    private const LIFETIME = 3600;

    public function __construct(
        private readonly string $cookieName = self::DEFAULT_COOKIE_NAME,
    ) {
    }

    public function preHandle(CacheEvent $event): void
    {
        $this->captureReferer($event->getRequest());
    }

    public function postHandle(CacheEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response instanceof Response) {
            return;
        }

        $this->writeCookie($event->getRequest(), $response);
    }

    private function captureReferer(Request $request): void
    {
        $referer = $request->headers->get('referer');

        if (null !== $referer && '' !== $referer && $this->isExternalReferer($request, $referer)) {
            $request->attributes->set('referer', $referer);
        }
    }

    private function writeCookie(Request $request, Response $response): void
    {
        $referer = $request->attributes->get('referer');

        if (!\is_string($referer) || '' === $referer) {
            return;
        }

        $response->headers->setCookie(
            Cookie::create($this->cookieName, $referer, \time() + self::LIFETIME),
        );
    }

    private function isExternalReferer(Request $request, string $referer): bool
    {
        // External = the referer's host differs from our own. Compare parsed hosts rather
        // than a substring match, which would miss lookalike domains ("notexample.com"
        // contains "example.com") and be fooled by subdomains ("example.com.attacker.test").
        $refererHost = \parse_url($referer, \PHP_URL_HOST);

        return \is_string($refererHost) && 0 !== \strcasecmp($refererHost, $request->getHost());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::PRE_HANDLE => 'preHandle',
            Events::POST_HANDLE => 'postHandle',
        ];
    }
}
