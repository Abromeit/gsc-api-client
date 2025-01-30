<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SitesListResponse;
use Google\Service\SearchConsole\WmxSite;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Google\Service\SearchConsole\SearchAnalyticsQueryResponse;
use InvalidArgumentException;
use DateTimeInterface;
use DateTime;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCDateFormat as DateFormat;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCDimension as Dimension;
use Abromeit\GoogleSearchConsoleClient\BatchProcessor;

class GoogleSearchConsoleClient
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


    public function __construct(
        Client $client
    ) {
        $this->client = $client;

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
        return $this->startDate !== null && $this->startDate > $this->zeroDate;
    }


    /**
     * Check if an end date is set.
     *
     * @return bool True if an end date is set, false otherwise
     */
    public function hasEndDate(): bool
    {
        return $this->endDate !== null && $this->endDate > $this->zeroDate;
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
     * Get the top keywords by day from Google Search Console.
     *
     * @param  int|null $maxRowsPerDay  - Maximum number of rows to return per day, max 5000.
     *                                    Null for default of 5000.
     *
     * @return \Generator<array{
     *     data_date: string,
     *     site_url: string,
     *     query: string,
     *     impressions: int,
     *     clicks: int,
     *     sum_top_position: float
     * }> Generator of daily performance data for top keywords
     *
     * @throws InvalidArgumentException  - If no property is set, dates are not set, or maxRowsPerDay exceeds limit
     */
    public function getTopKeywordsByDay(?int $maxRowsPerDay = null): \Generator
    {
        // Define how our request should look like
        $newBatchRequest = function(DateTimeInterface $date) use ($maxRowsPerDay) {
            $request = $this->getNewSearchAnalyticsQueryRequest(
                dimensions: [Dimension::DATE, Dimension::QUERY],
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
     * Get the top URLs by day from Google Search Console.
     *
     * @param  int|null $maxRowsPerDay  - Maximum number of rows to return per day, max 5000.
     *                                    Null for default of 5000.
     *
     * @return \Generator<array{
     *     data_date: string,
     *     site_url: string,
     *     url: string,
     *     impressions: int,
     *     clicks: int,
     *     sum_position: float
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
     * Get an array of dates between start and end date (inclusive).
     *
     * @param  DateTimeInterface|null $startDate  - Optional start date, defaults to instance start date
     * @param  DateTimeInterface|null $endDate    - Optional end date, defaults to instance end date
     *
     * @return array<DateTimeInterface>  - Array of dates between start and end date (inclusive)
     */
    private function getAllDatesInRange(?DateTimeInterface $startDate = null, ?DateTimeInterface $endDate = null): array
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
     * Create a search analytics query request object with the given or current settings.
     *
     * @param  array<Dimension>        $dimensions  - Dimensions to group by (e.g. DATE, QUERY, PAGE)
     * @param  DateTimeInterface|null  $startDate   - Start date (defaults to instance startDate)
     * @param  DateTimeInterface|null  $endDate     - End date   (defaults to instance endDate)
     * @param  int|null                $startRow    - Start row  (optional)
     * @param  int|null                $rowLimit    - Row limit  (optional)
     *
     * @return SearchAnalyticsQueryRequest
     *
     * @throws InvalidArgumentException If no dimensions are provided and instance has none
     */
    private function getNewSearchAnalyticsQueryRequest(
        array $dimensions,
        ?DateTimeInterface $startDate = null,
        ?DateTimeInterface $endDate = null,
        ?int $rowLimit = null,
        ?int $startRow = null,
    ): SearchAnalyticsQueryRequest {

        if (empty($dimensions) ) {
            throw new InvalidArgumentException('No dimensions provided.');
        }

        if (!$this->hasDates() && (
            ($startDate === null || $startDate <= $this->zeroDate) ||
            ($endDate === null || $endDate <= $this->zeroDate)
        )) {
            throw new InvalidArgumentException('No dates set. Call setDates() first.');
        }

        if (!$this->hasProperty()) {
            throw new InvalidArgumentException('No property set. Call setProperty() first.');
        }

        $request = new SearchAnalyticsQueryRequest();
        $request->setDimensions(array_column($dimensions, 'value'));
        $request->setStartDate(($startDate ?? $this->startDate)->format(DateFormat::DAILY->value));
        $request->setEndDate(($endDate ?? $this->endDate)->format(DateFormat::DAILY->value));

        if ($rowLimit !== null) {
            $rowLimit = $this->normalizeRowLimit($rowLimit);
            $request->setRowLimit($rowLimit);
            $request->setStartRow(0);
        }

        if ($startRow !== null) {
            $request->setStartRow($startRow);
        }

        return $request;
    }


    /**
     * Convert API response rows to match the BigQuery searchdata_site_impression schema.
     *
     * @param  array<\Google\Service\SearchConsole\ApiDataRow>|SearchAnalyticsQueryResponse|null  $rows  - The API response rows
     *
     * @return array<array{
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
    ): array
    {
        if ($rows instanceof SearchAnalyticsQueryResponse) {
            $rows = $rows->getRows();
        }

        if (empty($rows)) {
            return [];
        }

        return array_map(function($row) {
            $keys = $row->getKeys();
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $position = $row->getPosition();

            return [
                'data_date' => $keys[0],
                'site_url' => $this->property,
                'query' => $keys[1],
                // 'is_anonymized_query' => empty($keys[1]),
                // 'Country' => 'XXX', // Not available in current API response
                // 'search_type' => 'web', // Default to web search
                // 'device' => 'DESKTOP', // Not available in current API response
                'impressions' => $impressions,
                'clicks' => $clicks,
                'sum_top_position' => ($position - 1) * $impressions, // Convert 1-based to 0-based and multiply by impressions
            ];
        }, $rows);
    }


    /**
     * Convert API response rows to match the BigQuery searchdata_site_impression schema.
     *
     * @param  array<\Google\Service\SearchConsole\ApiDataRow>|SearchAnalyticsQueryResponse|null  $rows  - The API response rows
     *
     * @return array<array{
     *     data_date: string,
     *     site_url: string,
     *     query: string,
     *     impressions: int,
     *     clicks: int,
     *     sum_top_position: float
     * }> Converted performance data
     */
    public function convertApiResponseUrlsToArray(
        array|SearchAnalyticsQueryResponse|null $rows
    ): array
    {
        if ($rows instanceof SearchAnalyticsQueryResponse) {
            $rows = $rows->getRows();
        }

        if (empty($rows)) {
            return [];
        }

        return array_map(function($row) {
            $keys = $row->getKeys();
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();
            $position = $row->getPosition();

            return [
                'data_date' => $keys[0],
                'site_url' => $this->property,
                'url' => $keys[1],
                // 'is_anonymized_query' => empty($keys[1]),
                // 'Country' => 'XXX', // Not available in current API response
                // 'search_type' => 'web', // Default to web search
                // 'device' => 'DESKTOP', // Not available in current API response
                'impressions' => $impressions,
                'clicks' => $clicks,
                'sum_top_position' => ($position - 1) * $impressions, // Convert 1-based to 0-based and multiply by impressions
            ];
        }, $rows);
    }

}
