<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Abromeit\GoogleSearchConsoleClient\GoogleSearchConsoleClient;
use Abromeit\GoogleSearchConsoleClient\Enums\TimeframeResolution;
use Google\Client;

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
    $googleClient->addScope('https://www.googleapis.com/auth/webmasters.readonly');

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

    // Calculate date range (16 months back from today)
    $endDate = new \DateTime('today');
    $startDate = (new \DateTime('today'))->sub(new \DateInterval('P17M')); // Usually there are '16 Months + 2 Weeks' of data available, or something in that range.

    // Set the date range
    $client->setDates($startDate, $endDate);

    // Get monthly click data
    $data = $client->getSearchPerformance(
        resolution: TimeframeResolution::MONTHLY
    );

    // Display results
    echo "Monthly clicks over the last 16 months:\n";
    echo str_repeat('-', 40) . "\n";
    echo sprintf("%-10s %10s %15s\n", 'Month', 'Clicks', 'Impressions');
    echo str_repeat('-', 40) . "\n";

    foreach ($data as $row) {
        echo sprintf(
            "%-10s %10d %15d\n",
            $row['date'],
            $row['clicks'],
            $row['impressions']
        );
    }

    // Calculate totals
    $totalClicks = array_sum(array_column($data, 'clicks'));
    $totalImpressions = array_sum(array_column($data, 'impressions'));

    echo str_repeat('-', 40) . "\n";
    echo sprintf(
        "%-10s %10d %15d\n",
        'TOTAL',
        $totalClicks,
        $totalImpressions
    );

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
