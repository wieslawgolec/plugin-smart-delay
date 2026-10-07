<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticSmartDelayBundle\EventListener\CampaignSubscriber;
use MauticPlugin\MauticSmartDelayBundle\Execution\SmartDelayExecutor;
use MauticPlugin\MauticSmartDelayBundle\Form\Type\SmartDelayType;
use PHPUnit\Framework\TestCase;

class CampaignSubscriberTest extends TestCase
{
    private CampaignSubscriber $subscriber;
    private SmartDelayExecutor $executor;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $result     = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn(false);
        $connection->method('executeQuery')->willReturn($result);

        $this->executor   = new SmartDelayExecutor($connection, '');
        $this->subscriber = new CampaignSubscriber($this->executor);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CampaignSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CampaignEvents::CAMPAIGN_ON_BUILD, $events);
        $this->assertArrayHasKey('mautic.smartdelay.on_campaign_trigger_action', $events);
    }

    public function testOnCampaignBuildRegistersAction(): void
    {
        $builder = new CampaignBuilderEvent();
        $this->subscriber->onCampaignBuild($builder);

        $actions = $builder->getActions();
        $this->assertArrayHasKey(CampaignSubscriber::ACTION_TYPE, $actions);

        $action = $actions[CampaignSubscriber::ACTION_TYPE];
        $this->assertSame('mautic.smartdelay.action.label', $action['label']);
        $this->assertSame('mautic.smartdelay.on_campaign_trigger_action', $action['eventName']);
        $this->assertSame(SmartDelayType::class, $action['formType']);
    }

    public function testOnCampaignTriggerActionIgnoresWrongContext(): void
    {
        $lead  = new Lead(1);
        $event = new CampaignExecutionEvent();
        $event->setLead($lead);
        $event->setContext('some.other.action');

        $this->subscriber->onCampaignTriggerAction($event);

        $this->assertFalse($event->getResult());
        $this->assertNull($event->getDeferredUntil());
    }

    public function testOnCampaignTriggerActionExecutesForCorrectContext(): void
    {
        $lead  = new Lead(7);
        $event = new CampaignExecutionEvent();
        $event->setLead($lead);
        $event->setContext(CampaignSubscriber::ACTION_TYPE);

        $this->subscriber->onCampaignTriggerAction($event);

        $this->assertTrue($event->getResult());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getDeferredUntil());
    }
}
