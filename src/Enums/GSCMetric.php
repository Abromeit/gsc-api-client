<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

enum GSCMetric: string
{
    case CLICKS = 'clicks';
    case IMPRESSIONS = 'impressions';
    case CTR = 'ctr';
    case POSITION = 'position';
    case DATE = 'date';
    case KEYS = 'keys';
    case COUNT = 'count'; // Custom metric introduced for internal aggregation calculations. Not part of the GSC API.
}
