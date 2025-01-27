<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SitesListResponse;
use Google\Service\SearchConsole\WmxSite;

class GoogleSearchConsoleClient
{
    private SearchConsole $searchConsole;

    public function __construct(
        private readonly Client $client
    ) {
        $this->searchConsole = new SearchConsole($this->client);
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

    public function getSearchPerformance(){

    }
}
