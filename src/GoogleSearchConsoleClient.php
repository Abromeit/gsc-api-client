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
use Abromeit\GoogleSearchConsoleClient\Enums\GSCDateFormat;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCDimension;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCOperator;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCMetric;
use Abromeit\GoogleSearchConsoleClient\Enums\GSCGroupType;

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
     *
     * @return WmxSite[] Array of properties
     */
    public function getProperties(): array
    {
        /** @var SitesListResponse $response */
        $response = $this->searchConsole->sites->listSites();

        return $response->getSiteEntry() ?? [];
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
     * Get search performance data for the currently set property.
     *
     * @param  string[]|null                $keywords     Keywords to filter by (null for no filter)
     * @param  string[]|null                $urls         URLs to filter by (null for no filter)
     * @param  TimeframeResolution|null     $resolution   Resolution for aggregating data (null for daily, ALLOVER for total sums)
     *
     * @return array<array{
     *     date: string,
     *     clicks: int,
     *     impressions: int,
     *     ctr: float,
     *     position: float,
     *     keys?: array<string>
     * }> Array of performance data. For ALLOVER resolution, returns a single entry with summed metrics
     *
     * @throws InvalidArgumentException If no property is set or dates are not set
     */
    public function getSearchPerformance(
        ?array $keywords = null,
        ?array $urls = null,
        ?TimeframeResolution $resolution = null,
    ): array {
        if (!$this->hasProperty()) {
            throw new InvalidArgumentException('No property set. Call setProperty() first.');
        }

        if (!$this->hasDates()) {
            throw new InvalidArgumentException('No dates set. Call setDates() first.');
        }

        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($this->startDate->format(GSCDateFormat::DAILY->value));
        $request->setEndDate($this->endDate->format(GSCDateFormat::DAILY->value));
        $request->setDimensions([GSCDimension::DATE->value]);

        // Add filters if specified
        $filterGroups = [];

        if ($keywords !== null) {
            $keywordFilters = array_map(function ($keyword) {
                $filter = new ApiDimensionFilter();
                $filter->setDimension(GSCDimension::QUERY->value);
                $filter->setOperator(GSCOperator::EQUALS->value);
                $filter->setExpression($keyword);
                return $filter;
            }, $keywords);

            $keywordGroup = new ApiDimensionFilterGroup();
            $keywordGroup->setGroupType(GSCGroupType::OR->value);
            $keywordGroup->setFilters($keywordFilters);
            $filterGroups[] = $keywordGroup;
            $request->setDimensions([GSCDimension::DATE->value, GSCDimension::QUERY->value]);
        }

        if ($urls !== null) {
            $urlFilters = array_map(function ($url) {

                $filter = new ApiDimensionFilter();
                $filter->setDimension(GSCDimension::PAGE->value);
                $filter->setOperator(GSCOperator::EQUALS->value);
                $filter->setExpression($url);

                return $filter;
            }, $urls);

            $urlGroup = new ApiDimensionFilterGroup();
            $urlGroup->setGroupType(GSCGroupType::OR->value);
            $urlGroup->setFilters($urlFilters);
            $filterGroups[] = $urlGroup;
            $request->setDimensions([GSCDimension::DATE->value, GSCDimension::PAGE->value]);
        }

        if (!empty($filterGroups)) {
            $request->setDimensionFilterGroups($filterGroups);
        }

        $response = $this->searchConsole->searchanalytics->query($this->property, $request);
        $rows = $response->getRows() ?? [];

        if (empty($rows)) {
            return [];
        }

        // Group data by date for non-daily resolutions
        $groupedData = [];
        foreach ($rows as $row) {
            $date = new DateTime($row->getKeys()[0]);
            $key = $this->getDateKey($date, $resolution ?? TimeframeResolution::DAILY);

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = $this->initializeGroupData($key);

                if (count($row->getKeys()) > 1) {
                    $groupedData[$key][GSCMetric::KEYS->value] = [];
                }
            }

            $this->aggregateRowData($groupedData[$key], $row);

            if (count($row->getKeys()) > 1) {
                $groupedData[$key][GSCMetric::KEYS->value][] = $row->getKeys()[1];
            }
        }

        // Calculate averages and clean up
        $result = [];
        foreach ($groupedData as $key => $data) {
            $clicks = (int)$data[GSCMetric::CLICKS->value];
            $impressions = (int)$data[GSCMetric::IMPRESSIONS->value];
            $weightedPositionSum = $data[GSCMetric::POSITION->value];

            $ctr = $impressions > 0 ? $clicks / $impressions : 0;
            $avgPosition = $impressions > 0 ? $weightedPositionSum / $impressions : 0;

            $result[] = [
                GSCMetric::DATE->value        => $key,
                GSCMetric::CLICKS->value      => $clicks,
                GSCMetric::IMPRESSIONS->value => $impressions,
                GSCMetric::CTR->value         => $ctr,
                GSCMetric::POSITION->value    => $avgPosition,
            ];

            if (isset($data[GSCMetric::KEYS->value])) {
                $result[count($result) - 1][GSCMetric::KEYS->value] = array_unique($data[GSCMetric::KEYS->value]);
            }
        }

        return $result;
    }


    /**
     * Get a date key based on the resolution.
     *
     * @param  DateTimeInterface     $date       The date to get the key for
     * @param  TimeframeResolution   $resolution The resolution to use
     *
     * @return string   The date key in Y-m-d format for daily, Y-W for weekly, Y-m for monthly, or 'allover'
     */
    private function getDateKey(DateTimeInterface $date, TimeframeResolution $resolution): string
    {
        return match($resolution) {
            TimeframeResolution::DAILY   => $date->format(GSCDateFormat::DAILY->value),
            TimeframeResolution::WEEKLY  => $date->format(GSCDateFormat::WEEKLY->value),
            TimeframeResolution::MONTHLY => $date->format(GSCDateFormat::MONTHLY->value),
            TimeframeResolution::ALLOVER => GSCDateFormat::ALLOVER->value,
        };
    }

    /**
     * Initialize a new group data array with zero values.
     *
     * @param  string $key The date key for this group
     *
     * @return array<string, mixed> The initialized group data
     */
    private function initializeGroupData(string $key): array
    {
        return [
            GSCMetric::DATE->value        => $key,
            GSCMetric::CLICKS->value      => 0,
            GSCMetric::IMPRESSIONS->value => 0,
            GSCMetric::CTR->value         => 0,
            GSCMetric::POSITION->value    => 0,
            GSCMetric::COUNT->value       => 0,
        ];
    }

    /**
     * Aggregate data from a row into the group data.
     *
     * @param  array<string, mixed> $groupData The group data to update
     * @param  object              $row       The row data to aggregate
     */
    private function aggregateRowData(array &$groupData, object $row): void
    {
        $impressions = $row->getImpressions();
        $position = $row->getPosition();

        $groupData[GSCMetric::CLICKS->value]      += $row->getClicks();
        $groupData[GSCMetric::IMPRESSIONS->value] += $impressions;
        $groupData[GSCMetric::POSITION->value]    += $position * $impressions;  // Use weighted average for position based on impressions
        $groupData[GSCMetric::COUNT->value]       += 1;
    }
}
