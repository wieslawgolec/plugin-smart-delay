<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\DependencyInjection;

/**
 * Minimal stub so Config/services.php can be parsed during unit tests
 * without a full Mautic installation.
 */
class MauticCoreExtension
{
    public const DEFAULT_EXCLUDES = [
        'DependencyInjection',
        'Entity',
        'Migrations',
        'Tests',
        'Views',
    ];
}
