<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient;

use Psr\Http\Message\RequestInterface;
use Abromeit\GscApiClient\Traits\SearchAnalyticsQueryCounter;

/**
 * Middleware that implements a token bucket algorithm to ensure we stay under API rate limits.
 * Uses a target rate of 18 requests per second (90% of Google's 20 QPS limit) for safety.
 *
 * The bucket size is set to 900 tokens (50 seconds worth of requests at target QPS).
 * This allows for efficient batch processing while maintaining a safe margin below
 * Google's quota of 1,200 requests per minute.
 */
class ThrottlingMiddleware
{
    use SearchAnalyticsQueryCounter;

    /**
     * Target requests per second (18 equals 90% of Google's 20 QPS limit).
     */
    private const TARGET_QPS = 18.0;

    /**
     * Maximum bucket size (number of tokens that can be accumulated).
     * Set to 900 (50 seconds worth of requests at target QPS).
     * This allows for efficient batch processing while staying under
     * Google's quota of 1,200 requests per minute.
     */
    private const MAX_BUCKET_SIZE = 900.0;

    /**
     * @var float  - Current number of tokens in the bucket
     */
    private float $tokens;

    /**
     * @var float  - Last token refresh timestamp with microsecond precision
     */
    private float $lastRefreshTime;

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
        $this->tokens = self::MAX_BUCKET_SIZE;
        $this->lastRefreshTime = microtime(true);
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $this->refreshTokens();
            $this->waitForTokens($this->getRequiredTokens($request));

            return $handler($request, $options);
        };
    }

    /**
     * Refresh tokens based on time elapsed since last refresh.
     *
     * @return void
     */
    private function refreshTokens(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefreshTime;
        $this->lastRefreshTime = $now;

        // Add tokens based on elapsed time and target rate
        $this->tokens = min(
            self::MAX_BUCKET_SIZE,
            $this->tokens + ($elapsed * self::TARGET_QPS)
        );
    }

    /**
     * Wait until enough tokens are available.
     *
     * @param  float $requiredTokens  - Number of tokens needed
     *
     * @return void
     */
    private function waitForTokens(float $requiredTokens): void
    {
        if ($requiredTokens <= $this->tokens) {
            $this->tokens -= $requiredTokens;
            return;
        }

        // Calculate how long we need to wait
        $tokensNeeded = $requiredTokens - $this->tokens;
        $waitTime = ($tokensNeeded / self::TARGET_QPS) * 1_000_000; // Convert to microseconds

        usleep((int)$waitTime);
        $this->tokens = 0;
    }

    /**
     * Get number of tokens required for this request.
     *
     * @param  RequestInterface $request  - The request to check
     *
     * @return float  - Number of tokens needed
     */
    private function getRequiredTokens(RequestInterface $request): float
    {
        // For batch requests, count the number of searchAnalytics queries
        if ($this->isBatchExecution($request)) {
            return $this->countQueriesInBatch($request);
        }

        // For search analytics requests, use 1 token
        if ($this->isSearchAnalyticsRequest($request)) {
            return 1.0;
        }

        // Other requests don't count towards the quota
        return 0.0;
    }
}
