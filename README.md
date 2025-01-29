# Google Search Console API Client

A PHP client for the Google Search Console API that makes it easy to retrieve search performance data.


## Requirements

- PHP 8.2+
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
// Get daily performance data
$dailyData = $client->getSearchPerformance();

// Get performance data by keywords
$keywordData = $client->getSearchPerformanceKeywords();

// Get performance data by URLs
$urlData = $client->getSearchPerformanceUrls();
```

### Example: Working with Keyword Data

```php
$keywordData = $client->getSearchPerformanceKeywords();

foreach ($keywordData as $row) {
    echo "Date: {$row['date']}\n";
    echo "Keywords: " . implode(', ', $row['keys']) . "\n";
    echo "Clicks: {$row['clicks']}\n";
    echo "Impressions: {$row['impressions']}\n";
    echo "CTR: " . ($row['ctr'] * 100) . "%\n";
    echo "Position: {$row['position']}\n";
}
```

### Example: Working with URL Data

```php
$urlData = $client->getSearchPerformanceUrls();

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
        'date' => string,      // Format: YYYY-MM-DD
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

The client throws `InvalidArgumentException`


## Google's Table Schema

The underlying database at Google contains the following columns:

### Table `searchdata_site_impression`

This table contains data aggregated by property. The table contains the following fields:

| Field                | Type    | Description                                                                                                                                                                                                 |
|----------------------|---------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| data_date            | string  | The day on which the data in this row was generated (Pacific Time).                                                                                                                                         |
| site_url             | string  | URL of the property. For domain-level properties, this will be `sc-domain:property-name`. For URL-prefix properties, it will be the full URL of the property definition. Examples: `sc-domain:developers.google.com`, `https://developers.google.com/webmaster-tools/`. |
| query                | string  | The user query. When `is_anonymized_query` is true, this will be a zero-length string.                                                                                                                      |
| is_anonymized_query  | boolean | Rare queries (called anonymized queries) are marked with this bool. The query field will be null when it's true to protect the privacy of users making the query.                                           |
| Country              | string  | Country from where the query was made, in ISO-3166-1-Alpha-3 format.                                                                                                                                       |
| search_type          | string  | One of the following string values: `web`, `image`, `video`, `news`, `discover`, `googleNews`.                                                                                                             |
| device               | string  | The device from which the query was made.                                                                                                                                                                   |
| impressions          | integer | The number of impressions for this row.                                                                                                                                                                     |
| clicks               | integer | The number of clicks for this row.                                                                                                                                                                          |
| sum_top_position     | float   | The sum of the topmost position of the site in the search results for each impression in that table row, where zero is the top position in the results. To calculate average position (which is 1-based), calculate `SUM(sum_top_position)/SUM(impressions) + 1`. |

### Table `searchdata_url_impression`

This table contains data aggregated by URL. The table contains the following fields:

| Field                   | Type    | Description                                                                                                                                                                                                 |
|-------------------------|---------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| data_date               | string  | Same as above.                                                                                                                                                                                              |
| site_url                | string  | Same as above.                                                                                                                                                                                              |
| url                     | string  | The fully-qualified URL where the user eventually lands when they click the search result or Discover story.                                                                                               |
| query                   | string  | Same as above.                                                                                                                                                                                              |
| is_anonymized_query     | boolean | Same as above.                                                                                                                                                                                              |
| is_anonymized_discover  | boolean | Whether the data row is under the Discover anonymization threshold. When under the threshold, some other fields (like URL and country) will be missing to protect user privacy.                             |
| country                 | string  | Same as above.                                                                                                                                                                                              |
| search_type             | string  | Same as above.                                                                                                                                                                                              |
| device                  | string  | Same as above.                                                                                                                                                                                              |
| is_[search_appearance_type] | boolean | There are several boolean fields used to indicate search appearance type, such as `is_amp_top_stories`, `is_job_listing`, and `is_job_details`. A field will be true if the row in question appears for the specific rich result. |
| impressions             | integer | Same as above.                                                                                                                                                                                              |
| clicks                  | integer | Same as above.                                                                                                                                                                                              |
| sum_position            | float   | A zero-based number indicating the topmost position of this URL in the search results for the query. (Zero is the top position in the results.) To calculate average position (which is 1-based), calculate `SUM(sum_position)/SUM(impressions) + 1`. |

