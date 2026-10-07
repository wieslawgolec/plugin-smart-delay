<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads the GrapesJS A/B subject-line block script into the email builder.
 */
class AssetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => ['injectAssets', 0],
        ];
    }

    public function injectAssets(CustomAssetsEvent $event): void
    {
        // Only needed in the admin UI where GrapesJS runs.
        $event->addScript('plugins/MauticSmartDelayBundle/Assets/js/grapesjs-ab-subject.js');
    }
}
