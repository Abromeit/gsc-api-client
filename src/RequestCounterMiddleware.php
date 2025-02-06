<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient;

use Psr\Http\Message\RequestInterface;
use Abromeit\GscApiClient\Traits\SearchAnalyticsQueryCounter;

/**
 * Middleware to count actual API queries at HTTP client level.
 * Tracks total queries made and calculates request rates based on total runtime.
 * Handles both individual and batch requests, with special handling for search analytics queries.
 */
class RequestCounterMiddleware
{
    use SearchAnalyticsQueryCounter;

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
                $queryCount = (int)$this->countQueriesInBatch($request);
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
