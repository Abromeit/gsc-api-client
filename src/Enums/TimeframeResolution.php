<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

enum TimeframeResolution: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case ALLOVER = 'allover';
}
