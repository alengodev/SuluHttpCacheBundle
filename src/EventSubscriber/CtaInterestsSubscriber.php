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

use Alengo\SuluHttpCacheBundle\Matcher\NamePatternMatcher;
use FOS\HttpCache\SymfonyCache\CacheEvent;
use FOS\HttpCache\SymfonyCache\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores matched tracking query parameters in a cookie (default "cta_interests") for
 * marketing attribution. The cookie name and the matched-parameter allow list are injected
 * from configuration.
 *
 * Wired onto the HTTP cache kernel. Two things matter:
 *  - The cookie is written in POST_HANDLE (after the reverse-proxy store decision) so it is
 *    set per response and never baked into a cached page. It therefore runs only behind the proxy.
 *  - PRE_HANDLE runs at a higher priority than {@see StripQueryParametersSubscriber} so the
 *    tracking params are read *before* that subscriber strips them from the request.
 */
final class CtaInterestsSubscriber implements EventSubscriberInterface
{
    public const DEFAULT_COOKIE_NAME = 'cta_interests';

    private const LIFETIME = 3600;

    /** @var list<string> */
    public const DEFAULT_ALLOW_LIST = [
        // Matomo
        'mtm_[a-z]+',
        // Piwik
        'pk_campaign',
        'pk_kwd',
        '_stg_debug',
        // Google
        'utm_[a-z]+',
        'gtm_debug',
        '_gl',
        // Google ads
        'gad_[a-z]+',
        'gclid',
        'gclsrc',
        'dclid',
        // Google iOS
        'gbraid',
        'wbraid',
        // Facebook
        'fbclid',
        // Microsoft Bing Ads.
        'msclkid',
        // HubSpot Ad Tracking Parameters
        'hsa_[a-z]+',
        // HubSpot Email Tracking Parameters
        '_hsenc',
        '_hsmi',
        // HubSpot CTA Tracking Parameters
        '__hssc',
        '__hstc',
        '__hsfp',
        'hsCtaTracking',
        // HubSpot Form Tracking Parameters
        'submissionGuid',
        // Pinterest
        'epik',
        // ActiveCampaign
        'vgo_[a-z]+',
        // Zen Cart
        'zenid',
        // ...
        'wraid',
        'zanpid',
    ];

    /**
     * @param list<string> $allowList parameter-name patterns whose presence triggers the cookie
     */
    public function __construct(
        private readonly string $cookieName = self::DEFAULT_COOKIE_NAME,
        private readonly array $allowList = self::DEFAULT_ALLOW_LIST,
    ) {
    }

    public function preHandle(CacheEvent $event): void
    {
        $this->captureInterests($event->getRequest());
    }

    public function postHandle(CacheEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response instanceof Response) {
            return;
        }

        $this->writeCookie($event->getRequest(), $response);
    }

    private function captureInterests(Request $request): void
    {
        $params = $request->query->all();

        if ([] === $params || [] === $this->allowList) {
            return;
        }

        $allowRegex = NamePatternMatcher::compile($this->allowList);
        if (null === $allowRegex) {
            return;
        }

        // Keep only the matched tracking parameters, not the whole query string — otherwise an
        // unbounded set of arbitrary extra params would be stored in (and could overflow) the cookie.
        $interests = [];
        foreach ($params as $key => $value) {
            $name = (string) $key;
            if (1 === \preg_match($allowRegex, $name)) {
                $interests[$name] = $value;
            }
        }

        if ([] !== $interests) {
            $request->attributes->set('interests', $interests);
        }
    }

    private function writeCookie(Request $request, Response $response): void
    {
        $interests = $request->attributes->get('interests');

        if (!\is_array($interests) || [] === $interests) {
            return;
        }

        $response->headers->setCookie(
            Cookie::create(
                $this->cookieName,
                \json_encode($interests, \JSON_THROW_ON_ERROR),
                \time() + self::LIFETIME,
            ),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Higher priority than StripQueryParametersSubscriber (0): read the tracking
            // params before they are stripped from the request.
            Events::PRE_HANDLE => ['preHandle', 100],
            Events::POST_HANDLE => 'postHandle',
        ];
    }
}
