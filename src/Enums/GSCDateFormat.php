<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

enum GSCDateFormat: string
{
    case DAILY = 'Y-m-d';
    case WEEKLY = 'Y-\C\WW';
    case MONTHLY = 'Y-m';
    case ALLOVER = 'allover';
}
