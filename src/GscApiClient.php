<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SitesListResponse;
use Google\Service\SearchConsole\WmxSite;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Google\Service\SearchConsole\SearchAnalyticsQueryResponse;
use Google\Service\SearchConsole\ApiDimensionFilter;
use Google\Service\SearchConsole\ApiDimensionFilterGroup;
use InvalidArgumentException;
use DateTimeInterface;
use DateTime;
use Abromeit\GscApiClient\Enums\GSCDateFormat as DateFormat;
use Abromeit\GscApiClient\Enums\GSCDimension as Dimension;
use Abromeit\GscApiClient\Enums\GSCDeviceType as DeviceType;
use Abromeit\GscApiClient\Enums\GSCAggregationType as AggregationType;
use Abromeit\GscApiClient\Enums\GSCDataState as DataState;
use Abromeit\GscApiClient\BatchProcessor;
use GuzzleHttp\HandlerStack;
use Abromeit\GscApiClient\RetryMiddleware;
use Abromeit\GscApiClient\RequestCounterMiddleware;
use Abromeit\GscApiClient\ThrottlingMiddleware;

class GscApiClient
{
    /**
     * Prefix for domain properties in Google Search Console.
     * Example: sc-domain:example.com
     */
    private const DOMAIN_PROPERTY_PREFIX = 'sc-domain:';

    /**
     * Maximum number of rows that can be retrieved in a single request from GSC API.
     * See https://developers.google.com/webmaster-tools/v1/searchanalytics/query#rowLimit
     */
    private const MAX_ROWS_PER_REQUEST = 25000;

    /**
     * Default number of keywords to return per request if no limit is specified.
     *
     * Here, we take 5000. Which is the max. number of rows returned for a single day in GSC.
     * Which is annoying, since the official documentation states "a maximum of 50K rows of data per day"
     * see https://developers.google.com/webmaster-tools/v1/how-tos/all-your-data#data_limits
     */
    private const DEFAULT_ROWS_PER_REQUEST = 5000;

    private SearchConsole $searchConsole;

    private ?string $property = null;
    private ?DateTimeInterface $startDate = null;
    private ?DateTimeInterface $endDate = null;
    private readonly DateTimeInterface $zeroDate;
    private readonly BatchProcessor $batchProcessor;
    private readonly Client $client;

    private ?string $countryCode = null;
    private ?string $deviceType = null;
    private ?string $searchType = null;
    private ?DataState $dataState = null;

    private RequestCounterMiddleware $requestCounter;

    public function __construct(
        Client $client
    ) {
        $this->client = $client;

        // Create a handler stack with our middleware
        $stack = HandlerStack::create();

        // Add request counter middleware
        $this->requestCounter = new RequestCounterMiddleware();
        $stack->push($this->requestCounter);

        // Add throttling middleware
        $stack->push(ThrottlingMiddleware::create());

        // Then add retry middleware
        $stack->push(RetryMiddleware::create(
            maxRetries: 3,
            initialDelay: 64,  // 64 seconds
            backoffFactor: 2.0   // doubles each retry
        ));

        // Set up the HTTP client with retry and timeout configuration
        $this->client->setHttpClient(new \GuzzleHttp\Client([
            'handler' => $stack,
            'timeout' => 5 * 60, // 5 minutes (timeout includes the duration you need to download the response and the endpoint loves to be slow.)
            'connect_timeout' => 90,
            'headers' => [
                'Accept-Encoding' => 'gzip, deflate'
            ],
            'decode_content' => true  // This tells Guzzle to handle decompression automatically
        ]));

        $this->searchConsole = new SearchConsole($this->client);
        $this->batchProcessor = new BatchProcessor($this->client);
        $this->zeroDate = new DateTime('@0');
    }


    /**
     * Get the current batch size setting.
     *
     * @return int Current batch size
     */
    public function getBatchSize(): int
    {
        return $this->batchProcessor->getBatchSize();
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
        $this->batchProcessor->setBatchSize($batchSize);
        return $this;
    }


    /**
     * Get all properties (websites) the authenticated user has access to.
     * Caution: Sort order returned by API changes with every request.
     *
     * @return WmxSite[] Array of properties
     */
    public function getProperties(): array
    {
        /** @var SitesListResponse $response */
        $response = $this->searchConsole->sites->listSites();

        $properties = $response->getSiteEntry() ?? [];

        return $properties;
    }


    /**
     * Set the property (website) to work with.
     * The property must be accessible by the authenticated user.
     *
     * @param  string $siteUrl Full URL of the property (e.g., 'https://example.com/' or 'sc-domain:example.com')
     *
     * @return self
     *
     * @throws InvalidArgumentException If property is not accessible
     */
    public function setProperty(string $siteUrl): self
    {
        // Normalize the URL by ensuring it ends with a slash if it's not a domain property
        if (!str_starts_with($siteUrl, self::DOMAIN_PROPERTY_PREFIX) && !str_ends_with($siteUrl, '/')) {
            $siteUrl .= '/';
        }

        // Check if we have access to this property
        $properties = $this->getProperties();
        $hasAccess = false;

        foreach ($properties as $property) {
            if ($property->getSiteUrl() === $siteUrl) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            throw new InvalidArgumentException(
                "Property '{$siteUrl}' is not accessible. Please check the URL and your permissions."
            );
        }

        $this->property = $siteUrl;

        return $this;
    }


    /**
     * Get the currently set property URL.
     *
     * @return string|null  - The current property URL or null if none is set
     */
    public function getProperty(): ?string
    {
        return $this->property;
    }


    /**
     * Check if a property is currently set.
     *
     * @return bool  - True if a property is set, false otherwise
     */
    public function hasProperty(): bool
    {
        return $this->property !== null;
    }


    /**
     * Check if the given URL or current property represents a domain property.
     *
     * @param  string|null $siteUrl  - The URL to check, or null to check current property (from $this->getProperty())
     *
     * @return bool        - True if the URL is a domain property
     */
    public function isDomainProperty(?string $siteUrl = null): bool
    {
        $urlToCheck = $siteUrl ?? $this->property;
        if ($urlToCheck === null) {
            return false;
        }

        return str_starts_with($urlToCheck, self::DOMAIN_PROPERTY_PREFIX);
    }


    /**
     * Set the start date for data retrieval.
     *
     * @param  DateTimeInterface $date The start date
     *
     * @return self
     *
     * @throws InvalidArgumentException If start date is after end date (if set)
     */
    public function setStartDate(DateTimeInterface $date): self
    {
        if ($this->hasEndDate() && $date > $this->endDate) {
            throw new InvalidArgumentException(
                'Start date cannot be after end date.'
            );
        }

        $this->startDate = $date;

        return $this;
    }


    /**
     * Set the end date for data retrieval.
     *
     * @param  DateTimeInterface $date The end date
     *
     * @return self
     *
     * @throws InvalidArgumentException If end date is before start date (if set)
     */
    public function setEndDate(DateTimeInterface $date): self
    {
        if ($this->hasStartDate() && $date < $this->startDate) {
            throw new InvalidArgumentException(
                'End date cannot be before start date.'
            );
        }

        $this->endDate = $date;

        return $this;
    }


    /**
     * Set both start and end dates for data retrieval.
     *
     * @param  DateTimeInterface $startDate The start date
     * @param  DateTimeInterface $endDate   The end date
     *
     * @return self
     *
     * @throws InvalidArgumentException If end date is before start date
     */
    public function setDates(DateTimeInterface $startDate, DateTimeInterface $endDate): self
    {
        if ($endDate < $startDate) {
            throw new InvalidArgumentException(
                'End date cannot be before start date.'
            );
        }

        $this->startDate = $startDate;
        $this->endDate = $endDate;

        return $this;
    }


    /**
     * Clear the start date.
     *
     * @return self
     */
    public function clearStartDate(): self
    {
        $this->startDate = null;

        return $this;
    }


    /**
     * Clear the end date.
     *
     * @return self
     */
    public function clearEndDate(): self
    {
        $this->endDate = null;

        return $this;
    }


    /**
     * Clear both start and end dates.
     *
     * @return self
     */
    public function clearDates(): self
    {
        $this->startDate = null;
        $this->endDate = null;

        return $this;
    }


    /**
     * Get the currently set start date.
     *
     * @return DateTimeInterface|null The start date or null if none is set
     */
    public function getStartDate(): ?DateTimeInterface
    {
        return $this->startDate;
    }


    /**
     * Get the currently set end date.
     *
     * @return DateTimeInterface|null The end date or null if none is set
     */
    public function getEndDate(): ?DateTimeInterface
    {
        return $this->endDate;
    }


    /**
     * Get both start and end dates.
     *
     * @return array{start: ?DateTimeInterface, end: ?DateTimeInterface} Array with start and end dates
     */
    public function getDates(): array
    {
        return [
            'start' => $this->startDate,
            'end'   => $this->endDate
        ];
    }


    /**
     * Check if a start date is set.
     *
     * @return bool True if a start date is set, false otherwise
     */
    public function hasStartDate(): bool
    {
        return $this->isValidDate($this->startDate);
    }


    /**
     * Check if an end date is set.
     *
     * @return bool True if an end date is set, false otherwise
     */
    public function hasEndDate(): bool
    {
        return $this->isValidDate($this->endDate);
    }


    /**
     * Check if both start AND end dates are set.
     *
     * @return bool True if both dates are set, false otherwise
     */
    public function hasDates(): bool
    {
        return $this->hasStartDate() && $this->hasEndDate();
    }


    /**
     * Check if a date is valid (not null and after Unix epoch).
     *
     * @param  DateTimeInterface|null $date  - The date to check
     *
     * @return bool  - True if date is valid (not null and after Unix epoch)
     */
    private function isValidDate(?DateTimeInterface $date): bool
    {
        return $date !== null && $date > $this->zeroDate;
    }


    /**
     * Set the country using ISO-3166-1-Alpha-3 code.
     *
     * @param  string|null $countryCode  - ISO-3166-1-Alpha-3 country code or null to clear
     *
     * @return self
     *
     * @throws InvalidArgumentException If country code is invalid
     */
    public function setCountry(?string $countryCode): self
    {
        if ($countryCode === null) {
            $this->countryCode = null;
            return $this;
        }

        if (strlen($countryCode) !== 3) {
            throw new InvalidArgumentException(
                'Country code must be a valid ISO-3166-1-Alpha-3 code (3 uppercase letters)'
            );
        }
        $this->countryCode = strtoupper($countryCode);

        return $this;
    }


    /**
     * Get the currently set country.
     *
     * @return string|null  - The current country or null if none is set
     */
    public function getCountry(): ?string
    {
        return $this->countryCode;
    }


    /**
     * Check if a country filter is set.
     *
     * @return bool  - True if a country filter is set
     */
    public function hasCountry(): bool
    {
        return $this->countryCode !== null;
    }


    /**
     * Set the device type.
     *
     * @param  DeviceType|string|null $deviceType  - Device type or null to clear
     *
     * @return self
     *
     * @throws InvalidArgumentException If device type is invalid
     */
    public function setDevice(DeviceType|string|null $deviceType): self
    {
        // clear device type
        if ($deviceType === null) {
            $this->deviceType = null;
            return $this;
        }

        // handle enum DeviceType
        if ($deviceType instanceof DeviceType) {
            $this->deviceType = $deviceType->value;
            return $this;
        }

        // handle custom device type

        $deviceTypeStr = strtoupper($deviceType);

        $validDeviceTypes = array_column(DeviceType::cases(), 'value');
        if (!in_array($deviceTypeStr, $validDeviceTypes, true)) {
            throw new InvalidArgumentException(
                'Device type must be one of: ' . implode(', ', $validDeviceTypes)
            );
        }

        $this->deviceType = $deviceTypeStr;

        return $this;
    }


    /**
     * Get the currently set device type.
     *
     * @return string|null  - The current device type or null if none is set
     */
    public function getDevice(): ?string
    {
        return $this->deviceType;
    }


    /**
     * Check if a device filter is set.
     *
     * @return bool  - True if a device filter is set
     */
    public function hasDevice(): bool
    {
        return $this->deviceType !== null;
    }


    /**
     * Set the search type.
     *
     * @param  string|null $searchType  - Search type or null to clear
     *
     * @return self
     */
    public function setSearchType(?string $searchType): self
    {
        if ($searchType === null) {
            $this->searchType = null;
            return $this;
        }

        $this->searchType = strtoupper($searchType);

        return $this;
    }


    /**
     * Get the currently set search type.
     *
     * @return string|null  - The current search type or null if none is set
     */
    public function getSearchType(): ?string
    {
        return $this->searchType;
    }


    /**
     * Set the data state for API requests.
     *
     * Controls whether to include fresh (incomplete) data or only final data.
     * By default, the GSC API returns only final/complete data.
     *
     * @param DataState|null $dataState The data state to set, or null to use API default (final)
     *                                  - DataState::FINAL: Only final/complete data (default)
     *                                  - DataState::ALL: Fresh data including incomplete data
     *                                  - DataState::HOURLY_ALL: Fresh data with hourly breakdown
     *
     * @return self
     */
    public function setDataState(?DataState $dataState): self
    {
        $this->dataState = $dataState;
        return $this;
    }


    /**
     * Get the currently set data state.
     *
     * @return DataState|null The current data state or null if using API default (final)
     */
    public function getDataState(): ?DataState
    {
        return $this->dataState;
    }


    /**
     * Get the first date with available data for the current property and date range.
     *
     * This method queries Google Search Console to discover which dates have data
     * available, which is recommended before running the main queries to avoid
     * unnecessary API calls for dates with no data.
     *
     * If no dates are provided and no instance dates are set, this method will use
     * a default range of the last 18 months from today.
     *
     * @param  DateTimeInterface|null $startDate  - Optional start date, defaults to instance start date or 18 months ago
     * @param  DateTimeInterface|null $endDate    - Optional end date, defaults to instance end date or today
     *
     * @return DateTimeInterface|null  - The first date with data, or null if no data exists
     *
     * @throws InvalidArgumentException If property is not set or dates are invalid
     */
    public function getFirstDateWithData(
        ?DateTimeInterface $startDate = null,
        ?DateTimeInterface $endDate = null
    ): ?DateTimeInterface {

        if( $this->property === null ){
            throw new InvalidArgumentException('Property must be set before querying data');
        }

        // Use provided dates or fall back to instance dates, or use default range
        $startDate = $startDate ?? $this->startDate;
        $endDate = $endDate ?? $this->endDate;

        // Set default end date if invalid
        if( !$this->isValidDate($endDate) ){
            $endDate = new DateTime('now'); // Use current date for fresh data capability
        }

        // Set default start date if invalid (18 months before end date)
        if( !$this->isValidDate($startDate) ){
            $startDate = new DateTime($endDate->format(DateFormat::DAILY->value));
            $startDate->modify('-18 months');
        }

        // Create a simple request to get available dates
        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($startDate->format(DateFormat::DAILY->value));
        $request->setEndDate($endDate->format(DateFormat::DAILY->value));
        $request->setDimensions([Dimension::DATE->value]);
        $request->setRowLimit(1); // We only need the first date

        // Execute the query directly
        /** @var SearchAnalyticsQueryResponse $response */
        $response = $this->searchConsole->searchanalytics->query($this->property, $request);

        $rows = $response->getRows();

        if( empty($rows) ){
            return null;
        }

        // Get the first row's date (they should be sorted by date ascending by default)
        $firstRow = $rows[0];
        $dateString = $firstRow->getKeys()[0];

        return new DateTime($dateString);
    }


    /**
     * Get the top keywords by day from Google Search Console.
     *
     * @param  int|null $maxRowsPerDay  Maximum number of rows to return per day, max 5000. Null for default of 5000.
     *
     * @return \Generator<array{
     *     data_date: string,      // Format: YYYY-MM-DD
     *     site_url: string,       // Property URL
     *     query: string,          // Search query
     *     impressions: int,       // Total impressions
     *     clicks: int,            // Total clicks
     *     position: float,        // Average 1-based position
     *     sum_top_position: float // Sum of (position-1)*impressions
     * }> Generator of daily performance data for top keywords
     *
     * @throws InvalidArgumentException  If no property is set, no data is available, or maxRowsPerDay exceeds the limit
     */
    public function getTopKeywordsByDay(?int $maxRowsPerDay = null): \Generator
    {
        // Define how our request should look like
        $newBatchRequest = function(DateTimeInterface $date) use ($maxRowsPerDay) {
            $request = $this->getNewSearchAnalyticsQueryRequest(
                dimensions: [Dimension::DATE, Dimension::QUERY],
                aggregationType: AggregationType::BY_PROPERTY,
                startDate: $date,
                endDate: $date,
                rowLimit: $maxRowsPerDay
            );

            return $this->batchProcessor->createBatchRequest(
                $this->property,
                $request
            );
        };

        // Process all dates in batches of 'n'.
        $results = $this->batchProcessor->processInBatches(
            $this->getAllDatesInRange(),
            $newBatchRequest,
            [$this, 'convertApiResponseKeywordsToArray']
        );

        // Yield results instead of merging
        foreach ($results as $dayResults) {
            foreach ($dayResults as $result) {
                yield $result;
            }
        }
    }


    /**
     * Convert API response rows to match the BigQuery searchdata_site_impression schema.
     *
     * @param  array<\Google\Service\SearchConsole\ApiDataRow>|SearchAnalyticsQueryResponse|null  $rows  - The API response rows
     *
     * @return \Generator<array{
     *     data_date: string,
     *     site_url: string,
     *     query: string,
     *     impressions: int,
     *     clicks: int,
     *     sum_top_position: float
     * }> Converted performance data
     */
    public function convertApiResponseKeywordsToArray(
        array|SearchAnalyticsQueryResponse|null $rows
    ): \Generator {
        if ($rows instanceof SearchAnalyticsQueryResponse) {
            $rows = $rows->getRows();
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $keys = $row->getKeys();
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $position = $row->getPosition();

            $result = [
                'data_date' => $keys[0],
                'site_url' => $this->property,
                'query' => $keys[1],
                'impressions' => $impressions,
                'clicks' => $clicks,
                'position' => $position,
                'sum_top_position' => ($position - 1) * $impressions,
            ];

            yield $result;
        }
    }


    /**
     * Get the top URLs by day from Google Search Console.
     *
     * @param  int|null $maxRowsPerDay  - Maximum number of rows to return per day, max 5000.
     *                                    Null for default of 5000.
     *
     * @return \Generator<array{
     *     data_date: string,      // Format: YYYY-MM-DD
     *     site_url: string,       // Property URL
     *     url: string,            // Page URL
     *     impressions: int,       // Total impressions
     *     clicks: int,            // Total clicks
     *     position: float,        // Average 1-based position
     *     sum_top_position: float // Sum of (position-1)*impressions
     * }> Generator of daily performance data for top URLs
     *
     * @throws InvalidArgumentException  - If no property is set, dates are not set, or maxRowsPerDay exceeds limit
     */
    public function getTopUrlsByDay(?int $maxRowsPerDay = null): \Generator
    {
        // Define how our request should look like
        $newBatchRequest = function(DateTimeInterface $date) use ($maxRowsPerDay) {
            $request = $this->getNewSearchAnalyticsQueryRequest(
                dimensions: [Dimension::DATE, Dimension::PAGE],
                aggregationType: AggregationType::BY_PAGE,
                startDate: $date,
                endDate: $date,
                rowLimit: $maxRowsPerDay
            );

            return $this->batchProcessor->createBatchRequest(
                $this->property,
                $request
            );
        };

        // Process all dates in batches of 'n'.
        $results = $this->batchProcessor->processInBatches(
            $this->getAllDatesInRange(),
            $newBatchRequest,
            [$this, 'convertApiResponseUrlsToArray']
        );

        // Yield results instead of merging
        foreach ($results as $dayResults) {
            foreach ($dayResults as $result) {
                yield $result;
            }
        }
    }


    /**
     * Convert API response rows to match the BigQuery searchdata_site_impression schema.
     *
     * @param  array<\Google\Service\SearchConsole\ApiDataRow>|SearchAnalyticsQueryResponse|null  $rows  - The API response rows
     *
     * @return \Generator<array{
     *     data_date: string,
     *     site_url: string,
     *     url: string,
     *     impressions: int,
     *     clicks: int,
     *     position: int,
     *     sum_top_position: float
     * }> Converted performance data
     */
    public function convertApiResponseUrlsToArray(
        array|SearchAnalyticsQueryResponse|null $rows
    ): \Generator {
        if ($rows instanceof SearchAnalyticsQueryResponse) {
            $rows = $rows->getRows();
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $keys = $row->getKeys();
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $position = $row->getPosition();

            $result = [
                'data_date' => $keys[0],
                'site_url' => $this->property,
                'url' => $keys[1],
                'impressions' => $impressions,
                'clicks' => $clicks,
                'position' => $position,
                'sum_top_position' => ($position - 1) * $impressions,
            ];

            yield $result;
        }
    }


    /**
     * Get the top URLs with their associated keywords (queries) by day from Google Search Console.
     *
     * @param  int|null $maxRowsPerDay  - Maximum number of rows to return per day, max 5000.
     *                                    Null for default of 5000.
     *
     * @return \Generator<array{
     *     data_date: string,      // Format: YYYY-MM-DD
     *     site_url: string,       // Property URL
     *     url: string,            // Page URL
     *     query: string|null,     // Search query (may be null)
     *     impressions: int,       // Total impressions
     *     clicks: int,            // Total clicks
     *     position: float,        // Average 1-based position
     *     sum_top_position: float // Sum of (position-1)*impressions
     * }> Generator of daily performance data for top URLs with their keywords
     *
     * @throws InvalidArgumentException  - If no property is set, dates are not set, or maxRowsPerDay exceeds limit
     */
    public function getTopUrlsWithKeywordsByDay(?int $maxRowsPerDay = null): \Generator
    {
        // Define how our request should look like
        $newBatchRequest = function(DateTimeInterface $date) use ($maxRowsPerDay) {
            $request = $this->getNewSearchAnalyticsQueryRequest(
                dimensions: [Dimension::DATE, Dimension::PAGE, Dimension::QUERY],
                aggregationType: AggregationType::BY_PAGE,
                startDate: $date,
                endDate: $date,
                rowLimit: $maxRowsPerDay
            );

            return $this->batchProcessor->createBatchRequest(
                $this->property,
                $request
            );
        };

        // Process all dates in batches of 'n'.
        $results = $this->batchProcessor->processInBatches(
            $this->getAllDatesInRange(),
            $newBatchRequest,
            [$this, 'convertApiResponseUrlsWithKeywordsToArray']
        );

        // Yield results instead of merging
        foreach ($results as $dayResults) {
            foreach ($dayResults as $result) {
                yield $result;
            }
        }
    }


    /**
     * Convert API response rows to match a URL + keyword performance schema.
     *
     * @param  array<\Google\Service\SearchConsole\ApiDataRow>|SearchAnalyticsQueryResponse|null  $rows
     *
     * @return \Generator<array{
     *     data_date: string,
     *     site_url: string,
     *     url: string,
     *     query: string|null,
     *     impressions: int,
     *     clicks: int,
     *     position: int,
     *     sum_top_position: float
     * }>
     */
    public function convertApiResponseUrlsWithKeywordsToArray(
        array|SearchAnalyticsQueryResponse|null $rows
    ): \Generator {
        if ($rows instanceof SearchAnalyticsQueryResponse) {
            $rows = $rows->getRows();
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $keys = $row->getKeys();
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $position = $row->getPosition();

            $result = [
                'data_date' => $keys[0],
                'site_url' => $this->property,
                'url' => $keys[1] ?? null,
                'query' => $keys[2] ?? null,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'position' => $position,
                'sum_top_position' => ($position - 1) * $impressions,
            ];

            yield $result;
        }
    }


    /**
     * Get the top URLs by day from Google Search Console.
     *
     * @return \Generator<array{
     *     data_date: string,
     *     site_url: string,
     *     url: string,
     *     query?: string,
     *     country?: string,
     *     device?: string,
     *     impressions: int,
     *     clicks: int,
     *     sum_top_position: float
     * }> Generator of daily performance data for top URLs
     *
     * @throws InvalidArgumentException  - If no property is set, dates are not set, or maxRowsPerDay exceeds limit
     */
    public function getSearchPerformanceByUrl(): \Generator
    {
        $currentStartRow = 0;
        $maxRowsPerRequest = self::MAX_ROWS_PER_REQUEST;
        $datesOfInterest = $this->getAllDatesInRange();

        while ( !empty($datesOfInterest) ){

            // Define how our request should look like
            $newBatchRequest = function(DateTimeInterface $date) use ($maxRowsPerRequest, $currentStartRow) {
                $request = $this->getNewSearchAnalyticsQueryRequest(
                    dimensions: [
                        Dimension::DATE,
                        Dimension::PAGE,
                        Dimension::QUERY,
                        Dimension::COUNTRY,
                        Dimension::DEVICE
                    ],
                    aggregationType: AggregationType::BY_PAGE,
                    startDate: $date,
                    endDate: $date,
                    rowLimit: $maxRowsPerRequest,
                    startRow: $currentStartRow
                );

                return $this->batchProcessor->createBatchRequest(
                    $this->property,
                    $request
                );
            };

            $rowsReturnedByDate = [];

            // Process all dates in batches of 'n'.
            $batchedResults = $this->batchProcessor->processInBatches(
                $datesOfInterest,
                $newBatchRequest,
                [$this, 'convertApiResponseSearchPerformanceToArray']
            );

            // Yield results instead of merging
            foreach ($batchedResults as $results) {
                foreach ($results as $result) {

                    if (isset($result['data_date'])) {
                        $rowsReturnedByDate[$result['data_date']] = (
                            !isset($rowsReturnedByDate[$result['data_date']]) ? 1
                            : $rowsReturnedByDate[$result['data_date']] + 1
                        );
                    }

                    yield $result;
                }
            }

            // Filter out dates that have returned fewer rows than the maximum,
            // indicating we have all their data
            $datesOfInterest = array_filter(
                $datesOfInterest,
                function(DateTimeInterface $date) use ($rowsReturnedByDate, $maxRowsPerRequest): bool {
                    $dateStr = $date->format(DateFormat::DAILY->value);
                    return isset($rowsReturnedByDate[$dateStr]) &&
                        $rowsReturnedByDate[$dateStr] === $maxRowsPerRequest;
                }
            );

            $currentStartRow += $maxRowsPerRequest;
        }
    }


    /**
     * Convert API response rows to match the search performance schema.
     *
     * @param  array<\Google\Service\SearchConsole\ApiDataRow>|SearchAnalyticsQueryResponse|null  $rows  - The API response rows
     *
     * @return \Generator<array{
     *     data_date: string,
     *     site_url: string,
     *     url: string,
     *     query: string,
     *     country: string|null,
     *     device: string|null,
     *     impressions: int,
     *     clicks: int,
     *     position: int,
     *     sum_top_position: float
     * }> Converted performance data
     */
    public function convertApiResponseSearchPerformanceToArray(
        array|SearchAnalyticsQueryResponse|null $rows
    ): \Generator {
        if ($rows instanceof SearchAnalyticsQueryResponse) {
            $rows = $rows->getRows();
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $keys = $row->getKeys();
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $position = $row->getPosition();

            $result = [
                'data_date' => $keys[0],
                'site_url' => $this->property,
                'url' => $keys[1],
                'query' => $keys[2] ?? null,
                'country' => $keys[3] ?? null,
                'device' => $keys[4] ?? null,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'position' => $position,
                'sum_top_position' => ($position - 1) * $impressions,
            ];

            yield $result;
        }
    }


    /**
     * Normalize the row limit to be within valid bounds.
     * Uses default if null, caps at max allowed.
     *
     * @param  int|null $rowNumbersToReturn  - The requested row limit or null for default
     *
     * @return int      - The normalized row limit
     */
    private function normalizeRowLimit(?int $rowNumbersToReturn): int
    {
        if( $rowNumbersToReturn === null ){
            $rowNumbersToReturn = self::DEFAULT_ROWS_PER_REQUEST;
        }

        if ($rowNumbersToReturn <= 0) {
            return 0;
        }

        return min($rowNumbersToReturn, self::MAX_ROWS_PER_REQUEST);
    }


    /**
     * Normalize dimensions to strings.
     *
     * @param  array<Dimension|string>  $dimensions  - Array of dimensions to normalize
     *
     * @return array<string>  - Array of normalized dimension strings
     *
     * @throws InvalidArgumentException  - If a dimension is neither a string nor a Dimension enum
     */
    private function normalizeDimensions(array $dimensions): array
    {
        return array_map(
            static function ($dimension): string {
                if ($dimension instanceof Dimension) {
                    return $dimension->value;
                }
                if (is_string($dimension)) {
                    // notice: we're not lowercasing here,
                    // since not all dimensions are lowercase.
                    return $dimension;
                }
                throw new \InvalidArgumentException(
                    'Dimensions must be either strings or Dimension enum values'
                );
            },
            $dimensions
        );
    }


    /**
     * Get an array of dates between start and end date (inclusive).
     *
     * @param  DateTimeInterface|null $startDate  - Optional start date, defaults to instance start date
     * @param  DateTimeInterface|null $endDate    - Optional end date, defaults to instance end date
     *
     * @return array<DateTimeInterface>  - Array of dates between start and end date (inclusive)
     */
    private function getAllDatesInRange(
        ?DateTimeInterface $startDate = null,
        ?DateTimeInterface $endDate = null
    ): array
    {
        $dates = [];
        $currentDate = new DateTime(
            ($startDate ?? $this->startDate)->format(DateFormat::DAILY->value)
        );
        $endDate = new DateTime(
            ($endDate ?? $this->endDate)->format(DateFormat::DAILY->value)
        );

        while ($currentDate <= $endDate) {
            $dates[] = clone $currentDate;
            $currentDate->modify('+1 day');
        }

        return $dates;
    }


    /**
     * Create a dimension filter group for a single dimension.
     *
     * @param  string $dimension   - The dimension to filter on (e.g., 'country', 'device')
     * @param  string $expression  - The value to filter for
     * @param  string $operator    - The operator to use (e.g., 'equals', 'contains', 'notContains', 'includingRegex')
     *                              See https://developers.google.com/webmaster-tools/v1/searchanalytics/query#dimensionFilterGroups.filters.operator
     *
     * @return ApiDimensionFilterGroup  - The created filter group
     */
    public function getNewApiDimensionFilterGroup(
        string $dimension,
        string $expression,
        string $operator = 'equals'
    ): ApiDimensionFilterGroup
    {
        $filter = new ApiDimensionFilter();
        $filter->setDimension($dimension);
        $filter->setOperator($operator);
        $filter->setExpression($expression);

        $filterGroup = new ApiDimensionFilterGroup();
        $filterGroup->setGroupType('and');
        $filterGroup->setFilters([$filter]);

        return $filterGroup;
    }


    /**
     * Normalize aggregation type to string.
     *
     * @param  AggregationType|string|null  $aggregationType  - Aggregation type to normalize
     *
     * @return string  - Normalized aggregation type string
     *
     * @throws InvalidArgumentException  - If aggregation type is neither a string nor an AggregationType enum
     */
    private function normalizeAggregationType(AggregationType|string|null $aggregationType): string {

        if( $aggregationType === null ){
            return AggregationType::AUTO->value;
        }

        if( $aggregationType instanceof AggregationType ){
            return $aggregationType->value;
        }

        if( is_string($aggregationType) ){
            return $aggregationType;
        }

        throw new \InvalidArgumentException(
            'Aggregation type must be either string or AggregationType enum value'
        );
    }


    /**
     * Create a search analytics query request object with the given or current settings.
     *
     * @param  array<Dimension|string>  $dimensions      - Dimensions to group by (e.g. DATE, QUERY, PAGE, COUNTRY, DEVICE)
     *                                                     Can be either Dimension enum values or strings
     * @param  DateTimeInterface|null   $startDate       - Start date (defaults to instance startDate)
     * @param  DateTimeInterface|null   $endDate         - End date   (defaults to instance endDate)
     * @param  int|null                 $startRow        - Start row  (optional)
     * @param  int|null                 $rowLimit        - Row limit  (optional)
     * @param  array{
     *     country?: string,
     *     device?: string,
     *     searchType?: string
     * }                                $filters         - Optional filters for country, device, and search type
     * @param  AggregationType|null     $aggregationType - Aggregation type (defaults to AUTO)
     * @param  DataState|null           $dataState       - Data state (fresh vs final data)
     *
     * @return SearchAnalyticsQueryRequest
     *
     * @throws InvalidArgumentException If no dimensions are provided and instance has none
     */
    public function getNewSearchAnalyticsQueryRequest(
        array $dimensions,
        ?DateTimeInterface $startDate = null,
        ?DateTimeInterface $endDate = null,
        ?int $rowLimit = null,
        ?int $startRow = null,
        array $filters = [],
        ?AggregationType $aggregationType = null,
        ?DataState $dataState = null
    ): SearchAnalyticsQueryRequest {

        // Validate and normalize parameters
        if (empty($dimensions) ) {
            throw new \InvalidArgumentException('At least one dimension must be provided');
        }

        // Normalize dimensions to "all strings"
        $dimensions = $this->normalizeDimensions($dimensions);

        // Set default dates if not provided
        $startDate = $startDate ?? $this->startDate;
        $endDate = $endDate ?? $this->endDate;

        if ( !$this->isValidDate($startDate) || !$this->isValidDate($endDate) ) {
            throw new \InvalidArgumentException('Both start and end dates must be set');
        }

        // Normalize row limit
        $rowLimit = $this->normalizeRowLimit($rowLimit);

        // Apply filter settings, if any.

        if (!isset($filters['country']) && $this->countryCode !== null ) {
            $filters['country'] = $this->countryCode;
        }

        if (!isset($filters['device']) && $this->deviceType !== null ) {
            $filters['device'] = $this->deviceType;
        }

        if (!isset($filters['searchType']) && $this->searchType !== null) {
            $filters['searchType'] = $this->searchType;
        }

        // Use instance dataState if not provided in parameter
        $dataState = $dataState ?? $this->dataState;

        // Normalize aggregation type to "all strings"
        $aggregationType = $this->normalizeAggregationType($aggregationType);

        // Prepare the request
        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($startDate->format(DateFormat::DAILY->value));
        $request->setEndDate($endDate->format(DateFormat::DAILY->value));
        $request->setDimensions($dimensions);
        $request->setAggregationType($aggregationType);

        // Add optional filters
        if (!empty($filters)) {
            $dimensionFilterGroups = [];

            if (isset($filters['country'])) {
                $dimensionFilterGroups[] = $this->getNewApiDimensionFilterGroup('country', $filters['country']);
            }

            if (isset($filters['device'])) {
                $dimensionFilterGroups[] = $this->getNewApiDimensionFilterGroup('device', $filters['device']);
            }

            if (isset($filters['searchType'])) {
                $request->setType($filters['searchType']);
            }

            if (!empty($dimensionFilterGroups)) {
                $request->setDimensionFilterGroups($dimensionFilterGroups);
            }
        }

        if ($startRow !== null) {
            $request->setStartRow($startRow);
        }
        $request->setRowLimit($rowLimit);

        // Set data state if specified
        if ($dataState !== null) {
            $request->setDataState($dataState->value);
        }

        return $request;
    }


    /**
     * Get current requests per second.
     *
     * @return float  - Average requests per second
     */
    public function getRequestsPerSecond(): float
    {
        return $this->requestCounter->getRequestsPerSecond();
    }


    /**
     * Get total requests in the last n seconds.
     *
     * @return int  - Total number of requests
     */
    public function getTotalRequests(): int
    {
        return $this->requestCounter->getTotalRequests();
    }
}
