<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient;

use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;


/**
 * Middleware that retries requests with exponential backoff.
 */
class RetryMiddleware {

    private const QUOTA_EXCEEDED_REASON = 'quotaExceeded';
    private const QUOTA_EXCEEDED_DOMAIN = 'usageLimits';


    /**
     * Create retry middleware with exponential backoff.
     *
     * @param  int   $maxRetries     - Maximum number of retries
     * @param  int   $initialDelay   - Initial delay in milliseconds
     * @param  float $backoffFactor  - Multiplier for each subsequent retry
     * @param  int   $quotaDelay     - Additional delay in milliseconds for quota errors
     *
     * @return callable  - The middleware
     */
    public static function create(
        int $maxRetries = 3,
        int $initialDelay = 1000,
        float $backoffFactor = 2.0,
        int $quotaDelay = 60000
    ): callable {
        return Middleware::retry(
            self::createDecider($maxRetries),
            self::createDelay($initialDelay, $backoffFactor, $quotaDelay)
        );
    }


    /**
     * Create the retry decision callback.
     *
     * @param  int $maxRetries  - Maximum number of retries
     *
     * @return callable  - Function that decides whether to retry
     */
    private static function createDecider(int $maxRetries): callable {
        return function(
            int $retries,
            Request $request,
            Response $response = null,
            \Exception $exception = null
        ) use ($maxRetries): bool {
            // Don't retry if we've hit the max
            if ($retries >= $maxRetries) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                return true;
            }

            // Check for quota exceeded
            if ($response && self::isQuotaExceeded($response)) {
                error_log(sprintf(
                    'Quota exceeded. Will retry in %d seconds. Retry attempt: %d/%d',
                    64,
                    $retries + 1,
                    $maxRetries
                ));
                return true;
            }

            // Retry on server errors (5xx) and rate limits (429)
            if ($response && $response->getStatusCode() >= 500) {
                return true;
            }

            if ($response && $response->getStatusCode() === 429) {
                return true;
            }

            return false;
        };
    }


    /**
     * Create the delay calculator callback.
     *
     * @param  int   $initialDelay   - Initial delay in milliseconds
     * @param  float $backoffFactor  - Multiplier for each subsequent retry
     * @param  int   $quotaDelay     - Additional delay for quota errors
     *
     * @return callable  - Function that calculates delay duration
     */
    private static function createDelay(
        int $initialDelay,
        float $backoffFactor,
        int $quotaDelay
    ): callable {
        return function(
            int $retries,
            Response $response = null
        ) use ($initialDelay, $backoffFactor, $quotaDelay): int {
            // Use longer delay for quota exceeded
            if ($response && self::isQuotaExceeded($response)) {
                return $quotaDelay;
            }

            return (int)($initialDelay * pow($backoffFactor, $retries - 1));
        };
    }


    /**
     * Check if response indicates a quota exceeded error.
     *
     * @param  ResponseInterface $response  - The response to check
     *
     * @return bool  - True if quota exceeded
     */
    private static function isQuotaExceeded(ResponseInterface $response): bool {
        if ($response->getStatusCode() !== 403) {
            return false;
        }

        try {
            $body = json_decode((string)$response->getBody(), true);

            if (!isset($body['error']['errors'][0])) {
                return false;
            }

            $error = $body['error']['errors'][0];

            return $error['domain'] === self::QUOTA_EXCEEDED_DOMAIN
                && $error['reason'] === self::QUOTA_EXCEEDED_REASON;

        } catch (\Throwable $e) {
            return false;
        }
    }

}
