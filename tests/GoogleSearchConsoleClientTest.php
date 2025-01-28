<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient\Tests;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SitesListResponse;
use Google\Service\SearchConsole\WmxSite;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Google\Service\SearchConsole\SearchAnalyticsQueryResponse;
use Google\Service\SearchConsole\SearchAnalyticsRow;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Abromeit\GoogleSearchConsoleClient\GoogleSearchConsoleClient;
use Abromeit\GoogleSearchConsoleClient\Enums\TimeframeResolution;
use InvalidArgumentException;
use DateTime;
use DateTimeInterface;
use stdClass;

class GoogleSearchConsoleClientTest extends TestCase
{
    private GoogleSearchConsoleClient $client;
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
        $this->client = new GoogleSearchConsoleClient($this->googleClient);

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

    public function testGetSearchPerformanceWithoutPropertyThrowsException(): void
    {
        $this->client->setDates(new DateTime('2024-01-01'), new DateTime('2024-01-31'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No property set. Call setProperty() first.');

        $this->client->getSearchPerformance();
    }

    public function testGetSearchPerformanceWithoutDatesThrowsException(): void
    {
        $this->setUpValidProperty();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No dates set. Call setDates() first.');

        $this->client->getSearchPerformance();
    }

    public function testGetSearchPerformanceWithEmptyResponse(): void
    {
        $this->setUpValidPropertyAndDates();

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn(null);

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $result = $this->client->getSearchPerformance();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSearchPerformanceWithDailyResolution(): void
    {
        $this->setUpValidPropertyAndDates();

        $rows = [
            $this->createSearchAnalyticsRow('2024-01-01', 100, 1000, 1.5),  // CTR: 0.1, good position
            $this->createSearchAnalyticsRow('2024-01-01', 50, 200, 4.0),    // CTR: 0.25, poor position
            $this->createSearchAnalyticsRow('2024-01-02', 300, 500, 1.0),   // CTR: 0.6, excellent position
        ];

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn($rows);

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $result = $this->client->getSearchPerformance(TimeframeResolution::DAILY);

        $this->assertCount(2, $result);

        // Check first day aggregation
        $this->assertEquals('2024-01-01', $result[0]['date']);
        $this->assertEquals(150, $result[0]['clicks']);
        $this->assertEquals(1200, $result[0]['impressions']);
        $this->assertEquals(0.125, $result[0]['ctr']);  // (100 + 50) / (1000 + 200)
        $this->assertEquals(1.917, round($result[0]['position'], 3));  // (1.5*1000 + 4.0*200) / 1200

        // Check second day
        $this->assertEquals('2024-01-02', $result[1]['date']);
        $this->assertEquals(300, $result[1]['clicks']);
        $this->assertEquals(500, $result[1]['impressions']);
        $this->assertEquals(0.6, $result[1]['ctr']);
        $this->assertEquals(1.0, $result[1]['position']);
    }

    public function testGetSearchPerformanceWithWeeklyResolution(): void
    {
        $this->setUpValidPropertyAndDates();

        $rows = [
            $this->createSearchAnalyticsRow('2024-01-01', 100, 1000, 1.5),  // Week 1
            $this->createSearchAnalyticsRow('2024-01-02', 200, 2000, 2.0),  // Week 1
            $this->createSearchAnalyticsRow('2024-01-08', 300, 3000, 1.0),  // Week 2
        ];

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn($rows);

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $result = $this->client->getSearchPerformance(TimeframeResolution::WEEKLY);

        $this->assertCount(2, $result);

        // Check first week aggregation
        $this->assertEquals('2024-CW01', $result[0]['date']);
        $this->assertEquals(300, $result[0]['clicks']);
        $this->assertEquals(3000, $result[0]['impressions']);
        $this->assertEquals(0.1, $result[0]['ctr']);
        $this->assertEquals(1.833, round($result[0]['position'], 3));

        // Check second week
        $this->assertEquals('2024-CW02', $result[1]['date']);
        $this->assertEquals(300, $result[1]['clicks']);
        $this->assertEquals(3000, $result[1]['impressions']);
        $this->assertEquals(0.1, $result[1]['ctr']);
        $this->assertEquals(1.0, $result[1]['position']);
    }

    public function testGetSearchPerformanceWithMonthlyResolution(): void
    {
        $this->setUpValidPropertyAndDates();

        $rows = [
            $this->createSearchAnalyticsRow('2024-01-01', 10, 100, 1.5),
            $this->createSearchAnalyticsRow('2024-01-15', 20, 200, 1.0),
            $this->createSearchAnalyticsRow('2024-02-01', 30, 300, 2.0),
        ];

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn($rows);

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $result = $this->client->getSearchPerformance(TimeframeResolution::MONTHLY);

        $this->assertCount(2, $result);

        // Check January aggregation
        $this->assertEquals('2024-01', $result[0]['date']);
        $this->assertEquals(30, $result[0]['clicks']);
        $this->assertEquals(300, $result[0]['impressions']);
        $this->assertEquals(0.1, $result[0]['ctr']);
        $this->assertEquals(1.167, round($result[0]['position'], 3));

        // Check February
        $this->assertEquals('2024-02', $result[1]['date']);
        $this->assertEquals(30, $result[1]['clicks']);
        $this->assertEquals(300, $result[1]['impressions']);
        $this->assertEquals(0.1, $result[1]['ctr']);
        $this->assertEquals(2.0, $result[1]['position']);
    }

    public function testGetSearchPerformanceWithAlloverResolution(): void
    {
        $this->setUpValidPropertyAndDates();

        $rows = [
            $this->createSearchAnalyticsRow('2024-01-01', 10, 100, 1.5),
            $this->createSearchAnalyticsRow('2024-01-15', 20, 200, 1.0),
            $this->createSearchAnalyticsRow('2024-02-01', 30, 300, 2.0),
        ];

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn($rows);

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $result = $this->client->getSearchPerformance(TimeframeResolution::ALLOVER);

        $this->assertCount(1, $result);

        // Check total aggregation
        $this->assertEquals('allover', $result[0]['date']);
        $this->assertEquals(60, $result[0]['clicks']);
        $this->assertEquals(600, $result[0]['impressions']);
        $this->assertEquals(0.1, $result[0]['ctr']);
        $this->assertEquals(1.583, round($result[0]['position'], 3));
    }

    public function testGetSearchPerformanceKeywords(): void
    {
        $this->setUpValidPropertyAndDates();

        $rows = [
            // High CTR keyword with good position
            $this->createSearchAnalyticsRow('2024-01-01', 50, 100, 1.2, ['2024-01-01', 'buy product']),
            // Low CTR keyword with poor position
            $this->createSearchAnalyticsRow('2024-01-01', 5, 1000, 8.5, ['2024-01-01', 'unrelated term']),
            // Medium CTR keyword with medium position
            $this->createSearchAnalyticsRow('2024-01-02', 100, 500, 3.0, ['2024-01-02', 'buy product']),
        ];

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn($rows);

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $result = $this->client->getSearchPerformanceKeywords();

        $this->assertCount(2, $result);

        // Check first day
        $this->assertEquals('2024-01-01', $result[0]['date']);
        $this->assertEquals(55, $result[0]['clicks']);
        $this->assertEquals(1100, $result[0]['impressions']);
        $this->assertEquals(0.05, $result[0]['ctr']);
        $this->assertEquals(7.836, round($result[0]['position'], 3));  // (1.2*100 + 8.5*1000) / 1100
        $this->assertEquals(['buy product', 'unrelated term'], $result[0]['keys']);

        // Check second day
        $this->assertEquals('2024-01-02', $result[1]['date']);
        $this->assertEquals(100, $result[1]['clicks']);
        $this->assertEquals(500, $result[1]['impressions']);
        $this->assertEquals(0.2, $result[1]['ctr']);
        $this->assertEquals(3.0, $result[1]['position']);
        $this->assertEquals(['buy product'], $result[1]['keys']);
    }

    public function testGetSearchPerformanceUrls(): void
    {
        $this->setUpValidPropertyAndDates();

        $rows = [
            // Homepage with good metrics
            $this->createSearchAnalyticsRow('2024-01-01', 500, 1000, 1.1, ['2024-01-01', '/']),
            // Product page with medium metrics
            $this->createSearchAnalyticsRow('2024-01-01', 50, 200, 2.5, ['2024-01-01', '/products/123']),
            // Blog post with poor metrics
            $this->createSearchAnalyticsRow('2024-01-02', 10, 1000, 15.0, ['2024-01-02', '/blog/post-1']),
        ];

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn($rows);

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $result = $this->client->getSearchPerformanceUrls();

        $this->assertCount(2, $result);

        // Check first day
        $this->assertEquals('2024-01-01', $result[0]['date']);
        $this->assertEquals(550, $result[0]['clicks']);
        $this->assertEquals(1200, $result[0]['impressions']);
        $this->assertEquals(0.458, round($result[0]['ctr'], 3));
        $this->assertEquals(1.333, round($result[0]['position'], 3));  // (1.1*1000 + 2.5*200) / 1200
        $this->assertEquals(['/', '/products/123'], $result[0]['keys']);

        // Check second day
        $this->assertEquals('2024-01-02', $result[1]['date']);
        $this->assertEquals(10, $result[1]['clicks']);
        $this->assertEquals(1000, $result[1]['impressions']);
        $this->assertEquals(0.01, $result[1]['ctr']);
        $this->assertEquals(15.0, $result[1]['position']);
        $this->assertEquals(['/blog/post-1'], $result[1]['keys']);
    }

    public function testExecuteSearchQueryWithEmptyDimensionsThrowsException(): void
    {
        $this->setUpValidPropertyAndDates();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimensions array cannot be empty.');

        $this->searchanalytics->expects($this->never())
            ->method('query');

        $reflectionClass = new \ReflectionClass(GoogleSearchConsoleClient::class);
        $method = $reflectionClass->getMethod('executeSearchQuery');
        $method->setAccessible(true);
        $method->invoke($this->client, []);
    }

    public function testGetSearchPerformanceWithNullResolutionDefaultsToDaily(): void
    {
        $this->setUpValidPropertyAndDates();

        $rows = [
            $this->createSearchAnalyticsRow('2024-01-01', 10, 100, 1.5),
            $this->createSearchAnalyticsRow('2024-01-02', 20, 200, 1.0),
        ];

        $response = $this->createMock(SearchAnalyticsQueryResponse::class);
        $response->method('getRows')->willReturn($rows);

        $this->searchanalytics->expects($this->exactly(2))
            ->method('query')
            ->willReturn($response);

        $resultWithNull = $this->client->getSearchPerformance(null);
        $resultWithDaily = $this->client->getSearchPerformance(TimeframeResolution::DAILY);

        $this->assertEquals($resultWithDaily, $resultWithNull);
    }

    public function testExecuteSearchQuerySetsCorrectRequestParameters(): void
    {
        $this->setUpValidPropertyAndDates();

        $this->searchanalytics->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo('https://example.com/'),
                $this->callback(function (SearchAnalyticsQueryRequest $request) {
                    return $request->getStartDate() === '2024-01-01'
                        && $request->getEndDate() === '2024-01-31'
                        && $request->getDimensions() === ['date'];
                })
            )
            ->willReturn($this->createMock(SearchAnalyticsQueryResponse::class));

        $this->client->getSearchPerformance();
    }

    private function setUpValidProperty(): void
    {
        $response = new SitesListResponse();
        $response->setSiteEntry([$this->testSite]);

        $this->sites->method('listSites')
            ->willReturn($response);

        $this->client->setProperty('https://example.com/');
    }

    private function setUpValidPropertyAndDates(): void
    {
        $this->setUpValidProperty();
        $this->client->setDates(
            new DateTime('2024-01-01'),
            new DateTime('2024-01-31')
        );
    }

    private function createSearchAnalyticsRow(
        string $date,
        int $clicks,
        int $impressions,
        float $position,
        array $keys = []
    ): SearchAnalyticsQueryResponse_Row {
        $row = new SearchAnalyticsQueryResponse_Row();
        $row->keys = $keys ?: [$date];
        $row->clicks = $clicks;
        $row->impressions = $impressions;
        $row->position = $position;
        return $row;
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
