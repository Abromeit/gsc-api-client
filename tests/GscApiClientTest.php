<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Tests;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SitesListResponse;
use Google\Service\SearchConsole\WmxSite;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Abromeit\GscApiClient\GscApiClient;
use InvalidArgumentException;
use DateTime;
use ReflectionMethod;
use Abromeit\GscApiClient\Enums\GSCDimension as Dimension;
use Abromeit\GscApiClient\Enums\GSCDeviceType as DeviceType;
use Abromeit\GscApiClient\Enums\GSCDataState as DataState;

class GscApiClientTest extends TestCase
{
    private GscApiClient $client;
    private MockObject&Client $googleClient;
    private MockObject&SearchConsole $searchConsole;
    private MockObject&SearchConsole\Resource\Sites $sites;
    private MockObject&SearchConsole\Resource\Searchanalytics $searchanalytics;
    private WmxSite $testSite;

    protected function setUp(): void
    {
        $this->googleClient = $this->createMock(Client::class);

        // Create nested mocks for SearchConsole service
        $this->searchConsole = $this->createMock(SearchConsole::class);
        $this->sites = $this->createMock(SearchConsole\Resource\Sites::class);
        $this->searchanalytics = $this->createMock(SearchConsole\Resource\Searchanalytics::class);
        $this->searchConsole->sites = $this->sites;
        $this->searchConsole->searchanalytics = $this->searchanalytics;

        // Create the client with our mocked Google client
        $this->client = new GscApiClient($this->googleClient);

        // Inject our mocked SearchConsole service
        $reflection = new \ReflectionProperty($this->client, 'searchConsole');
        $reflection->setValue($this->client, $this->searchConsole);

        // Create a test site for reuse
        $this->testSite = new WmxSite();
        $this->testSite->setSiteUrl('https://example.com/');
    }

    public function testInitialStateHasNoProperty(): void
    {
        $this->assertFalse($this->client->hasProperty());
        $this->assertNull($this->client->getProperty());
    }

    public function testInitialStateHasNoDates(): void
    {
        $this->assertFalse($this->client->hasStartDate());
        $this->assertFalse($this->client->hasEndDate());
        $this->assertNull($this->client->getStartDate());
        $this->assertNull($this->client->getEndDate());
    }

    public function testSetStartDate(): void
    {
        $date = new DateTime('2024-01-01');
        $result = $this->client->setStartDate($date);

        $this->assertSame($this->client, $result);
        $this->assertTrue($this->client->hasStartDate());
        $this->assertEquals($date, $this->client->getStartDate());
    }

    public function testSetEndDate(): void
    {
        $date = new DateTime('2024-12-31');
        $result = $this->client->setEndDate($date);

        $this->assertSame($this->client, $result);
        $this->assertTrue($this->client->hasEndDate());
        $this->assertEquals($date, $this->client->getEndDate());
    }

    public function testSetStartDateAfterEndDateThrowsException(): void
    {
        $endDate = new DateTime('2024-01-01');
        $this->client->setEndDate($endDate);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Start date cannot be after end date.');

        $this->client->setStartDate(new DateTime('2024-01-02'));
    }

    public function testSetEndDateBeforeStartDateThrowsException(): void
    {
        $startDate = new DateTime('2024-01-02');
        $this->client->setStartDate($startDate);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End date cannot be before start date.');

        $this->client->setEndDate(new DateTime('2024-01-01'));
    }

    public function testSetValidDateRange(): void
    {
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');

        $this->client->setStartDate($startDate)
            ->setEndDate($endDate);

        $this->assertTrue($this->client->hasStartDate());
        $this->assertTrue($this->client->hasEndDate());
        $this->assertEquals($startDate, $this->client->getStartDate());
        $this->assertEquals($endDate, $this->client->getEndDate());
    }

    public function testSetSameDateForStartAndEnd(): void
    {
        $date = new DateTime('2024-01-01');

        $this->client->setStartDate($date)
            ->setEndDate($date);

        $this->assertEquals($date, $this->client->getStartDate());
        $this->assertEquals($date, $this->client->getEndDate());
    }

    public function testClearStartDate(): void
    {
        // Set a date first
        $date = new DateTime('2024-01-01');
        $this->client->setStartDate($date);
        $this->assertTrue($this->client->hasStartDate());

        // Clear it
        $result = $this->client->clearStartDate();

        // Verify fluent interface and cleared state
        $this->assertSame($this->client, $result);
        $this->assertFalse($this->client->hasStartDate());
        $this->assertNull($this->client->getStartDate());
    }

    public function testClearEndDate(): void
    {
        // Set a date first
        $date = new DateTime('2024-12-31');
        $this->client->setEndDate($date);
        $this->assertTrue($this->client->hasEndDate());

        // Clear it
        $result = $this->client->clearEndDate();

        // Verify fluent interface and cleared state
        $this->assertSame($this->client, $result);
        $this->assertFalse($this->client->hasEndDate());
        $this->assertNull($this->client->getEndDate());
    }

    public function testClearDates(): void
    {
        // Set both dates
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');

        $this->client->setStartDate($startDate)
            ->setEndDate($endDate);

        $this->assertTrue($this->client->hasStartDate());
        $this->assertTrue($this->client->hasEndDate());

        // Clear both
        $result = $this->client->clearDates();

        // Verify fluent interface and cleared state
        $this->assertSame($this->client, $result);
        $this->assertFalse($this->client->hasStartDate());
        $this->assertFalse($this->client->hasEndDate());
        $this->assertNull($this->client->getStartDate());
        $this->assertNull($this->client->getEndDate());
    }

    public function testClearStartDateAllowsSettingLaterDate(): void
    {
        // Set initial dates
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');

        $this->client->setStartDate($startDate)
            ->setEndDate($endDate);

        // Clear end date first (to avoid validation error)
        $this->client->clearEndDate();
        // Then clear start date
        $this->client->clearStartDate();

        $newStartDate = new DateTime('2025-01-01');
        $this->client->setStartDate($newStartDate);

        $this->assertEquals($newStartDate, $this->client->getStartDate());
    }

    public function testClearEndDateAllowsSettingEarlierDate(): void
    {
        // Set initial dates
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');

        $this->client->setStartDate($startDate)
            ->setEndDate($endDate);

        // Clear start date first (to avoid validation error)
        $this->client->clearStartDate();
        // Then clear end date
        $this->client->clearEndDate();

        $newEndDate = new DateTime('2023-12-31');
        $this->client->setEndDate($newEndDate);

        $this->assertEquals($newEndDate, $this->client->getEndDate());
    }

    public function testGetPropertiesReturnsArrayOfSites(): void
    {
        // Create mock response
        $response = new SitesListResponse();
        $response->setSiteEntry([$this->testSite]);

        // Configure mock
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($response);

        // Execute and verify
        $result = $this->client->getProperties();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(WmxSite::class, $result[0]);
        $this->assertEquals('https://example.com/', $result[0]->getSiteUrl());
    }

    public function testGetPropertiesReturnsEmptyArrayWhenNoSites(): void
    {
        // Create mock response with no sites
        $response = new SitesListResponse();
        $response->setSiteEntry(null);

        // Configure mock
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($response);

        // Execute and verify
        $result = $this->client->getProperties();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSetPropertyWithValidSite(): void
    {
        // Create mock response with our test site
        $response = new SitesListResponse();
        $response->setSiteEntry([$this->testSite]);

        // Configure mock
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($response);

        // Execute and verify
        $result = $this->client->setProperty('https://example.com/');

        $this->assertSame($this->client, $result);
        $this->assertTrue($this->client->hasProperty());
        $this->assertEquals('https://example.com/', $this->client->getProperty());
    }

    public function testSetPropertyNormalizesUrl(): void
    {
        // Create mock response with our test site
        $response = new SitesListResponse();
        $response->setSiteEntry([$this->testSite]);

        // Configure mock
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($response);

        // Execute with URL without trailing slash
        $this->client->setProperty('https://example.com');

        // Verify the URL was normalized
        $this->assertEquals('https://example.com/', $this->client->getProperty());
    }

    public function testSetPropertyWithInvalidSiteThrowsException(): void
    {
        // Create mock response with different site
        $otherSite = new WmxSite();
        $otherSite->setSiteUrl('https://other.com/');

        $response = new SitesListResponse();
        $response->setSiteEntry([$otherSite]);

        // Configure mock
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($response);

        // Execute and verify exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'https://example.com/' is not accessible");

        $this->client->setProperty('https://example.com/');
    }

    public function testSetPropertyWithDomainProperty(): void
    {
        // Create domain property
        $domainSite = new WmxSite();
        $domainSite->setSiteUrl('sc-domain:example.com');

        $response = new SitesListResponse();
        $response->setSiteEntry([$domainSite]);

        // Configure mock
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($response);

        // Execute and verify
        $this->client->setProperty('sc-domain:example.com');

        // Domain properties should not be modified
        $this->assertEquals('sc-domain:example.com', $this->client->getProperty());
    }

    public function testPropertyStateAfterInvalidSet(): void
    {
        // First set a valid property
        $response = new SitesListResponse();
        $response->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->exactly(2))
            ->method('listSites')
            ->willReturn($response);

        $this->client->setProperty('https://example.com/');
        $this->assertTrue($this->client->hasProperty());

        // Then try to set an invalid one
        try {
            $this->client->setProperty('https://invalid.com/');
        } catch (InvalidArgumentException) {
            // Verify the original property remains unchanged
            $this->assertTrue($this->client->hasProperty());
            $this->assertEquals('https://example.com/', $this->client->getProperty());
        }
    }

    /**
     * @dataProvider domainPropertyProvider
     */
    public function testIsDomainProperty(string $url, bool $expected): void
    {
        $this->assertSame($expected, $this->client->isDomainProperty($url));
    }

    public static function domainPropertyProvider(): array
    {
        return [
            'domain property' => ['sc-domain:example.com', true],
            'http url' => ['http://example.com', false],
            'https url' => ['https://example.com', false],
            'https url with slash' => ['https://example.com/', false],
            'empty string' => ['', false],
            'partial match' => ['mysc-domain:example.com', false],
            'case sensitive' => ['SC-DOMAIN:example.com', false],
            'domain with sc-domain in name' => ['https://foobarsc-domain.com:8080', false],
            'domain with sc.domain in name' => ['https://foobarsc.domain:1234', false],
            'domain starting with sc.domain' => ['https://sc.domain:443', false],
            'domain with sc-doma.in' => ['https://sc-doma.in:8080', false],
            'domain property with port' => ['sc-domain:example.com:8080', true],
            'domain property with subdomain' => ['sc-domain:sub.example.com', true],
        ];
    }

    public function testSetDates(): void
    {
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');

        $result = $this->client->setDates($startDate, $endDate);

        $this->assertSame($this->client, $result);
        $this->assertTrue($this->client->hasDates());
        $this->assertEquals($startDate, $this->client->getStartDate());
        $this->assertEquals($endDate, $this->client->getEndDate());
    }

    public function testSetDatesWithInvalidRangeThrowsException(): void
    {
        $startDate = new DateTime('2024-12-31');
        $endDate = new DateTime('2024-01-01');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End date cannot be before start date.');

        $this->client->setDates($startDate, $endDate);
    }

    public function testGetDatesWithBothDatesSet(): void
    {
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');

        $this->client->setStartDate($startDate)
            ->setEndDate($endDate);

        $dates = $this->client->getDates();

        $this->assertArrayHasKey('start', $dates);
        $this->assertArrayHasKey('end', $dates);
        $this->assertEquals($startDate, $dates['start']);
        $this->assertEquals($endDate, $dates['end']);
    }

    public function testGetDatesWithNoDatesSet(): void
    {
        $dates = $this->client->getDates();

        $this->assertArrayHasKey('start', $dates);
        $this->assertArrayHasKey('end', $dates);
        $this->assertNull($dates['start']);
        $this->assertNull($dates['end']);
    }

    public function testHasDatesWithBothDatesSet(): void
    {
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');

        $this->client->setStartDate($startDate)
            ->setEndDate($endDate);

        $this->assertTrue($this->client->hasDates());
    }

    public function testHasDatesWithOnlyStartDateSet(): void
    {
        $startDate = new DateTime('2024-01-01');
        $this->client->setStartDate($startDate);

        $this->assertFalse($this->client->hasDates());
    }

    public function testHasDatesWithOnlyEndDateSet(): void
    {
        $endDate = new DateTime('2024-12-31');
        $this->client->setEndDate($endDate);

        $this->assertFalse($this->client->hasDates());
    }

    public function testHasDatesWithNoDatesSet(): void
    {
        $this->assertFalse($this->client->hasDates());
    }

    public function testRowLimitNormalization(): void
    {
        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'normalizeRowLimit');
        $reflection->setAccessible(true);

        // Test default value
        $this->assertEquals(5000, $reflection->invoke($this->client, null));

        // Test zero and negative values
        $this->assertEquals(0, $reflection->invoke($this->client, 0));
        $this->assertEquals(0, $reflection->invoke($this->client, -1));

        // Test value within bounds
        $this->assertEquals(10000, $reflection->invoke($this->client, 10000));

        // Test maximum value
        $this->assertEquals(25000, $reflection->invoke($this->client, 25000));

        // Test value exceeding maximum
        $this->assertEquals(25000, $reflection->invoke($this->client, 30000));
    }

    public function testSearchAnalyticsQueryRequestWithMaxRows(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        // Configure mock to return our properties
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Configure test site
        $this->client->setProperty('https://example.com/');
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-01'));

        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'getNewSearchAnalyticsQueryRequest');
        $reflection->setAccessible(true);

        // Create request with maximum rows
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE, Dimension::QUERY],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            25000
        );

        // Verify row limit is set to maximum
        $this->assertEquals(25000, $request->getRowLimit());
    }

    public function testSetCountry(): void
    {
        // Test valid country code
        $result = $this->client->setCountry('USA');
        $this->assertSame($this->client, $result);
        $this->assertEquals('USA', $this->client->getCountry());

        // Test case normalization
        $result = $this->client->setCountry('gbr');
        $this->assertSame($this->client, $result);
        $this->assertEquals('GBR', $this->client->getCountry());

        // Test invalid length country code
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be a valid ISO-3166-1-Alpha-3 code (3 uppercase letters)');
        $this->client->setCountry('US');
    }

    public function testClearCountry(): void
    {
        // Set and verify country
        $this->client->setCountry('USA');
        $this->assertEquals('USA', $this->client->getCountry());

        // Test clearing filter
        $this->client->setCountry(null);
        $this->assertNull($this->client->getCountry());
    }

    public function testSetDevice(): void
    {
        // Test setting each device type as enum
        $result = $this->client->setDevice(DeviceType::DESKTOP);
        $this->assertSame($this->client, $result);
        $this->assertEquals('DESKTOP', $this->client->getDevice());

        $result = $this->client->setDevice(DeviceType::MOBILE);
        $this->assertSame($this->client, $result);
        $this->assertEquals('MOBILE', $this->client->getDevice());

        $result = $this->client->setDevice(DeviceType::TABLET);
        $this->assertSame($this->client, $result);
        $this->assertEquals('TABLET', $this->client->getDevice());

        // Test setting each device type as string
        $result = $this->client->setDevice('DESKTOP');
        $this->assertSame($this->client, $result);
        $this->assertEquals('DESKTOP', $this->client->getDevice());

        $result = $this->client->setDevice('mobile');
        $this->assertSame($this->client, $result);
        $this->assertEquals('MOBILE', $this->client->getDevice());

        $result = $this->client->setDevice('TABLET');
        $this->assertSame($this->client, $result);
        $this->assertEquals('TABLET', $this->client->getDevice());

        // Test clearing filter
        $result = $this->client->setDevice(null);
        $this->assertSame($this->client, $result);
        $this->assertNull($this->client->getDevice());
    }

    public function testSetInvalidDevice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Device type must be one of: DESKTOP, MOBILE, TABLET');
        $this->client->setDevice('INVALID');
    }

    public function testSetDataState(): void
    {
        // Test initial state is null
        $this->assertNull($this->client->getDataState());

        // Test setting each data state
        $result = $this->client->setDataState(DataState::FINAL);
        $this->assertSame($this->client, $result);
        $this->assertEquals(DataState::FINAL, $this->client->getDataState());

        $result = $this->client->setDataState(DataState::ALL);
        $this->assertSame($this->client, $result);
        $this->assertEquals(DataState::ALL, $this->client->getDataState());

        // Test clearing data state
        $result = $this->client->setDataState(null);
        $this->assertSame($this->client, $result);
        $this->assertNull($this->client->getDataState());
    }

    public function testSearchAnalyticsQueryRequestWithAllFilters(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        // Configure mock to return our properties
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Configure test site and dates
        $this->client->setProperty('https://example.com/');
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-01'));

        // Set all filters
        $this->client->setCountry('USA');
        $this->client->setDevice(DeviceType::DESKTOP);
        $this->client->setSearchType('WEB');

        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'getNewSearchAnalyticsQueryRequest');
        $reflection->setAccessible(true);

        // Create request with all filters
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE, Dimension::QUERY, Dimension::COUNTRY, Dimension::DEVICE],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000
        );

        // Verify dimensions include country and device
        $this->assertContains('country', $request->getDimensions());
        $this->assertContains('device', $request->getDimensions());

        // Verify filter groups are set
        $filterGroups = $request->getDimensionFilterGroups();
        $this->assertNotEmpty($filterGroups);

        // Verify country filter
        $countryFilter = null;
        $deviceFilter = null;
        foreach ($filterGroups as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getDimension() === 'country') {
                    $countryFilter = $filter;
                } elseif ($filter->getDimension() === 'device') {
                    $deviceFilter = $filter;
                }
            }
        }

        $this->assertNotNull($countryFilter);
        $this->assertEquals('USA', $countryFilter->getExpression());
        $this->assertEquals('equals', $countryFilter->getOperator());

        $this->assertNotNull($deviceFilter);
        $this->assertEquals('DESKTOP', $deviceFilter->getExpression());
        $this->assertEquals('equals', $deviceFilter->getOperator());

        // Verify search type is set
        $this->assertEquals('WEB', $request->getType());
    }

    public function testSetSearchType(): void
    {
        // Test setting search type
        $result = $this->client->setSearchType('WEB');
        $this->assertSame($this->client, $result);
        $this->assertEquals('WEB', $this->client->getSearchType());

        // Test lowercase conversion
        $result = $this->client->setSearchType('news');
        $this->assertSame($this->client, $result);
        $this->assertEquals('NEWS', $this->client->getSearchType());

        // Test clearing search type
        $result = $this->client->setSearchType(null);
        $this->assertSame($this->client, $result);
        $this->assertNull($this->client->getSearchType());
    }

    public function testSearchAnalyticsQueryRequestWithMixedDimensionTypes(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        // Configure mock to return our properties
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Configure test site and dates
        $this->client->setProperty('https://example.com/');
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-01'));

        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'getNewSearchAnalyticsQueryRequest');
        $reflection->setAccessible(true);

        // Create request with mixed dimension types (enum and string)
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE, 'query', Dimension::COUNTRY, 'device'],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000
        );

        // Verify dimensions are correctly converted to lowercase strings
        $dimensions = $request->getDimensions();
        $this->assertContains('date', $dimensions);
        $this->assertContains('query', $dimensions);
        $this->assertContains('country', $dimensions);
        $this->assertContains('device', $dimensions);
    }

    public function testSearchAnalyticsQueryRequestWithInvalidDimension(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        // Configure mock to return our properties
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Configure test site and dates
        $this->client->setProperty('https://example.com/');
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-01'));

        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'getNewSearchAnalyticsQueryRequest');
        $reflection->setAccessible(true);

        // Test with invalid dimension type
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimensions must be either strings or Dimension enum values');

        $reflection->invoke(
            $this->client,
            [Dimension::DATE, 123], // Invalid dimension type
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000
        );
    }

    public function testSearchAnalyticsQueryRequestWithNoFilters(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        // Configure mock to return our properties
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Configure test site and dates
        $this->client->setProperty('https://example.com/');
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-01'));

        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'getNewSearchAnalyticsQueryRequest');
        $reflection->setAccessible(true);

        // Create request without any filters
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE, Dimension::QUERY],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000
        );

        // Verify no filter groups are set
        $this->assertEmpty($request->getDimensionFilterGroups());

        // Verify no search type is set
        $this->assertNull($request->getType());
    }

    public function testSearchAnalyticsQueryRequestWithPartialFilters(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        // Configure mock to return our properties
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Configure test site and dates
        $this->client->setProperty('https://example.com/');
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-01'));

        // Set only country and search type
        $this->client->setCountry('USA');
        $this->client->setSearchType('WEB');

        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'getNewSearchAnalyticsQueryRequest');
        $reflection->setAccessible(true);

        // Create request with partial filters
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE, Dimension::QUERY, Dimension::COUNTRY],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000
        );

        // Verify filter groups are set
        $filterGroups = $request->getDimensionFilterGroups();
        $this->assertNotEmpty($filterGroups);

        // Verify only country filter exists
        $countryFilter = null;
        foreach ($filterGroups as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getDimension() === 'country') {
                    $countryFilter = $filter;
                }
            }
        }

        $this->assertNotNull($countryFilter);
        $this->assertEquals('USA', $countryFilter->getExpression());
        $this->assertEquals('equals', $countryFilter->getOperator());

        // Verify search type is set
        $this->assertEquals('WEB', $request->getType());
    }

    public function testSearchAnalyticsQueryRequestWithDataState(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        // Configure mock to return our properties
        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Configure test site and dates
        $this->client->setProperty('https://example.com/');
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-01'));

        // Get access to private method
        $reflection = new ReflectionMethod(GscApiClient::class, 'getNewSearchAnalyticsQueryRequest');
        $reflection->setAccessible(true);

        // Test 1: No data state set (should be null)
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000
        );
        $this->assertNull($request->getDataState());

        // Test 2: Instance data state set
        $this->client->setDataState(DataState::ALL);
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000
        );
        $this->assertEquals('all', $request->getDataState());

        // Test 3: Parameter overrides instance data state
        $request = $reflection->invoke(
            $this->client,
            [Dimension::DATE],
            new DateTime('2024-01-01'),
            new DateTime('2024-01-01'),
            5000,
            null, // startRow
            [], // filters
            null, // aggregationType
            DataState::FINAL // dataState parameter
        );
        $this->assertEquals('final', $request->getDataState());
    }


    public function testGetFirstDateWithDataReturnsFirstDate(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Set up client with property and dates
        $this->client->setProperty('https://example.com/')
            ->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-31'));

        // Create mock API response data
        $mockRow = $this->createMock(SearchConsole\ApiDataRow::class);
        $mockRow->expects($this->once())
            ->method('getKeys')
            ->willReturn(['2024-01-15']);

        $mockResponse = $this->createMock(SearchConsole\SearchAnalyticsQueryResponse::class);
        $mockResponse->expects($this->once())
            ->method('getRows')
            ->willReturn([$mockRow]);

        // Configure searchanalytics mock to return our response
        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($mockResponse);

        // Execute method
        $result = $this->client->getFirstDateWithData();

        // Verify result
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
    }


    public function testGetFirstDateWithDataReturnsNullWhenNoData(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Set up client with property and dates
        $this->client->setProperty('https://example.com/')
            ->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-31'));

        // Create mock API response with no data
        $mockResponse = $this->createMock(SearchConsole\SearchAnalyticsQueryResponse::class);
        $mockResponse->expects($this->once())
            ->method('getRows')
            ->willReturn([]);

        // Configure searchanalytics mock to return empty response
        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($mockResponse);

        // Execute method
        $result = $this->client->getFirstDateWithData();

        // Verify result is null
        $this->assertNull($result);
    }


    public function testGetFirstDateWithDataThrowsExceptionWhenNoPropertySet(): void
    {
        // Set dates but no property
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-31'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Property must be set before querying data');

        $this->client->getFirstDateWithData();
    }


    public function testGetFirstDateWithDataUsesDefaultRangeWhenNoDatesSet(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Set property but no dates
        $this->client->setProperty('https://example.com/');

        // Create mock API response data
        $mockRow = $this->createMock(SearchConsole\ApiDataRow::class);
        $mockRow->expects($this->once())
            ->method('getKeys')
            ->willReturn(['2024-01-15']);

        $mockResponse = $this->createMock(SearchConsole\SearchAnalyticsQueryResponse::class);
        $mockResponse->expects($this->once())
            ->method('getRows')
            ->willReturn([$mockRow]);

                // Configure searchanalytics mock to return our response
        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo('https://example.com/'),
                $this->callback(function($request) {
                                        // Verify that the request uses default date range (18 months)
                    $startDate = new DateTime($request->getStartDate());
                    $endDate = new DateTime($request->getEndDate());
                    $now = new DateTime('now');
                    $expectedStart = (clone $now)->modify('-18 months');

                    // Allow for small time differences due to test execution time
                    $startDiff = abs($startDate->getTimestamp() - $expectedStart->getTimestamp());
                    $endDiff = abs($endDate->getTimestamp() - $now->getTimestamp());

                    return $startDiff <= 86400 && $endDiff <= 86400; // Within 1 day tolerance
                })
            )
            ->willReturn($mockResponse);

        // Execute method - should work without throwing exception
        $result = $this->client->getFirstDateWithData();

        // Verify result
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
    }


    public function testGetFirstDateWithDataUsesProvidedDates(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Set up client with property and different instance dates
        $this->client->setProperty('https://example.com/')
            ->setDates(new DateTime('2023-01-01'), new DateTime('2023-12-31'));

        // Create mock API response data
        $mockRow = $this->createMock(SearchConsole\ApiDataRow::class);
        $mockRow->expects($this->once())
            ->method('getKeys')
            ->willReturn(['2024-02-10']);

        $mockResponse = $this->createMock(SearchConsole\SearchAnalyticsQueryResponse::class);
        $mockResponse->expects($this->once())
            ->method('getRows')
            ->willReturn([$mockRow]);

        // Configure searchanalytics mock to return our response
        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($mockResponse);

        // Execute method with custom dates
        $customStartDate = new DateTime('2024-02-01');
        $customEndDate = new DateTime('2024-02-28');
        $result = $this->client->getFirstDateWithData($customStartDate, $customEndDate);

        // Verify result uses the returned date
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-02-10', $result->format('Y-m-d'));
    }


    public function testGetFirstDateWithDataUsesDefaultRangeWhenNullDatesProvided(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Set property but no instance dates
        $this->client->setProperty('https://example.com/');

        // Create mock API response data
        $mockRow = $this->createMock(SearchConsole\ApiDataRow::class);
        $mockRow->expects($this->once())
            ->method('getKeys')
            ->willReturn(['2024-01-20']);

        $mockResponse = $this->createMock(SearchConsole\SearchAnalyticsQueryResponse::class);
        $mockResponse->expects($this->once())
            ->method('getRows')
            ->willReturn([$mockRow]);

        // Configure searchanalytics mock to return our response
        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($mockResponse);

        // Execute method with explicit null dates - should use default range
        $result = $this->client->getFirstDateWithData(null, null);

        // Verify result
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-01-20', $result->format('Y-m-d'));
    }


    public function testGetFirstDateWithDataUsesOnlyValidInstanceDate(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Set property and only valid start date
        $this->client->setProperty('https://example.com/')
            ->setStartDate(new DateTime('2024-01-01'));
        // End date remains null

        // Create mock API response data
        $mockRow = $this->createMock(SearchConsole\ApiDataRow::class);
        $mockRow->expects($this->once())
            ->method('getKeys')
            ->willReturn(['2024-01-05']);

        $mockResponse = $this->createMock(SearchConsole\SearchAnalyticsQueryResponse::class);
        $mockResponse->expects($this->once())
            ->method('getRows')
            ->willReturn([$mockRow]);

        // Configure searchanalytics mock to return our response
        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo('https://example.com/'),
                $this->callback(function($request) {
                    // Verify that start date is instance date and end date is default (now)
                    $startDate = new DateTime($request->getStartDate());
                    $endDate = new DateTime($request->getEndDate());
                    $now = new DateTime('now');
                    $expectedStart = new DateTime('2024-01-01');

                    // Allow for small time differences
                    $startDiff = abs($startDate->getTimestamp() - $expectedStart->getTimestamp());
                    $endDiff = abs($endDate->getTimestamp() - $now->getTimestamp());

                    return $startDiff <= 86400 && $endDiff <= 86400; // Within 1 day tolerance
                })
            )
            ->willReturn($mockResponse);

        // Execute method
        $result = $this->client->getFirstDateWithData();

        // Verify result
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-01-05', $result->format('Y-m-d'));
    }


    public function testGetFirstDateWithDataUsesProvidedValidStartDateAndDefaultEndDate(): void
    {
        // Create mock response for properties
        $propertiesResponse = new SitesListResponse();
        $propertiesResponse->setSiteEntry([$this->testSite]);

        $this->sites->expects($this->once())
            ->method('listSites')
            ->willReturn($propertiesResponse);

        // Set property but no instance dates
        $this->client->setProperty('https://example.com/');

        // Create mock API response data
        $mockRow = $this->createMock(SearchConsole\ApiDataRow::class);
        $mockRow->expects($this->once())
            ->method('getKeys')
            ->willReturn(['2024-02-15']);

        $mockResponse = $this->createMock(SearchConsole\SearchAnalyticsQueryResponse::class);
        $mockResponse->expects($this->once())
            ->method('getRows')
            ->willReturn([$mockRow]);

        // Configure searchanalytics mock to return our response
        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($mockResponse);

        // Execute method with valid start date but null end date
        $customStartDate = new DateTime('2024-02-01');
        $result = $this->client->getFirstDateWithData($customStartDate, null);

        // Verify result
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-02-15', $result->format('Y-m-d'));
    }
}

/**
 * Mock class for SearchAnalyticsRow since we can't access the actual Google class in tests
 */
class SearchAnalyticsQueryResponse_Row
{
    public array $keys = [];
    public int $clicks = 0;
    public int $impressions = 0;
    public float $position = 0.0;

    public function getKeys(): array
    {
        return $this->keys;
    }

    public function getClicks(): int
    {
        return $this->clicks;
    }

    public function getImpressions(): int
    {
        return $this->impressions;
    }

    public function getPosition(): float
    {
        return $this->position;
    }
}
