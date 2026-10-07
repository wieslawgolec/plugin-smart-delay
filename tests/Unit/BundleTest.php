<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Tests\Unit;

use MauticPlugin\MauticSmartDelayBundle\MauticSmartDelayBundle;
use PHPUnit\Framework\TestCase;

class BundleTest extends TestCase
{
    public function testGetPathReturnsParentDirectory(): void
    {
        $bundle = new MauticSmartDelayBundle();
        $path   = $bundle->getPath();

        $this->assertDirectoryExists($path);
        $this->assertStringContainsString('plugin-smart-delay', $path);
    }

    public function testBundleNamespace(): void
    {
        $bundle = new MauticSmartDelayBundle();
        $this->assertSame(
            'MauticPlugin\\MauticSmartDelayBundle\\MauticSmartDelayBundle',
            $bundle::class
        );
    }
}
