<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Abromeit\GoogleSearchConsoleClient\GoogleSearchConsoleClient;
use Google\Client;
use Google\Service\SearchConsole;

// Path to your service account JSON file
$serviceAccountFile = __DIR__ . '/../credentials/service-account.json';

if (!file_exists($serviceAccountFile)) {
    $serviceAccountFile = realpath($serviceAccountFile);
    die("Please provide your service account JSON file at: {$serviceAccountFile}\n");
}

try {
    // Initialize Google Client with service account
    $googleClient = new Client();
    $googleClient->setAuthConfig($serviceAccountFile);
    $googleClient->addScope(SearchConsole::WEBMASTERS_READONLY);

    // Initialize our client
    $client = new GoogleSearchConsoleClient($googleClient);

    // Get and display properties
    $properties = $client->getProperties();

    if (empty($properties)) {
        echo "No properties found.\n";
        exit(0);
    }

    // Select the first property
    $firstProperty = $properties[0]->getSiteUrl();
    $client->setProperty($firstProperty);
    echo "Selected property: {$firstProperty}\n\n";

    // Calculate date range (last 7 days)
    $endDate = new DateTime('today');
    $startDate = (new DateTime('today'))->sub(new DateInterval('P7D'));

    // Set the date range
    $client->setDates($startDate, $endDate);

    // Get daily click data grouped by keywords
    $data = $client->getSearchPerformanceKeywords();

    // Display results
    echo "Daily clicks by keyword over the last 7 days:\n";
    echo str_repeat('-', 80) . "\n";
    echo sprintf("%-10s %-30s %10s %15s %10s\n", 'Date', 'Keyword', 'Clicks', 'Impressions', 'CTR (%)');
    echo str_repeat('-', 80) . "\n";

    foreach ($data as $row) {
        foreach ($row['keys'] as $keyword) {
            echo sprintf(
                "%-10s %-30s %10d %15d %10.1f\n",
                $row['date'],
                mb_substr($keyword, 0, 29),
                $row['clicks'],
                $row['impressions'],
                $row['ctr'] * 100
            );
        }
    }

    // Calculate totals
    $totalClicks = array_sum(array_column($data, 'clicks'));
    $totalImpressions = array_sum(array_column($data, 'impressions'));
    $avgCtr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;

    echo str_repeat('-', 80) . "\n";
    echo sprintf(
        "%-41s %10d %15d %10.1f\n",
        'TOTAL',
        $totalClicks,
        $totalImpressions,
        $avgCtr
    );

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
