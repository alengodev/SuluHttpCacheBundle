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

namespace Alengo\SuluHttpCacheBundle\Matcher;

/**
 * Compiles a list of parameter-name patterns (regex fragments authored in configuration) into
 * a single anchored, case-insensitive regex that matches a whole parameter name.
 *
 * Each pattern is validated in isolation and silently skipped when it is not a valid regular
 * expression (e.g. unbalanced groups, or a stray "/" that would break the delimiter), so a
 * single malformed entry in the config cannot take the whole matcher down or emit warnings.
 */
final class NamePatternMatcher
{
    /**
     * @param list<string> $patterns parameter-name patterns (whole-name, case-insensitive)
     *
     * @return non-empty-string|null null when no usable pattern remains
     */
    public static function compile(array $patterns): ?string
    {
        $valid = [];
        foreach ($patterns as $pattern) {
            // Validate the fragment on its own; "/" or malformed syntax drops only this entry.
            if (false !== @\preg_match('/(?:' . $pattern . ')/', '')) {
                $valid[] = $pattern;
            }
        }

        if ([] === $valid) {
            return null;
        }

        return '/^(?:' . \implode(')$|^(?:', $valid) . ')$/i';
    }
}
