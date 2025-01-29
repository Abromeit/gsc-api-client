<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryResponse;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

/**
 * Handles batch processing of Google Search Console API requests.
 */
class BatchProcessor
{
    /**
     * Maximum number of requests to batch together.
     * This is a Google API limitation.
     *
     * See https://developers.google.com/webmaster-tools/v1/how-tos/batch?hl=en#:~:text=You%27re%20limited%20to%201000%20calls%20in%20a%20single%20batch%20request
     */
    private const MAX_BATCH_SIZE = 1000;

    /**
     * Default number of requests to batch together.
     */
    private const DEFAULT_BATCH_SIZE = 10;

    private readonly Client $client;
    private readonly SearchConsole $searchConsole;
    private int $batchSize;


    public function __construct(
        Client $client,
        ?int $batchSize = null
    ) {
        // here we make a copy of the client,
        // which allows us to set `use-batch` to `true`
        // without causing troubles in other parts of the application.

        $this->client = clone $client;
        $this->client->setUseBatch(true);
        $this->searchConsole = new SearchConsole($this->client);

        $this->setBatchSize($batchSize ?? self::DEFAULT_BATCH_SIZE);
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
     *
     * @param  array<mixed>         $items            - Items to process
     * @param  callable|array       $requestBuilder   - Function to build request for each item
     *                                                  Signature: fn($item) => [$request, $requestId]
     * @param  callable|array       $responseHandler  - Function to handle each response
     *                                                  Signature: fn($response, $requestId) => mixed
     * @param  callable|array|null  $errorHandler     - Optional function to handle errors
     *                                                  Signature: fn(\Exception $e, $item) => void
     *                                                  If null, uses default error logging
     *
     * @return array<mixed>  - Array of processed results
     */
    public function processInBatches(
        array $items,
        callable|array $requestBuilder,
        callable|array $responseHandler,
        callable|array|null $errorHandler = null
    ): array {

        $results = [];
        $errorHandler ??= [$this, 'defaultErrorHandler'];
        $itemChunks = array_chunk($items, $this->batchSize);

        foreach ($itemChunks as $batchItems) {
            $batch = $this->searchConsole->createBatch();

            // add each item's request to the batch
            foreach ($batchItems as $item) {
                try {
                    [$request, $requestId] = $requestBuilder($item);
                    $batch->add($request, $requestId);
                }
                catch (\Exception $e) {
                    $errorHandler($e, $item);
                    continue;
                }
            }

            // exec. batch and handle responses
            try {
                $responses = $batch->execute();

                if (is_array($responses)) {
                    foreach ($responses as $requestId => $response) {
                        if ($response instanceof SearchAnalyticsQueryResponse) {
                            $results[] = $responseHandler($response, $requestId);
                        }
                    }
                }
            } catch (\Exception $e) {
                $errorHandler($e, $batchItems);
                //continue;
            }
        }

        return array_filter($results);
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

        // unique request id based on the request parameters
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
    private function defaultErrorHandler(\Exception $e, mixed $item): void
    {
        error_log('Batch request failed: ' . $e->getMessage());
    }
}
