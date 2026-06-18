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

namespace Alengo\SuluHttpCacheBundle\DependencyInjection;

use Alengo\SuluHttpCacheBundle\EventSubscriber\CtaInterestsSubscriber;
use Alengo\SuluHttpCacheBundle\EventSubscriber\CtaRefererSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads and validates the bundle configuration.
 *
 * The subscribers themselves are wired onto the cache kernel at runtime (see
 * {@see \Alengo\SuluHttpCacheBundle\Cache\CacheKernelConfigurator}), so this
 * extension only validates the config and exposes the resolved cookie names as container
 * parameters for container-side consumers (e.g. a form controller reading the cookie).
 */
class AlengoHttpCacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(
            'alengo_http_cache.cta_interests.cookie_name',
            $this->cookieName($config, 'cta_interests', CtaInterestsSubscriber::DEFAULT_COOKIE_NAME),
        );
        $container->setParameter(
            'alengo_http_cache.cta_referer.cookie_name',
            $this->cookieName($config, 'cta_referer', CtaRefererSubscriber::DEFAULT_COOKIE_NAME),
        );
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function cookieName(array $config, string $section, string $default): string
    {
        $sectionConfig = $config[$section] ?? null;

        if (\is_array($sectionConfig) && \is_string($sectionConfig['cookie_name'] ?? null)) {
            return $sectionConfig['cookie_name'];
        }

        return $default;
    }
}
