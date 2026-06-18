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

namespace Alengo\SuluHttpCacheBundle\Cache;

use Alengo\SuluHttpCacheBundle\EventSubscriber\CtaInterestsSubscriber;
use Alengo\SuluHttpCacheBundle\EventSubscriber\CtaRefererSubscriber;
use Alengo\SuluHttpCacheBundle\EventSubscriber\StripQueryParametersSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Wires the bundle's subscribers onto the HTTP cache kernel's event dispatcher.
 *
 * Called from the project Kernel's getHttpCache(), which runs *before* the kernel is booted
 * — so the DI container is unavailable, and booting it would defeat the cache. The
 * configuration is therefore read straight from config/packages/alengo_http_cache.yaml at
 * runtime (a tiny file; the parse cost is negligible) rather than from container parameters.
 *
 * Note: only the base file and an optional "when@<env>" block within it are read. Cross-file
 * imports and %env()% placeholders are not resolved here — keep this bundle's config inline.
 */
final class CacheKernelConfigurator
{
    private const CONFIG_FILE = 'config/packages/alengo_http_cache.yaml';

    private const ROOT_KEY = 'alengo_http_cache';

    /**
     * @param EventDispatcherInterface $dispatcher the cache kernel's own dispatcher,
     *                                             e.g. SuluHttpCache::getEventDispatcher()
     */
    public function configure(EventDispatcherInterface $dispatcher, string $projectDir, string $env): void
    {
        $config = $this->readConfig($projectDir, $env);

        $strip = $this->section($config, 'strip_query_parameters');
        if ($this->flag($strip['enabled'] ?? null, true)) {
            $dispatcher->addSubscriber(new StripQueryParametersSubscriber(
                $this->stringList($strip['deny_list'] ?? null) ?? StripQueryParametersSubscriber::DEFAULT_DENY_LIST,
                $this->stringList($strip['allow_list'] ?? null) ?? [],
            ));
        }

        $interests = $this->section($config, 'cta_interests');
        if ($this->flag($interests['enabled'] ?? null, true)) {
            $dispatcher->addSubscriber(new CtaInterestsSubscriber(
                $this->string($interests['cookie_name'] ?? null, CtaInterestsSubscriber::DEFAULT_COOKIE_NAME),
                $this->stringList($interests['allow_list'] ?? null) ?? CtaInterestsSubscriber::DEFAULT_ALLOW_LIST,
            ));
        }

        $referer = $this->section($config, 'cta_referer');
        if ($this->flag($referer['enabled'] ?? null, true)) {
            $dispatcher->addSubscriber(new CtaRefererSubscriber(
                $this->string($referer['cookie_name'] ?? null, CtaRefererSubscriber::DEFAULT_COOKIE_NAME),
            ));
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readConfig(string $projectDir, string $env): array
    {
        $file = \rtrim($projectDir, '/') . '/' . self::CONFIG_FILE;

        if (!\is_file($file)) {
            return [];
        }

        $parsed = Yaml::parseFile($file);
        if (!\is_array($parsed)) {
            return [];
        }

        $config = $this->section($parsed, self::ROOT_KEY);

        // Merge an optional "when@<env>" override block (Symfony's env-scoped config syntax).
        $envBlock = $parsed['when@' . $env] ?? null;
        if (\is_array($envBlock)) {
            $config = $this->deepMerge($config, $this->section($envBlock, self::ROOT_KEY));
        }

        return $config;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function section(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        return \is_array($value) ? $value : [];
    }

    /**
     * Recursive merge where the override wins; list values are replaced wholesale.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     *
     * @return array<array-key, mixed>
     */
    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (\is_array($value) && !\array_is_list($value) && isset($base[$key]) && \is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function flag(mixed $value, bool $default): bool
    {
        return \is_bool($value) ? $value : $default;
    }

    private function string(mixed $value, string $default): string
    {
        return \is_string($value) && '' !== $value ? $value : $default;
    }

    /**
     * @return list<string>|null null when the value is not a usable list
     */
    private function stringList(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $list = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
