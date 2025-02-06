<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Traits;

use Psr\Http\Message\RequestInterface;


/**
 * Trait for counting search analytics queries in requests.
 */
trait SearchAnalyticsQueryCounter
{

    /**
     * Check if this is a batch execution request.
     *
     * @param  RequestInterface $request  - The request to check
     *
     * @return bool  - True if this is a batch execution
     */
    private function isBatchExecution(RequestInterface $request): bool
    {
        return $request->getMethod() === 'POST' && (
            str_contains($request->getUri()->getPath(), '/batch') ||
            str_contains($request->getHeaderLine('Content-Type'), 'multipart/mixed')
        );
    }


    /**
     * Check if this is a search analytics request.
     *
     * @param  RequestInterface $request  - The request to check
     *
     * @return bool  - True if this is a search analytics request
     */
    private function isSearchAnalyticsRequest(RequestInterface $request): bool
    {
        return str_contains($request->getUri()->getPath(), '/searchAnalytics/query');
    }


    /**
     * Count the number of queries in a batch request.
     *
     * @param  RequestInterface $request  - The batch request
     *
     * @return float  - Number of queries in the batch
     */
    private function countQueriesInBatch(RequestInterface $request): float
    {
        return (float)substr_count((string)$request->getBody(), '/searchAnalytics/query');
    }
}
