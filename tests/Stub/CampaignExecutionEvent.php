<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Event;

/**
 * Lightweight stub of CampaignExecutionEvent for unit tests outside a full Mautic install.
 */
class CampaignExecutionEvent
{
    private mixed $lead = null;
    private bool $result = false;
    private ?\DateTimeInterface $deferredUntil = null;
    private string $context = '';

    public function setLead(object $lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    public function getLead(): object
    {
        return $this->lead;
    }

    public function setResult(bool $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getResult(): bool
    {
        return $this->result;
    }

    public function deferExecution(\DateTimeInterface $dateTime): self
    {
        $this->deferredUntil = $dateTime;

        return $this;
    }

    public function getDeferredUntil(): ?\DateTimeInterface
    {
        return $this->deferredUntil;
    }

    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function checkContext(string $context): bool
    {
        return $this->context === $context;
    }
}
