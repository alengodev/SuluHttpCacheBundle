# alengoHttpCacheBundle

HTTP-cache helpers for Sulu / Symfony, wired onto the FOS `EventDispatchingHttpCache`
(reverse-proxy) kernel:

- **StripQueryParametersSubscriber** — removes tracking query parameters (`utm_*`, `gclid`,
  `fbclid`, …) at `PRE_HANDLE`, before the cache key is computed, so tracking-param URL
  permutations collapse onto a single cache entry.
- **CtaInterestsSubscriber** — stores matched tracking parameters in a cookie (default
  `cta_interests`) for marketing attribution.
- **CtaRefererSubscriber** — stores an external referer in a cookie (default `cta_referer`).

The CTA cookies are written in `POST_HANDLE` (after the store decision) so they are set per
response and never baked into a cached page.

## Installation

```bash
composer require alengo/sulu-http-cache-bundle
```

Register the bundle (Symfony Flex does this automatically):

```php
// config/bundles.php
Alengo\SuluHttpCacheBundle\AlengoHttpCacheBundle::class => ['all' => true],
```

## Wiring

The subscribers run on the reverse-proxy cache kernel, which is created in the front
controller / `Kernel::getHttpCache()` *before* the DI container is available. Wire them with
the provided configurator:

```php
use Alengo\SuluHttpCacheBundle\Cache\CacheKernelConfigurator;

public function getHttpCache(): HttpKernelInterface
{
    if (!$this->httpCache instanceof HttpKernelInterface) {
        $cache = new SuluHttpCache($this);

        (new CacheKernelConfigurator())->configure(
            $cache->getEventDispatcher(),
            $this->getProjectDir(),
            $this->getEnvironment(),
        );

        $this->httpCache = $cache;
    }

    return $this->httpCache;
}
```

## Configuration

All settings live in `config/packages/alengo_http_cache.yaml`. The configurator reads this
file directly at runtime (no container), so keep the values inline — cross-file imports and
`%env()%` placeholders are not resolved. Defaults are applied for anything omitted.

```yaml
alengo_http_cache:
    strip_query_parameters:
        enabled: true
        # deny_list: ['utm_[a-z]+', 'gclid', ...]   # defaults to the built-in tracking list
        # allow_list: []
    cta_interests:
        enabled: true
        cookie_name: cta_interests
        # allow_list: [...]                          # defaults to the built-in tracking list
    cta_referer:
        enabled: true
        cookie_name: cta_referer
```

Each subscriber can be switched off with `enabled: false`. The resolved cookie names are also
exposed as the container parameters `alengo_http_cache.cta_interests.cookie_name` and
`alengo_http_cache.cta_referer.cookie_name` for container-side consumers (e.g. a form
controller that reads the cookie).
