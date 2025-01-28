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
use Google\Service\SearchConsole\SearchAnalyticsQueryResponse;
use InvalidArgumentException;
use DateTimeInterface;
use DateTime;
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
     * Execute a search analytics query with the given parameters.
     *
     * @param  array<Dimension>  $dimensions  - Dimensions to group by
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
    private function executeSearchQuery(array $dimensions): array
    {
        if (!$this->hasProperty()) {
            throw new InvalidArgumentException('No property set. Call setProperty() first.');
        }

        if (!$this->hasDates()) {
            throw new InvalidArgumentException('No dates set. Call setDates() first.');
        }

        if (empty($dimensions)) {
            throw new InvalidArgumentException('Dimensions array cannot be empty.');
        }

        $response = $this->getNewSearchAnalyticsQueryRequest($dimensions);
        $rows = $response->getRows() ?? [];

        if (empty($rows)) {
            return [];
        }

        return $this->convertApiResponseToArray($rows);
    }


    /**
     * Create a search analytics query request object with the current settings.
     *
     * @param  array<Dimension>  $dimensions  - Dimensions to group by
     *
     * @return SearchAnalyticsQueryResponse
     */
    private function getNewSearchAnalyticsQueryRequest(array $dimensions): SearchAnalyticsQueryResponse
    {
        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($this->startDate->format(DateFormat::DAILY->value));
        $request->setEndDate($this->endDate->format(DateFormat::DAILY->value));
        $request->setDimensions(array_column($dimensions, 'value'));

        return $this->searchConsole->searchanalytics->query($this->property, $request);
    }


    /**
     * Convert API response rows to an array of performance data.
     *
     * @param  array<\Google\Service\SearchConsole\SearchAnalyticsRow>  $rows  - The API response rows
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys?: array<string>
     * }> Converted performance data
     */
    private function convertApiResponseToArray(array $rows): array
    {
        return array_map(function($row) {
            $keys = $row->getKeys();
            $clicks = (int)$row->getClicks();
            $impressions = (int)$row->getImpressions();

            $result = [
                Metric::DATE->value        => $keys[0],
                Metric::CLICKS->value      => $clicks,
                Metric::IMPRESSIONS->value => $impressions,
                Metric::CTR->value         => $impressions > 0 ? $clicks / $impressions : 0.0,
                Metric::POSITION->value    => $row->getPosition(),
            ];

            if (count($keys) > 1) {
                $result[Metric::KEYS->value] = array_slice($keys, 1);
            }

            return $result;
        }, $rows);
    }


    /**
     * Get overall search performance data by day.
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float
     * }> Array of daily performance data
     *
     * @throws InvalidArgumentException If no property is set or dates are not set
     */
    public function getSearchPerformance(): array
    {
        return $this->executeSearchQuery([Dimension::DATE]);
    }


    /**
     * Get search performance data grouped by keywords and days.
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys: array<string>
     * }> Array of daily performance data grouped by keywords
     *
     * @throws InvalidArgumentException If no property is set or dates are not set
     */
    public function getSearchPerformanceKeywords(): array
    {
        return $this->executeSearchQuery([Dimension::DATE, Dimension::QUERY]);
    }


    /**
     * Get search performance data grouped by URLs and days.
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys: array<string>
     * }> Array of daily performance data grouped by URLs
     *
     * @throws InvalidArgumentException If no property is set or dates are not set
     */
    public function getSearchPerformanceUrls(): array
    {
        return $this->executeSearchQuery([Dimension::DATE, Dimension::PAGE]);
    }
}
