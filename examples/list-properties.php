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

    echo "Found " . count($properties) . " properties:\n\n";

    foreach ($properties as $property) {
        echo "- " . $property->getSiteUrl() . "\n";
        echo "  Permission Level: " . $property->getPermissionLevel() . "\n";
        echo "\n";
    }

    // Example: Select the first property for further operations
    if (isset($properties[0])) {
        $firstProperty = $properties[0]->getSiteUrl();
        echo "Selecting property: {$firstProperty}\n";

        $client->setProperty($firstProperty);
        echo "Current property: " . $client->getProperty() . "\n";
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
