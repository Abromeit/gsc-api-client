<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Abromeit\GscApiClient\GscApiClient;
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
    $client = new GscApiClient($googleClient);

    // Get and display properties
    $properties = $client->getProperties();

    if (empty($properties)) {
        echo "No properties found. Please make sure the service account has access to some properties.\n";
        echo "\n";
        exit(0);
    }

    // Display results
    echo "Found " . count($properties) . " properties:\n";
    echo "\n";
    echo str_repeat('-', 80) . "\n";
    echo sprintf("%-50s %s\n", 'Property URL', 'Permission Level');
    echo str_repeat('-', 80) . "\n";

    foreach ($properties as $property) {
        echo sprintf(
            "%-50s %s\n",
            strlen($property->getSiteUrl()) > 49 ? mb_substr($property->getSiteUrl(), 0, 46) . '...' : $property->getSiteUrl(),
            $property->getPermissionLevel()
        );
    }

    echo str_repeat('-', 80) . "\n";
    echo "\n";

    // Example: Select the first property for further operations
    if (isset($properties[0])) {

        $firstProperty = $properties[0]->getSiteUrl();
        echo "Selecting property: {$firstProperty}\n";

        $client->setProperty($firstProperty);
        echo "Current property: " . $client->getProperty() . "\n";
        echo "\n";
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
