<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Abromeit\GscApiClient\GscApiClient;
use Google\Client;
use Google\Service\SearchConsole;

// Path to your service account JSON file
$serviceAccountFile = __DIR__ . '/../credentials/service-account.json';

// Initialize with service account
$googleClient = new Client();
$googleClient->setAuthConfig($serviceAccountFile);
$googleClient->addScope(SearchConsole::WEBMASTERS_READONLY);

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

// Get URLs for last $numdays days
$numdays = 14;
$endDate = new DateTime('today');
$startDate = (new DateTime('today'))->sub(new DateInterval('P'.$numdays.'D'));
$cli->setDates($startDate, $endDate);

$topN = 3;
$urls = $cli->getTopUrlsByDay($topN);

// Display results
echo "Top {$topN} URLs per day for {$propertyUrl}:\n";
echo "\n";
echo str_repeat('-', 120) . "\n";
echo sprintf("%-10s %-50s %10s %15s %10s\n", 'Date', 'URL', 'Clicks', 'Impressions', 'CTR (%)');
echo str_repeat('-', 120) . "\n";

if (empty($urls)) {
    echo sprintf(
        "\nNo data found for the period %s to %s.\n",
        $startDate->format('Y-m-d'),
        $endDate->format('Y-m-d')
    );
    echo "\n";
    exit(0);
}

foreach ($urls as $row) {

    // Avoid division by zero
    $ctr = $row['impressions'] > 0 ? ($row['clicks'] / $row['impressions']) * 100 : 0;

    echo sprintf(
        "%-10s %-50s %10d %15d %10.1f\n",
        $row['data_date'],
        strlen($row['url']) > 49 ? mb_substr($row['url'], 0, 46) . '...' : $row['url'],
        $row['clicks'],
        $row['impressions'],
        $ctr
    );
}

echo str_repeat('-', 120) . "\n";
echo "\n";
