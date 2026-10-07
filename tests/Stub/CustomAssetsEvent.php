<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Event;

/**
 * Minimal stub of CustomAssetsEvent for unit tests.
 */
class CustomAssetsEvent
{
    /** @var list<string> */
    private array $scripts = [];

    public function addScript(string $path): void
    {
        $this->scripts[] = $path;
    }

    /**
     * @return list<string>
     */
    public function getScripts(): array
    {
        return $this->scripts;
    }
}
