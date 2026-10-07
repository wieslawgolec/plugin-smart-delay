<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Tests\Unit;

use MauticPlugin\MauticSmartDelayBundle\EventListener\AssetSubscriber;
use PHPUnit\Framework\TestCase;

class AssetSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = AssetSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('mautic.core_on_view_inject_custom_assets', $events);
        $this->assertSame(['injectAssets', 0], $events['mautic.core_on_view_inject_custom_assets']);
    }

    public function testSubscriberIsInstantiable(): void
    {
        $subscriber = new AssetSubscriber();
        $this->assertInstanceOf(AssetSubscriber::class, $subscriber);
    }
}
