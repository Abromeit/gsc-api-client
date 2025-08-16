<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

/**
 * Data State options for Google Search Console API.
 *
 * Controls whether to include fresh (incomplete) data or only final data.
 */
enum GSCDataState: string
{
    /**
     * Returns only final/complete data (default behavior).
     * This excludes any data that is still being collected or processed.
     */
    case FINAL = 'final';

    /**
     * Returns fresh data including incomplete/current data.
     * This includes data that is still being collected and processed.
     */
    case ALL = 'all';

    /**
     * Returns fresh data with hourly breakdown including incomplete data.
     * Should be used when grouping by the HOUR dimension.
     */
    case HOURLY_ALL = 'hourly_all';
}
