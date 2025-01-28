<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SitesListResponse;
use Google\Service\SearchConsole\WmxSite;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Google\Service\SearchConsole\ApiDimensionFilter;
use Google\Service\SearchConsole\ApiDimensionFilterGroup;
use InvalidArgumentException;
use DateTimeInterface;
use DateTime;
use Abromeit\GoogleSearchConsoleClient\Enums\TimeframeResolution;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCDateFormat as DateFormat;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCDimension as Dimension;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCOperator as Operator;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCMetric as Metric;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCGroupType as GroupType;

class GoogleSearchConsoleClient
{
    /**
     * Prefix for domain properties in Google Search Console.
     * Example: sc-domain:example.com
     */
    private const DOMAIN_PROPERTY_PREFIX = 'sc-domain:';

    private SearchConsole $searchConsole;
    private ?string $property = null;
    private ?DateTimeInterface $startDate = null;
    private ?DateTimeInterface $endDate = null;
    private readonly DateTimeInterface $zeroDate;

    public function __construct(
        private readonly Client $client
    ) {
        $this->searchConsole = new SearchConsole($this->client);
        $this->zeroDate = new DateTime('@0');
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
     * @return string|null The current property URL or null if none is set
     */
    public function getProperty(): ?string
    {
        return $this->property;
    }


    /**
     * Check if a property is currently set.
     *
     * @return bool True if a property is set, false otherwise
     */
    public function hasProperty(): bool
    {
        return $this->property !== null;
    }


    /**
     * Check if the given URL or current property represents a domain property.
     *
     * @param  string|null $siteUrl The URL to check, or null to check current property (from $this->getProperty())
     *
     * @return bool        True if the URL is a domain property
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
     * Get overall search performance data grouped by day/date.
     *
     * @param  TimeframeResolution|null $resolution - Resolution for aggregating data (null for daily, TimeframeResolution::ALLOVER for total sums)
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float
     * }> Array of performance data. For ALLOVER resolution, returns a single entry with summed metrics
     *
     * @throws InvalidArgumentException If no property is set or dates are not set
     */
    public function getSearchPerformance(
        ?TimeframeResolution $resolution = null
    ): array {
        return $this->executeSearchQuery(
            dimensions: [Dimension::DATE],
            resolution: $resolution
        );
    }


    /**
     * Get search performance data grouped by keywords.
     *
     * @param  TimeframeResolution|null $resolution - Resolution for aggregating data (null for daily, ALLOVER for total sums)
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys: array<string>
     * }> Array of performance data grouped by keywords
     *
     * @throws InvalidArgumentException If no property is set or dates are not set
     */
    public function getSearchPerformanceKeywords(
        ?TimeframeResolution $resolution = null
    ): array {
        return $this->executeSearchQuery(
            dimensions: [Dimension::DATE, Dimension::QUERY],
            resolution: $resolution
        );
    }


    /**
     * Get search performance data grouped by URLs.
     *
     * @param  TimeframeResolution|null $resolution - Resolution for aggregating data (null for daily, ALLOVER for total sums)
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys: array<string>
     * }> Array of performance data grouped by URLs
     *
     * @throws InvalidArgumentException If no property is set or dates are not set
     */
    public function getSearchPerformanceUrls(
        ?TimeframeResolution $resolution = null
    ): array {
        return $this->executeSearchQuery(
            dimensions: [Dimension::DATE, Dimension::PAGE],
            resolution: $resolution
        );
    }


    /**
     * Execute a search analytics query with the given parameters.
     *
     * @param  array<Dimension>          $dimensions  - Dimensions to group by
     * @param  TimeframeResolution|null  $resolution  - Resolution for aggregating data
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys?: array<string>
     * }> Array of performance data
     *
     * @throws InvalidArgumentException If no property is set, dates are not set, or dimensions array is empty
     */
    private function executeSearchQuery(
        array $dimensions,
        ?TimeframeResolution $resolution = null
    ): array {
        if (!$this->hasProperty()) {
            throw new InvalidArgumentException('No property set. Call setProperty() first.');
        }

        if (!$this->hasDates()) {
            throw new InvalidArgumentException('No dates set. Call setDates() first.');
        }

        if (empty($dimensions)) {
            throw new InvalidArgumentException('Dimensions array cannot be empty.');
        }

        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($this->startDate->format(DateFormat::DAILY->value));
        $request->setEndDate($this->endDate->format(DateFormat::DAILY->value));
        $request->setDimensions(array_map(fn(Dimension $d) => $d->value, $dimensions));

        $response = $this->searchConsole->searchanalytics->query($this->property, $request);
        $rows = $response->getRows() ?? [];

        if (empty($rows)) {
            return [];
        }

        return $this->convertResponseToTimeframe($rows, $resolution);
    }


    /**
     * Convert API response rows to the desired timeframe resolution.
     *
     * @param  array<\Google\Service\SearchConsole\SearchAnalyticsRow>  $rows       - The API response rows
     * @param  TimeframeResolution|null                                 $resolution  - The desired time interval to use
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys?: array<string>
     * }> Converted and aggregated performance data
     */
    private function convertResponseToTimeframe(
        array $rows,
        ?TimeframeResolution $resolution
    ): array {
        $groupedData = $this->groupRowsByTimeframe($rows, $resolution);

        return array_map(function(array $data, string $key) {
            $clicks = (int)$data[Metric::CLICKS->value];
            $impressions = (int)$data[Metric::IMPRESSIONS->value];

            $result = [
                Metric::DATE->value        => $key,
                Metric::CLICKS->value      => $clicks,
                Metric::IMPRESSIONS->value => $impressions,
                Metric::CTR->value         => $impressions > 0 ? $clicks / $impressions : 0.0,
                Metric::POSITION->value    => $impressions > 0 ? $data[Metric::POSITION->value] / $impressions : 0.0,
            ];

            if (isset($data[Metric::KEYS->value])) {
                $result[Metric::KEYS->value] = array_unique($data[Metric::KEYS->value]);
            }

            return $result;
        }, $groupedData, array_keys($groupedData));
    }


    /**
     * Group API response rows by the specified timeframe.
     *
     * @param  array                    $rows       - The API response rows
     * @param  TimeframeResolution|null $resolution - The timeframe resolution to group by
     *
     * @return array<string, array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     count: int,
     *     keys?: array<string>
     * }> Grouped performance data
     */
    private function groupRowsByTimeframe(
        array $rows,
        ?TimeframeResolution $resolution
    ): array {

        $resolution ??= TimeframeResolution::DAILY;
        $groupedData = [];

        foreach ($rows as $row) {
            $keys = $row->getKeys();
            $date = new DateTime($keys[0]);
            $key  = self::getDateKey($date, $resolution);
            $hasMultipleKeys = count($keys) > 1;

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    Metric::DATE->value        => $key,
                    Metric::CLICKS->value      => 0,
                    Metric::IMPRESSIONS->value => 0,
                    Metric::CTR->value         => 0,
                    Metric::POSITION->value    => 0,
                    Metric::COUNT->value       => 0,
                ];

                if( $hasMultipleKeys ){
                    $groupedData[$key][Metric::KEYS->value] = [];
                }
            }

            $clicks      = $row->getClicks();
            $impressions = $row->getImpressions();
            $position    = $row->getPosition();

            $groupedData[$key][Metric::CLICKS->value]      += $clicks;
            $groupedData[$key][Metric::IMPRESSIONS->value] += $impressions;
            $groupedData[$key][Metric::POSITION->value]    += $position * $impressions;  // Weighted average for position based on impressions
            $groupedData[$key][Metric::COUNT->value]       += 1;

            if( $hasMultipleKeys ){
                $groupedData[$key][Metric::KEYS->value][] = $keys[1];
            }
        }

        return $groupedData;
    }


    /**
     * Get a date key based on the resolution.
     *
     * @param  DateTimeInterface     $date       - The date to get the key for
     * @param  TimeframeResolution   $resolution - The resolution to use
     *
     * @return string  The date key in Y-m-d format for daily, Y-W for weekly, Y-m for monthly, or 'allover'
     */
    private static function getDateKey(
        DateTimeInterface $date,
        TimeframeResolution $resolution
    ): string {
        return match($resolution) {
            TimeframeResolution::DAILY   => $date->format(DateFormat::DAILY->value),
            TimeframeResolution::WEEKLY  => $date->format(DateFormat::WEEKLY->value),
            TimeframeResolution::MONTHLY => $date->format(DateFormat::MONTHLY->value),
            TimeframeResolution::ALLOVER => DateFormat::ALLOVER->value,
        };
    }
}
