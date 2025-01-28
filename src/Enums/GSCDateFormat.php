<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient\Enums;

enum GSCDateFormat: string
{
    case DAILY = 'Y-m-d';
    case WEEKLY = 'Y-\C\WW';
    case MONTHLY = 'Y-m';
    case ALLOVER = 'allover';
}
