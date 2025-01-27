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

class GoogleSearchConsoleClientTest extends TestCase
{
    private GoogleSearchConsoleClient $client;
    private MockObject&Client $googleClient;
    private MockObject&SearchConsole $searchConsole;
    private MockObject&SearchConsole\Resource\Sites $sites;

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
    }

    public function testGetPropertiesReturnsArrayOfSites(): void
    {
        // Create mock response
        $site = new WmxSite();
        $site->setSiteUrl('https://example.com/');

        $response = new SitesListResponse();
        $response->setSiteEntry([$site]);

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
}
