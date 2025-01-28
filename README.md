# Google Search Console API Client

A PHP client for the Google Search Console API that makes it easy to retrieve search performance data.


## Requirements

- PHP 8.1+
- Google API credentials (Service Account recommended)

## Installation

Install via Composer:

```bash
composer require abromeit/gsc-api-client
```

## Setup

1. Create a Google Cloud Project and enable the Search Console API
2. Create credentials (Service Account recommended)
3. Download the JSON credentials file
4. Grant access to your Search Console properties to the service account email

## Basic Usage

### Initialize the Client

```php
use Abromeit\GoogleSearchConsoleClient\GoogleSearchConsoleClient;
use Google\Client;
use Google\Service\SearchConsole;

// Initialize with service account
$googleClient = new Client();
$googleClient->setAuthConfig('/path/to/service-account.json');
$googleClient->addScope(SearchConsole::WEBMASTERS_READONLY);

$cli = new GoogleSearchConsoleClient($googleClient);
```

### List Available Properties

```php
$properties = $client->getProperties();
foreach ($properties as $property) {
    echo $property->getSiteUrl() . "\n";
    echo "Permission Level: " . $property->getPermissionLevel() . "\n";
}
```

### Select a Property

```php
// Using URL
$client->setProperty('https://example.com/');

// Using domain property
$client->setProperty('sc-domain:example.com');
```

### Set Date Range

```php
// Last 7 days
$endDate = new DateTime('today');
$startDate = (new DateTime('today'))->sub(new DateInterval('P7D'));
$client->setDates($startDate, $endDate);

// Specific date range
$client->setDates(
    new DateTime('2024-01-01'),
    new DateTime('2024-01-31')
);
```

### Get Search Performance Data

```php
use Abromeit\GoogleSearchConsoleClient\Enums\TimeframeResolution;

// Daily data (default)
$dailyData = $client->getSearchPerformance();

// Weekly data
$weeklyData = $client->getSearchPerformance(
    resolution: TimeframeResolution::WEEKLY
);

// Monthly data
$monthlyData = $client->getSearchPerformance(
    resolution: TimeframeResolution::MONTHLY
);

// All-time totals
$totals = $client->getSearchPerformance(
    resolution: TimeframeResolution::ALLOVER
);
```

### Get Performance by Keywords

```php
// Daily keyword data
$keywordData = $client->getSearchPerformanceKeywords(
    resolution: TimeframeResolution::DAILY
);

foreach ($keywordData as $row) {
    echo "Date: {$row['date']}\n";
    echo "Keywords: " . implode(', ', $row['keys']) . "\n";
    echo "Clicks: {$row['clicks']}\n";
    echo "Impressions: {$row['impressions']}\n";
    echo "CTR: " . ($row['ctr'] * 100) . "%\n";
    echo "Position: {$row['position']}\n";
}
```

### Get Performance by URLs

```php
// Monthly URL data
$urlData = $client->getSearchPerformanceUrls(
    resolution: TimeframeResolution::MONTHLY
);

foreach ($urlData as $row) {
    echo "Date: {$row['date']}\n";
    echo "URLs: " . implode(', ', $row['keys']) . "\n";
    echo "Clicks: {$row['clicks']}\n";
    echo "Impressions: {$row['impressions']}\n";
    echo "CTR: " . ($row['ctr'] * 100) . "%\n";
    echo "Position: {$row['position']}\n";
}
```

## Return Values

All performance methods return an array of rows with the following structure:

```php
[
    [
        'date' => string,      // Format depends on resolution
        'clicks' => int,       // Total clicks
        'impressions' => int,  // Total impressions
        'ctr' => float,        // Click-through rate (0.0 to 1.0)
        'position' => float,   // Average position
        'keys' => array        // Keywords/URLs (only for keyword/URL methods)
    ],
    [
        // etc.
    ],
    // etc.
]
```


## Error Handling

The client throws `InvalidArgumentException` for:
- Missing property selection
- Missing date range
- Invalid date ranges
- Empty dimensions

Google API errors are passed through as their original exceptions.

## More Examples

Check the `examples/` directory for complete working examples:
- `list-properties.php`: List all available properties
- `monthly-clicks.php`: Get monthly click data
- `keyword-clicks.php`: Get daily clicks by keyword

## License

MIT License. See LICENSE file for details. 
