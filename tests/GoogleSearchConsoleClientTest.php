<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient\Tests;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SitesListResponse;
use Google\Service\SearchConsole\WmxSite;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Abromeit\GoogleSearchConsoleClient\GoogleSearchConsoleClient;
use InvalidArgumentException;
use DateTime;
use DateTimeInterface;

class GoogleSearchConsoleClientTest extends TestCase
{
    private GoogleSearchConsoleClient $client;
    private MockObject&Client $googleClient;
    private MockObject&SearchConsole $searchConsole;
    private MockObject&SearchConsole\Resource\Sites $sites;
    private WmxSite $testSite;

    protected function setUp(): void
    {
        $this->googleClient = $this->createMock(Client::class);

        // Create nested mocks for SearchConsole service
        $this->searchConsole = $this->createMock(SearchConsole::class);
        $this->sites = $this->createMock(SearchConsole\Resource\Sites::class);
        $this->searchConsole->sites = $this->sites;

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
}
