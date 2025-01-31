<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

enum GSCDeviceType: string
{
    case DESKTOP = 'DESKTOP';
    case MOBILE = 'MOBILE';
    case TABLET = 'TABLET';
}
