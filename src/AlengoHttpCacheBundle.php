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

namespace Alengo\SuluHttpCacheBundle;

use Alengo\SuluHttpCacheBundle\DependencyInjection\AlengoHttpCacheExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AlengoHttpCacheBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new AlengoHttpCacheExtension();
    }
}
