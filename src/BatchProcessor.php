<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryResponse;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Psr\Log\LoggerInterface;

/**
 * Handles batch processing of Google Search Console API requests.
 */
class BatchProcessor
{
    /**
     * Maximum number of requests to batch together.
     * This is a Google API limitation.
     *
     * @see https://developers.google.com/webmaster-tools/v1/how-tos/batch?hl=en#:~:text=You%27re%20limited%20to%201000%20calls%20in%20a%20single%20batch%20request
     * @see https://developers.google.com/webmaster-tools/limits?hl=en#qps-quota
     */
    private const MAX_BATCH_SIZE = 1000;

    /**
     * Default number of requests to batch together.
     */
    private const DEFAULT_BATCH_SIZE = 10;

    /**
     * Maximum number of retries allowed for a single–item request.
     */
    private const MAX_SINGLE_RETRIES = 3;

    /**
     * Base delay in seconds for retry attempts.
     */
    private const RETRY_DELAY_SECONDS = 60;

    private readonly Client $client;
    private readonly SearchConsole $searchConsole;
    private int $batchSize;

    /**
     * Optional PSR‑3 logger. If not set, falls back to error_log().
     */
    private ?LoggerInterface $logger = null;

    public function __construct(
        Client $client,
        ?int $batchSize = null
    ) {
        // Create a clone of the client to enable batch mode without side effects.
        $this->client = clone $client;
        $this->client->setUseBatch(true);
        $this->searchConsole = new SearchConsole($this->client);

        $this->setBatchSize($batchSize ?? self::DEFAULT_BATCH_SIZE);
    }

    /**
     * Optionally set a PSR‑3 logger.
     *
     * @param LoggerInterface $logger
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Log an error message using the provided logger or fallback to error_log.
     *
     * @param string $message
     * @return void
     */
    private function logError(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message);
        } else {
            error_log($message);
        }
    }

    /**
     * Set the number of requests to batch together.
     *
     * @param  int $batchSize  - Number of requests to batch (1-1000)
     *
     * @return self
     */
    public function setBatchSize(int $batchSize): self
    {
        if ($batchSize < 1) {
            $batchSize = 1;
        }

        if ($batchSize > self::MAX_BATCH_SIZE) {
            $batchSize = self::MAX_BATCH_SIZE;
        }

        $this->batchSize = $batchSize;

        return $this;
    }

    /**
     * Get the current batch size setting.
     *
     * @return int Current batch size
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Process items in batches using the Google API batch functionality.
     * This single recursive method replaces the previous processInBatches and processBatchedItems methods.
     *
     * @param  array<mixed>         $items            - Items to process
     * @param  callable|array       $requestBuilder   - Function to build request for each item.
     *                                                  Signature: fn($item) => [$request, $requestId]
     * @param  callable|array       $responseHandler  - Function to handle each response.
     *                                                  Signature: fn($response, $requestId) => mixed
     * @param  callable|array|null  $errorHandler     - Optional function to handle errors.
     *                                                  Signature: fn(\Exception $e, $item) => void.
     *                                                  If null, uses default error logging.
     * @param  int|null             $currentBatchSize - (Internal) Batch size to use. Defaults to instance batchSize.
     * @param  int                  $retryAttempts    - (Internal) Number of retry attempts for single–item failures.
     *
     * @return \Generator<mixed>  - Generator of processed results
     */
    public function processInBatches(
        array $items,
        callable|array $requestBuilder,
        callable|array $responseHandler,
        callable|array|null $errorHandler = null,
        ?int $currentBatchSize = null,
        int $retryAttempts = 0
    ): \Generator {
        $errorHandler   = $errorHandler ?? [$this, 'defaultErrorHandler'];
        $currentBatchSize = $currentBatchSize ?? $this->batchSize;
        $chunks = array_chunk($items, $currentBatchSize);

        foreach ($chunks as $chunkIndex => $chunkItems) {
            // Build the batch and mapping.
            [$batch, $requestMap] = $this->buildBatch($chunkItems, $requestBuilder, $errorHandler);
            if (!$batch) {
                continue;
            }

            // Execute the batch.
            try {
                $responses = $batch->execute();
            } catch (\Exception $e) {
                $errorHandler(
                    new \RuntimeException(
                        "Batch execution failed for chunk {$chunkIndex}: " . $e->getMessage(),
                        $e->getCode(),
                        $e
                    ),
                    $chunkItems
                );
                continue;
            }

            if (!is_array($responses)) {
                continue;
            }

            // Process responses and collect results and failures.
            $resultData = $this->processResponsesAndCollectFailures($responses, $requestMap, $responseHandler, $errorHandler);
            foreach ($resultData['results'] as $result) {
                yield $result;
            }

            $failedItems = $resultData['failed'];
            if (empty($failedItems)) {
                continue;
            }

            yield from $this->handleRetries($failedItems, $currentBatchSize, $requestBuilder, $responseHandler, $errorHandler, $retryAttempts);
        }
    }

    /**
     * Helper method to build a batch and its request mapping.
     *
     * @param array<mixed>       $chunkItems
     * @param callable|array     $requestBuilder
     * @param callable|array     $errorHandler
     *
     * @return array{0: mixed, 1: array} Returns the batch instance and a mapping of requestId to item.
     */
    private function buildBatch(array $chunkItems, callable|array $requestBuilder, callable|array $errorHandler): array
    {
        $batch = $this->searchConsole->createBatch();
        $requestMap = [];
        foreach ($chunkItems as $item) {
            try {
                [$request, $requestId] = $requestBuilder($item);
                $batch->add($request, $requestId);
                $requestMap[$requestId] = $item;
            } catch (\Exception $e) {
                $errorHandler($e, $item);
            }
        }
        return [$batch, $requestMap];
    }

    /**
     * Helper method to process responses and collect results and failed items.
     *
     * @param array<mixed>       $responses
     * @param array              $requestMap
     * @param callable|array     $responseHandler
     * @param callable|array     $errorHandler
     *
     * @return array{results: array, failed: array}
     */
    private function processResponsesAndCollectFailures(
        array $responses,
        array $requestMap,
        callable|array $responseHandler,
        callable|array $errorHandler
    ): array {
        $results = [];
        $failedItems = [];
        foreach ($responses as $requestId => $response) {
            if (!$response instanceof SearchAnalyticsQueryResponse) {
                $this->logError(sprintf(
                    "Failed response for requestId %s: %s",
                    $requestId,
                    get_class($response)
                ));
                $failedItems[] = $requestMap[$requestId] ?? null;
                continue;
            }
            try {
                $result = $responseHandler($response, $requestId);
                if ($result !== null) {
                    $results[] = $result;
                }
            } catch (\Exception $e) {
                $errorHandler($e, $requestMap[$requestId] ?? null);
            }
        }
        return ['results' => $results, 'failed' => array_filter($failedItems)];
    }


    /**
     * Helper method to handle retries for failed items.
     *
     * @param array              $failedItems
     * @param int                $currentBatchSize
     * @param callable|array     $requestBuilder
     * @param callable|array     $responseHandler
     * @param callable|array     $errorHandler
     * @param int                $retryAttempts
     *
     * @return \Generator
     */
    private function handleRetries(
        array $failedItems,
        int $currentBatchSize,
        callable|array $requestBuilder,
        callable|array $responseHandler,
        callable|array $errorHandler,
        int $retryAttempts
    ): \Generator {

        if ($currentBatchSize > 1) {

            $newBatchSize = max((int) floor($currentBatchSize / 2), 1);
            $this->logError(sprintf("Retrying %d failed items with reduced batch size %d.", count($failedItems), $newBatchSize));
            sleep(self::RETRY_DELAY_SECONDS);
            yield from $this->processInBatches($failedItems, $requestBuilder, $responseHandler, $errorHandler, $newBatchSize, 0);

        } elseif ($retryAttempts < self::MAX_SINGLE_RETRIES) {

            $this->logError(sprintf("Retrying %d failed single-item request(s), attempt %d/%d.", count($failedItems), $retryAttempts + 1, self::MAX_SINGLE_RETRIES));
            sleep(self::RETRY_DELAY_SECONDS * (int) pow(2, $retryAttempts));
            yield from $this->processInBatches($failedItems, $requestBuilder, $responseHandler, $errorHandler, 1, $retryAttempts + 1);

        } else {

            foreach ($failedItems as $failedItem) {
                $errorHandler(new \RuntimeException("Failed request for single item processing after maximum retries."), $failedItem);
            }

        }
    }


    /**
     * Create a batched request from a SearchAnalyticsQueryRequest.
     *
     * @param  string                      $property  - The property to query
     * @param  SearchAnalyticsQueryRequest $request   - The request to convert to a batch request
     *
     * @return array{0: mixed, 1: string}  - Array containing [batchRequest, requestId]
     */
    public function createBatchRequest(string $property, SearchAnalyticsQueryRequest $request): array
    {
        $batchRequest = $this->searchConsole->searchanalytics->query($property, $request);

        // Unique request id based on the request parameters.
        $requestId = $this->getBatchRequestId(
            $property,
            $request->getStartDate(),
            $request->getEndDate(),
            (string)$request->getStartRow(),
            (string)$request->getRowLimit(),
            implode(',', $request->getDimensions() ?? [])
        );

        return [$batchRequest, $requestId];
    }


    /**
     * Generate a reproducible ID (hash) from variable parameters.
     *
     * @param  string|array<string> ...$params  - Variable number of string parameters or arrays to hash
     *
     * @return string  - xxHash of the concatenated parameters
     */
    private function getBatchRequestId(string|array ...$params): string
    {
        $allStringParams = array_map(
            fn($param) => is_array($param) ? implode('§§§', $param) : $param,
            $params
        );
        $concatenated = implode('###', $allStringParams);

        return hash('xxh128', $concatenated);
    }


    /**
     * Default error handler that logs batch processing errors.
     *
     * @param  \Exception $e     - The exception that occurred
     * @param  mixed     $item   - The item being processed when the error occurred
     */
    private function defaultErrorHandler(\Exception $e, mixed $item): void {
        $context = is_array($item) ? ' for batch' : ' for item';
        $this->logError(sprintf(
            'Batch request failed%s: %s (Code: %d)',
            $context,
            $e->getMessage(),
            $e->getCode()
        ));

        if ($e->getPrevious() !== null) {
            $this->logError('Caused by: ' . $e->getPrevious()->getMessage());
        }
    }
}
