<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

enum GSCAggregationType: string
{
    case AUTO = 'auto';
    case BY_PAGE = 'byPage';
    case BY_PROPERTY = 'byProperty';
    case BY_NEWS_SHOWCASE_PANEL = 'byNewsShowcasePanel';
}
