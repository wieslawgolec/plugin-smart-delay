<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use MauticPlugin\MauticSmartDelayBundle\Execution\SmartDelayExecutor;
use MauticPlugin\MauticSmartDelayBundle\Form\Type\SmartDelayType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    public const ACTION_TYPE = 'smartdelay.execution';

    public function __construct(
        private readonly SmartDelayExecutor $executor,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            'mautic.smartdelay.on_campaign_trigger_action' => ['onCampaignTriggerAction', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $event->addAction(
            self::ACTION_TYPE,
            [
                'label'       => 'mautic.smartdelay.action.label',
                'description' => 'mautic.smartdelay.action.desc',
                'eventName'   => 'mautic.smartdelay.on_campaign_trigger_action',
                'formType'    => SmartDelayType::class,
                'formTheme'   => false,
            ]
        );
    }

    public function onCampaignTriggerAction(CampaignExecutionEvent $event): void
    {
        if (!$event->checkContext(self::ACTION_TYPE)) {
            return;
        }

        $this->executor->executeSmartDelay($event);
    }
}
