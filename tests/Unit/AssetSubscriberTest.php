<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Tests\Unit;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use MauticPlugin\MauticSmartDelayBundle\EventListener\AssetSubscriber;
use PHPUnit\Framework\TestCase;

class AssetSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = AssetSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_ASSETS, $events);
        $this->assertSame(['injectAssets', 0], $events[CoreEvents::VIEW_INJECT_CUSTOM_ASSETS]);
    }

    public function testInjectAssetsAddsGrapesJsScript(): void
    {
        $subscriber = new AssetSubscriber();
        $event      = new CustomAssetsEvent();

        $subscriber->injectAssets($event);

        $scripts = $event->getScripts();
        $this->assertCount(1, $scripts);
        $this->assertStringContainsString('grapesjs-ab-subject.js', $scripts[0]);
    }
}
