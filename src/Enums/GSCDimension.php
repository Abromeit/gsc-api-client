<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

enum GSCDimension: string
{
    case DATE = 'date';
    case QUERY = 'query';
    case PAGE = 'page';
    case COUNTRY = 'country';
    case DEVICE = 'device';
    case SEARCH_APPEARANCE = 'searchAppearance';
}
