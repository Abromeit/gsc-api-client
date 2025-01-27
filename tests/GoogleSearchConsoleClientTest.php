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
}
