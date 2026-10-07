<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Event;

/**
 * Lightweight stub of CampaignBuilderEvent for unit tests.
 */
class CampaignBuilderEvent
{
    /** @var array<string, array<string, mixed>> */
    private array $actions = [];

    public function addAction(string $key, array $action): void
    {
        $this->actions[$key] = $action;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}
