<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MauticSmartDelayBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
