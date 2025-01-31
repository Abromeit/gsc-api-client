<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient\Enums;

enum GSCDeviceType: string
{
    case DESKTOP = 'DESKTOP';
    case MOBILE = 'MOBILE';
    case TABLET = 'TABLET';
}
