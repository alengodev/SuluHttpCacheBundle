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
use Alengo\SuluHttpCacheBundle\EventSubscriber\StripQueryParametersSubscriber;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Validates and merges the bundle configuration. The same structure is read at runtime by
 * {@see \Alengo\SuluHttpCacheBundle\Cache\CacheKernelConfigurator} to wire the
 * subscribers onto the cache kernel (where the container is not available).
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('alengo_http_cache');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('strip_query_parameters')
                    ->info('Strips tracking query parameters at the proxy layer to improve the cache hit rate.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->arrayNode('deny_list')
                            ->info('Parameter-name patterns to remove (anchored, case-insensitive).')
                            ->scalarPrototype()->end()
                            ->defaultValue(StripQueryParametersSubscriber::DEFAULT_DENY_LIST)
                        ->end()
                        ->arrayNode('allow_list')
                            ->info('Parameter-name patterns to keep even when matched by the deny list.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cta_interests')
                    ->info('Stores matched tracking parameters in a cookie for marketing attribution.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('cookie_name')
                            ->cannotBeEmpty()
                            ->defaultValue(CtaInterestsSubscriber::DEFAULT_COOKIE_NAME)
                        ->end()
                        ->arrayNode('allow_list')
                            ->info('Parameter-name patterns whose presence triggers the cookie.')
                            ->scalarPrototype()->end()
                            ->defaultValue(CtaInterestsSubscriber::DEFAULT_ALLOW_LIST)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cta_referer')
                    ->info('Stores an external referer in a cookie for marketing attribution.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('cookie_name')
                            ->cannotBeEmpty()
                            ->defaultValue(CtaRefererSubscriber::DEFAULT_COOKIE_NAME)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
