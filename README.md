# GSC API Client - A PHP Class for Easy-Peasy Data Retrieval from Google Search Console

A **PHP client** for the Google Search Console API that makes it easy to import search performance data programmatically into your application.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage Examples](#usage-examples)
  - [Initialize the Client](#initialize-the-client)
  - [List Available Properties](#list-available-properties)
  - [Select a Property](#select-a-property)
  - [Set Date Range](#set-date-range)
  - [Get Search Performance Data](#get-search-performance-data)
  - [Configure Batch Processing](#configure-batch-processing)
  - [Accessing Returned Keyword Data](#accessing-returned-keyword-data)
  - [Accessing Returned URL Data](#accessing-returned-url-data)
- [Return Values](#return-values)
  - [Keyword Data Structure](#keyword-data-structure)
  - [URL Data Structure](#url-data-structure)
- [API Reference](#api-reference)
- [Performance and Resource Requirements](#performance-and-resource-requirements)
  - [Tested with Large-ish GSC Accounts](#tested-with-large-ish-gsc-accounts)
    - [Memory Usage](#memory-usage)
    - [Execution Time](#execution-time)
- [Google's Table Schema](#googles-table-schema)
  - [Table `searchdata_site_impression`](#table-searchdata_site_impression)
  - [Table `searchdata_url_impression`](#table-searchdata_url_impression)

## Requirements

- PHP 8.2+
- Credentials for the Google Search Console API
- A Google Search Console property with some data

## Installation

The GSC API Client is available as Composer package, which is most likely the easiest way to use this library.

To install it via Composer:

```bash
composer require abromeit/gsc-api-client
```

1. Create a Google Cloud Project and enable the Search Console API
2. Create credentials (Service Account recommended)
3. Download the JSON credentials file
4. Grant access to your Search Console properties to the service account email

## Usage Examples

### Initialize the Client

```php
use Abromeit\GscApiClient\GscApiClient;
use Google\Client;
use Google\Service\SearchConsole;

// Initialize with service account
$googleClient = new Client();
$googleClient->setAuthConfig('/path/to/service-account.json');
$googleClient->addScope(SearchConsole::WEBMASTERS_READONLY);

$apiClient = new GscApiClient($googleClient);
```

### List Available Properties

```php
$properties = $apiClient->getProperties();
foreach ($properties as $property) {
    echo $property->getSiteUrl() . "\n";
    echo "Permission Level: " . $property->getPermissionLevel() . "\n";
}
```

### Select a Property

```php
// Using URL
$apiClient->setProperty('https://example.com/');

// Using domain property
$apiClient->setProperty('sc-domain:example.com');
```

### Set Date Range

```php
// Last 7 days
$startDate = (new DateTime('today'))->sub(new DateInterval('P7D'));
$endDate = new DateTime('today');
$apiClient->setDates($startDate, $endDate);

// Or specific date range
$apiClient->setDates(
    new DateTime('2024-01-01'),
    new DateTime('2024-01-31')
);
```

### Get Search Performance Data

```php
// Get daily performance data
$keywordData = $apiClient->getTopKeywordsByDay();

// Get performance data by URLs (up to 5k rows per day)
$urlData = $apiClient->getTopUrlsByDay();

// Customize the number of rows per day (default/max: 5000)
$keywordData = $apiClient->getTopKeywordsByDay(10);
$urlData = $apiClient->getTopUrlsByDay(10);

// Filter by country (using ISO-3166-1-Alpha-3 code)
$apiClient->setCountry('USA');

// Filter by device type (DESKTOP, MOBILE, TABLET)
$apiClient->setDevice(\Abromeit\GscApiClient\Enums\GSCDeviceType::DESKTOP);

// Clear individual filters
$apiClient->setCountry(null);
$apiClient->setDevice(null);
$apiClient->setSearchType(null);

// Create custom dimension filters
$queryFilter = $apiClient->getNewApiDimensionFilterGroup('query', 'foo bar'); // "query = foo bar"
$queryFilter = $apiClient->getNewApiDimensionFilterGroup('query', 'foo bar', 'contains'); // "query *= foo bar"
```

### Configure Batch Processing

See https://developers.google.com/webmaster-tools/v1/how-tos/batch

```php
// Get current batch size
$batchSize = $apiClient->getBatchSize();

// Set number of requests to batch together (1-1000)
// Note that your batch size modification must take place
// before calling methods like getTopKeywordsByDay(), which trigger API requests.
$apiClient->setBatchSize(10);
```

### Accessing Returned Keyword Data

```php
$keywordData = $apiClient->getTopKeywordsByDay();

foreach ($keywordData as $row) {
    echo "Date: {$row['data_date']}\n";
    echo "Query: {$row['query']}\n";
    echo "Clicks: {$row['clicks']}\n";
    echo "Impressions: {$row['impressions']}\n";
    echo "Sum Top Position: {$row['sum_top_position']}\n";
}
```

### Accessing Returned URL Data

```php
$urlData = $apiClient->getTopUrlsByDay();

foreach ($urlData as $row) {
    echo "Date: {$row['data_date']}\n";
    echo "URL: {$row['url']}\n";
    echo "Clicks: {$row['clicks']}\n";
    echo "Impressions: {$row['impressions']}\n";
    echo "Sum Top Position: {$row['sum_top_position']}\n";
}
```

## Return Values

The performance methods return arrays matching Google's BigQuery schema. (Via https://support.google.com/webmasters/answer/12917991?hl=en )

### Keyword Data Structure

```php
/* <Generator> */
    [
        'data_date' => string,         // Format: YYYY-MM-DD
        'site_url' => string,          // Property URL
        'query' => string,             // Search query
        'country' => ?string,          // Optional: ISO-3166-1-Alpha-3 country code
        'device' => ?string,           // Optional: DESKTOP, MOBILE, or TABLET
        'impressions' => int,          // Total impressions
        'clicks' => int,               // Total clicks
        'sum_top_position' => float    // Sum of positions * impressions
    ],
    // etc.
/* </Generator> */
```

### URL Data Structure

```php
/* <Generator> */
    [
        'data_date' => string,         // Format: YYYY-MM-DD
        'site_url' => string,          // Property URL
        'url' => string,               // Page URL
        'country' => ?string,          // Optional: ISO-3166-1-Alpha-3 country code
        'device' => ?string,           // Optional: DESKTOP, MOBILE, or TABLET
        'impressions' => int,          // Total impressions
        'clicks' => int,               // Total clicks
        'sum_top_position' => float    // Sum of positions * impressions
    ],
    // etc.
/* </Generator> */
```


## API Reference

The following table lists all public methods available in the `GscApiClient` class:

| Method Signature | Return Type | Description |
|-----------------|-------------|-------------|
| `__construct(Client $client)` | `void` | Initializes a new GSC API client instance |
| `getBatchSize()` | `int` | Gets the current batch size setting |
| `setBatchSize(int $batchSize)` | `self` | Sets number of requests to batch (1-1000, default 10) |
| `getProperties()` | `WmxSite[]` | Gets all properties the user has access to |
| `setProperty(string $siteUrl)` | `self` | Sets the property to work with |
| `getProperty()` | `string \| null` | Gets the currently set property URL |
| `hasProperty()` | `bool` | Checks if a property is set |
| `isDomainProperty([?string $siteUrl=null])` | `bool` | Checks if URL is a domain property |
| `setStartDate(DateTimeInterface $date)` | `self` | Sets the start date |
| `setEndDate(DateTimeInterface $date)` | `self` | Sets the end date |
| `setDates(DateTimeInterface $startDate, DateTimeInterface $endDate)` | `self` | Sets both start and end dates |
| `clearStartDate()` | `self` | Clears the start date |
| `clearEndDate()` | `self` | Clears the end date |
| `clearDates()` | `self` | Clears both dates |
| `getStartDate()` | `DateTimeInterface \| null` | Gets the start date |
| `getEndDate()` | `DateTimeInterface \| null` | Gets the end date |
| `getDates()` | `array{start: DateTimeInterface \| null, end: DateTimeInterface \| null}` | Gets both dates |
| `hasStartDate()` | `bool` | Checks if start date is set |
| `hasEndDate()` | `bool` | Checks if end date is set |
| `hasDates()` | `bool` | Checks if both dates are set |
| `setCountry([?string $countryCode=null])` | `self` | Sets country using ISO-3166-1-Alpha-3 code |
| `getCountry()` | `string \| null` | Gets the current country |
| `setDevice([?DeviceType $deviceType=null])` | `self` | Sets device type |
| `getDevice()` | `string \| null` | Gets the current device type |
| `setSearchType([?string $searchType=null])` | `self` | Sets search type (e.g., 'WEB', 'NEWS') |
| `getSearchType()` | `string \| null` | Gets the current search type |
| `getNewApiDimensionFilterGroup(string $dimension, string $expression, [string $operator='equals'])` | `ApiDimensionFilterGroup` | Creates a dimension filter group for custom filtering. Operator can be 'equals', 'contains', 'notContains', 'includingRegex' |
| `getTopKeywordsByDay([?int $maxRowsPerDay=null])` | `Generator<array{...}>` | Gets top keywords by day |
| `getTopUrlsByDay([?int $maxRowsPerDay=null])` | `Generator<array{...}>` | Gets top URLs by day |

## Speed and Resource Requirements

The client has been updated to a yield-style implementation, which offers several advantages above traditional returns:

- Data is returned sooner, allowing processing of initial entries without waiting for the entire dataset. (Effectively **streaming** the data as it becomes available.)
- The BatchSize config option now allows us to choose of either speed or minimal memory usage. _(I'd love to say "and any point in between", but in reality even small batch sizes can result in substantial HTTP response sizes. - which unsuprisingly affects the memory footprint our application has.)_

### Tested with Large-ish GSC Accounts

Tests were conducted in a local environment, not on a production server. 

We requested 16 months of daily data using `getTopKeywordsByDay()`. This function, based on the "byProperty" aggregation, returns the top 5k keywords per day. _(I.e. the max. row number was 5k at the time of testing, not 25k or more. This is undocumented behavior of the official API.)_

The result returned 499 days of data in 2,495,000 rows. (One row contains the keyword with impressions, clicks, ctr for a single day.)

#### Measured Performance Characteristics

These are ballpark numbers.

| Implementation | Batch Size | Peak Memory   | Runtime     |
|----------------|------------|---------------|-------------|
| Array-style    | 1          | 1,269 MB      | 307s (5.1m) |
| Yield-style    | 1          | 45 MB (-96%)  | 313s (5.2m) |
| Array-style    | 10         | 1,329 MB      | 194s (3.2m) |
| Yield-style    | 10         | 145 MB (-89%) | 193s (3.2m) |
| Array-style    | 1000       | 1,917 MB      | 36s (0.6m)  |
| Yield-style    | 1000       | 639 MB (-67%) | 38s (0.6m)  |

## Google's Table Schema

It is not always easy to think about GSC data. Therefore, it is great to be able to see how Google itself handles the problem.

If you export your GSC data to BigQuery, you will find the following situation in the tables.

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

