<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

// Stub MAUTIC_TABLE_PREFIX when running outside a full Mautic install
if (!defined('MAUTIC_TABLE_PREFIX')) {
    define('MAUTIC_TABLE_PREFIX', '');
}
