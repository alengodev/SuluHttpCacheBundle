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
use Symfony\Component\HttpFoundation\Request;

/**
 * Removes tracking query parameters (utm_*, gclid, fbclid, …) from the request at the
 * reverse-proxy layer (PRE_HANDLE), before the cache key is computed. This collapses the
 * countless tracking-param permutations of a URL onto a single cache entry.
 *
 * The deny/allow lists are injected from configuration; see {@see self::DEFAULT_DENY_LIST}
 * for the built-in default. Matching is anchored and case-insensitive against the whole
 * parameter name. An empty deny list means "remove everything"; the allow list protects
 * matches from removal.
 */
final class StripQueryParametersSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public const DEFAULT_DENY_LIST = [
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
     * @param list<string> $denyList parameter-name patterns to remove
     * @param list<string> $allowList parameter-name patterns to keep even when denied
     */
    public function __construct(
        private readonly array $denyList = self::DEFAULT_DENY_LIST,
        private readonly array $allowList = [],
    ) {
    }

    public function preHandle(CacheEvent $event): void
    {
        $request = $event->getRequest();

        if (0 === $request->query->count()) {
            return;
        }

        $this->filterQueryParams($request);
    }

    private function filterQueryParams(Request $request): void
    {
        // Match against the whole param name (anchored, case-insensitive). An empty deny list
        // means "remove everything"; an empty allow list protects nothing. Invalid patterns are
        // dropped by the matcher, so a malformed config entry can never strip more than intended.
        $denyRegex = NamePatternMatcher::compile($this->denyList ?: ['.*']);
        if (null === $denyRegex) {
            return; // no usable deny pattern → nothing to strip
        }
        $allowRegex = NamePatternMatcher::compile($this->allowList);

        // String-cast the names — numeric names like "?1=x" would otherwise be int keys.
        foreach (\array_map(strval(...), \array_keys($request->query->all())) as $name) {
            if (1 !== \preg_match($denyRegex, $name)) {
                continue; // not on the deny list → keep
            }
            if (null !== $allowRegex && 1 === \preg_match($allowRegex, $name)) {
                continue; // explicitly allow-listed → keep
            }
            $request->query->remove($name);
        }

        $request->server->set('QUERY_STRING', \http_build_query($request->query->all()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::PRE_HANDLE => 'preHandle',
        ];
    }
}
