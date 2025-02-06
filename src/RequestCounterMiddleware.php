<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient;

use Psr\Http\Message\RequestInterface;

/**
 * Middleware to count actual API queries at HTTP client level.
 * Tracks total queries made and calculates request rates based on total runtime.
 * Handles both individual and batch requests, with special handling for search analytics queries.
 */
class RequestCounterMiddleware
{
    /**
     * @var float  - Start timestamp of the middleware with microsecond precision
     */
    private readonly float $startTime;

    /**
     * @var int  - Total queries counted
     */
    private int $totalQueries = 0;

    /**
     * Create a new instance of the middleware.
     *
     * @return callable  - The middleware instance
     */
    public static function create(): callable
    {
        return new self();
    }

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            if ($this->isBatchExecution($request)) {
                $queryCount = $this->countQueriesInBatch($request);
                $this->recordQueries($queryCount);
            }
            elseif ($this->isSearchAnalyticsRequest($request)) {
                $this->recordQueries(1);
            }

            $response = $handler($request, $options);

            if (is_object($response) && method_exists($response, 'then')) {
                return $response->then(
                    function ($value) {
                        return $value;
                    },
                    function ($reason) {
                        throw $reason;
                    }
                );
            }

            return $response;
        };
    }

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
     * @return int  - Number of queries in the batch
     */
    private function countQueriesInBatch(RequestInterface $request): int
    {
        return substr_count((string)$request->getBody(), '/searchAnalytics/query');
    }

    /**
     * Record multiple queries.
     *
     * @param  int $count  - Number of queries to record
     *
     * @return void
     */
    private function recordQueries(int $count): void
    {
        $this->totalQueries += $count;
    }

    /**
     * Get current requests per second.
     *
     * @return float  - Average requests per second
     */
    public function getRequestsPerSecond(): float
    {
        $runtime = max(0.001, microtime(true) - $this->startTime);
        return $this->totalQueries / $runtime;
    }

    /**
     * Get total requests made.
     *
     * @return int  - Total number of requests
     */
    public function getTotalRequests(): int
    {
        return $this->totalQueries;
    }

    /**
     * Get the start timestamp of the middleware.
     *
     * @return float  - Start timestamp with microsecond precision
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }
}
