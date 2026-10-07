<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Entity;

/**
 * Minimal Lead stub exposing getId().
 */
class Lead
{
    public function __construct(
        private readonly int $id,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
