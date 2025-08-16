<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Abromeit\GscApiClient\GscApiClient;
use Abromeit\GscApiClient\Enums\GSCDataState as DataState;
use Abromeit\GscApiClient\Enums\GSCDimension as Dimension;
use Google\Client;


/**
 * Display top keywords data with a nice formatted table
 */
function displayTopKeywords(GscApiClient $cli, string $dataStateLabel, int $topN = 3): void
{
    $keywords = $cli->getTopKeywordsByDay($topN);

    echo "=== Top {$topN} Keywords per day ({$dataStateLabel}) for {$cli->getProperty()} ===\n";
    echo str_repeat('-', 100) . "\n";
    echo sprintf("%-10s %-30s %10s %15s %10s\n", 'Date', 'Keyword', 'Clicks', 'Impressions', 'CTR (%)');
    echo str_repeat('-', 100) . "\n";

    $rowCount = 0;
    foreach ($keywords as $row) {
        // Avoid division by zero
        $ctr = $row['impressions'] > 0 ? ($row['clicks'] / $row['impressions']) * 100 : 0;

        echo sprintf(
            "%-10s %-30s %10d %15d %10.1f\n",
            $row['data_date'],
            strlen($row['query']) > 29 ? mb_substr($row['query'], 0, 26) . '...' : $row['query'],
            $row['clicks'],
            $row['impressions'],
            $ctr
        );
        $rowCount++;
    }

    if ($rowCount === 0) {
        echo "No data found for this period with {$dataStateLabel} data state.\n";
    }

    echo str_repeat('-', 100) . "\n";
    echo "Total rows: {$rowCount}\n\n";
}

// Path to your service account JSON file
$serviceAccountFile = __DIR__ . '/../credentials/service-account.json';

// Initialize with service account
$googleClient = new Client();
$googleClient->setApplicationName('GSC API Client - Fresh Data Example');
$googleClient->setScopes(['https://www.googleapis.com/auth/webmasters.readonly']);
$googleClient->setAuthConfig($serviceAccountFile);

// Initialize GSC API Client
$cli = new GscApiClient($googleClient);

// Get available properties
$properties = $cli->getProperties();
if (empty($properties)) {
    die("No properties found. Please make sure the service account has access to some properties.\n");
}

// Get property from command line or select random one
$propertyUrl = null;
if (isset($argv[1])) {
    $requestedUrl = rtrim($argv[1], '/') . '/';
    foreach ($properties as $property) {
        if ($property->getSiteUrl() === $requestedUrl) {
            $propertyUrl = $requestedUrl;
            break;
        }
    }
    if ($propertyUrl === null) {
        die("Property '{$requestedUrl}' not found or not accessible.\n");
    }
} else {
    // Select a random property.
    // List of properties returned by GSC is usually rnd anyway, but hey.
    $randomProperty = $properties[array_rand($properties)];
    $propertyUrl = $randomProperty->getSiteUrl();
}

$cli->setProperty($propertyUrl);

// Set date range (last 7 days)
$endDate = new DateTime('today');
$startDate = new DateTime('3 days ago');
$cli->setDates($startDate, $endDate);

echo "=== GSC API Fresh Data Example ===\n\n";

// Example 1: Get final/complete data only (default behavior)
echo "1. Getting FINAL data (default - complete data only):\n";
$cli->setDataState(DataState::FINAL);

$request = $cli->getNewSearchAnalyticsQueryRequest(
    dimensions: [Dimension::DATE],
    rowLimit: 10
);

// You would execute this with your search console service
echo "Request with dataState: " . ($request->getDataState() ?? 'null (API default)') . "\n\n";

// Example 2: Get fresh data including incomplete data
echo "2. Getting FRESH data (includes incomplete/current data):\n";
$cli->setDataState(DataState::ALL);

$request = $cli->getNewSearchAnalyticsQueryRequest(
    dimensions: [Dimension::DATE],
    rowLimit: 10
);

echo "Request with dataState: " . $request->getDataState() . "\n\n";

// Example 3: Override instance dataState with method parameter
echo "3. Overriding instance dataState with method parameter:\n";
$cli->setDataState(DataState::ALL); // Instance is set to ALL

$request = $cli->getNewSearchAnalyticsQueryRequest(
    dimensions: [Dimension::DATE],
    rowLimit: 10,
    dataState: DataState::FINAL  // Override with FINAL for this specific request
);

echo "Instance dataState: " . $cli->getDataState()->value . "\n";
echo "Request dataState: " . $request->getDataState() . "\n\n";

// Example 4: Using with existing methods (they will use instance dataState)
echo "4. Using fresh data with existing generator methods:\n";
$cli->setDataState(DataState::ALL);
echo "DataState is now set to: " . $cli->getDataState()->value . "\n";
echo "All subsequent API calls (getTopKeywordsByDay, getSearchPerformanceByUrl, etc.) will use fresh data!\n\n";

// Compare FINAL vs FRESH data
echo "=== DATA COMPARISON: Final vs Fresh ===\n\n";

// Get FINAL (stale) data
echo "5. Getting FINAL/COMPLETE data for comparison:\n";
$cli->setDataState(DataState::FINAL);
displayTopKeywords($cli, "FINAL/COMPLETE", 5);

// Get FRESH data
echo "6. Getting FRESH/CURRENT data for comparison:\n";
$cli->setDataState(DataState::ALL);
displayTopKeywords($cli, "FRESH/CURRENT", 5);

