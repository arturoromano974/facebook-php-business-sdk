# Multi-Pixel Facebook Conversions API Integration

## Overview

This enhanced version of the Facebook PHP Business SDK now supports simultaneous tracking across multiple Facebook Pixels, enabling optimal tracking and real-time data transmission for Facebook Ads campaigns. This is particularly useful for:

- Multi-brand businesses tracking conversions across different pixels
- Agency-client relationships requiring dual pixel tracking
- A/B testing with different pixel configurations
- Multi-region campaigns with separate pixels per region
- Backup pixel configurations for redundancy

## Key Features

### 1. Multi-Pixel Event Request
Send the same conversion events to multiple Facebook Pixels simultaneously with:
- **Parallel execution** (default): Events sent to all pixels concurrently for maximum speed
- **Sequential execution**: Events sent one after another for debugging or rate-limiting scenarios
- **Per-pixel configuration**: Each pixel can have its own access token, test event code, and settings

### 2. Enhanced Geotargeting
Automatic extraction and enrichment of user data with location information:
- Client IP address and user agent capture
- Facebook pixel cookies (fbp, fbc) extraction
- Country code detection (via CloudFlare/CloudFront headers)
- Support for city, state, and ZIP code tracking
- Browser locale detection

### 3. Comprehensive Response Handling
Detailed response aggregation with:
- Success/failure status per pixel
- Event count tracking
- Error reporting with specific pixel identification
- Summary statistics (success rate, total events, etc.)

## Installation

The multi-pixel functionality is built into the SDK. No additional installation required.

## Quick Start

### Basic Multi-Pixel Tracking

```php
<?php
require 'vendor/autoload.php';

use FacebookAds\Api;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\ActionSource;
use FacebookAds\Object\ServerSide\MultiPixelEventRequest;
use FacebookAds\Object\ServerSide\GeotargetingHelper;

// Initialize API
Api::init(null, null, '<ACCESS_TOKEN>');

// Create user data with automatic geotargeting
$user_data = GeotargetingHelper::autoDetect([
    'email' => 'customer@example.com',
    'phone' => '5511999999999',
]);

// Create a purchase event
$event = (new Event())
    ->setEventName('Purchase')
    ->setEventTime(time())
    ->setEventSourceUrl('https://www.example.com/checkout/success')
    ->setUserData($user_data)
    ->setCustomData(
        (new CustomData())
            ->setCurrency('BRL')
            ->setValue(299.90)
    )
    ->setActionSource(ActionSource::WEBSITE)
    ->setEventId(uniqid('order_', true));

// Send to multiple pixels
$response = (new MultiPixelEventRequest())
    ->setEvents([$event])
    ->setPixels([
        '<PIXEL_ID_1>',  // Main business pixel
        '<PIXEL_ID_2>',  // Agency pixel
        '<PIXEL_ID_3>',  // Backup pixel
    ])
    ->execute();

// Check results
if ($response->isAllSuccessful()) {
    echo "✓ Event tracked successfully across all pixels!\n";
} else {
    echo "⚠ Some pixels failed:\n";
    foreach ($response->getErrors() as $pixel_id => $error) {
        echo "  - $pixel_id: $error\n";
    }
}
```

### Advanced Configuration

```php
<?php
// Configure different settings per pixel
$multi_request = new MultiPixelEventRequest($events);

// Pixel 1: Main business pixel
$multi_request->addPixel('<PIXEL_ID_1>', [
    'partner_agent' => 'my-ecommerce-platform-v1.0',
]);

// Pixel 2: Agency pixel with separate access token
$multi_request->addPixel('<PIXEL_ID_2>', [
    'access_token' => '<AGENCY_ACCESS_TOKEN>',
    'partner_agent' => 'agency-platform-v2.0',
]);

// Pixel 3: Test pixel with test event code
$multi_request->addPixel('<PIXEL_ID_3>', [
    'test_event_code' => 'TEST12345',
]);

// Execute
$response = $multi_request->execute();

// Get detailed statistics
$summary = $response->getSummary();
echo "Sent {$summary['total_events_received']} events to {$summary['total_pixels']} pixels\n";
echo "Success Rate: {$summary['success_rate']}%\n";
```

## Geotargeting Features

### Automatic Detection

```php
<?php
use FacebookAds\Object\ServerSide\GeotargetingHelper;

// Automatically detect and capture all available tracking data
$user_data = GeotargetingHelper::autoDetect([
    'email' => 'customer@example.com',
    'phone' => '5511999999999',
]);

// This automatically includes:
// - Client IP address (from $_SERVER['REMOTE_ADDR'])
// - User agent (from $_SERVER['HTTP_USER_AGENT'])
// - FBP cookie (from $_COOKIE['_fbp'])
// - FBC cookie (from $_COOKIE['_fbc'] or $_GET['fbclid'])
// - Country code (from CloudFlare/CloudFront headers)
```

### Manual Geotargeting

```php
<?php
// Specify exact location data
$user_data = GeotargetingHelper::createFromRequest(
    [
        'email' => 'customer@example.com',
        'phone' => '5511999999999',
        'fbp' => 'fb.1.1558571054389.1098115397',
        'fbc' => 'fb.1.1554763741205.AbCdEfGhIjKlMnOpQrStUvWxYz',
    ],
    [
        'city' => 'São Paulo',
        'state' => 'SP',
        'country_code' => 'br',
        'zip_code' => '01310-100',
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0...',
    ]
);
```

### Extracting Facebook Parameters

```php
<?php
// Extract FBP and FBC individually
$fbp = GeotargetingHelper::extractFbp();
$fbc = GeotargetingHelper::extractFbc();

// Or get both at once
$params = GeotargetingHelper::extractFacebookParams();
// Returns: ['fbp' => '...', 'fbc' => '...']
```

## API Reference

### MultiPixelEventRequest

#### Constructor
```php
new MultiPixelEventRequest(?array $events = null)
```

#### Methods

##### setEvents(array $events): self
Set the events to send to all pixels.

##### addPixel(string $pixel_id, array $config = []): self
Add a pixel to the request. Config options:
- `access_token` (string): Override default access token
- `test_event_code` (string): Test event code for this pixel
- `partner_agent` (string): Partner agent identifier
- `namespace_id` (string): Namespace for external IDs
- `upload_id` (string): Upload identifier
- `upload_tag` (string): Upload tracking tag
- `upload_source` (string): Data source origin

##### setPixels(array $pixels): self
Set multiple pixels at once. Accepts array of pixel IDs or configurations.

##### setParallelExecution(bool $parallel): self
Enable/disable parallel execution (default: true).

##### setTestEventCode(string $code): self
Set global test event code for all pixels.

##### setPartnerAgent(string $agent): self
Set global partner agent for all pixels.

##### execute(): MultiPixelEventResponse
Execute the request and return aggregated response.

### MultiPixelEventResponse

#### Methods

##### getResponses(): array
Get all pixel responses.

##### getPixelResponse(string $pixel_id): ?array
Get response for a specific pixel.

##### isAllSuccessful(): bool
Check if all pixels succeeded.

##### getSuccessCount(): int
Get count of successful pixels.

##### getFailureCount(): int
Get count of failed pixels.

##### getSuccessfulPixels(): array
Get list of successful pixel IDs.

##### getFailedPixels(): array
Get list of failed pixel IDs.

##### getErrors(): array
Get errors for failed pixels.

##### getSummary(): array
Get summary statistics.

### GeotargetingHelper

#### Static Methods

##### autoDetect(array $user_info = []): UserData
Automatically detect and enrich UserData with all available tracking data.

##### createFromRequest(array $user_info, array $geo_options): UserData
Create UserData with specific user and geo information.

##### enrichUserData(UserData $user_data, array $options): UserData
Enrich existing UserData with geotargeting information.

##### extractFbp(): ?string
Extract Facebook Pixel cookie (_fbp).

##### extractFbc(): ?string
Extract Facebook Click ID cookie (_fbc) or build from fbclid parameter.

##### extractFacebookParams(): array
Extract both FBP and FBC parameters.

##### getCountryFromIp(string $ip_address): ?string
Get country code from IP (requires CloudFlare/CloudFront headers).

##### normalizeCountryCode(string $country_code): ?string
Validate and normalize country code to lowercase.

## Best Practices

### 1. Event Deduplication
Always set a unique `event_id` for each event to prevent duplicate tracking:

```php
$event->setEventId('ORDER-' . $order_id);  // Use order ID
// OR
$event->setEventId(uniqid('purchase_', true));  // Generate unique ID
```

### 2. Real-Time Tracking
Send events immediately after the action occurs:

```php
$event->setEventTime(time());  // Current timestamp
```

### 3. Error Handling
Always check for failures and log errors:

```php
$response = $multi_request->execute();

if (!$response->isAllSuccessful()) {
    error_log("Multi-pixel tracking partial failure:");
    foreach ($response->getErrors() as $pixel_id => $error) {
        error_log("  Pixel $pixel_id: $error");
    }
}
```

### 4. Test Event Codes
Use test event codes during development:

```php
$multi_request
    ->setPixels(['<PIXEL_ID>'])
    ->setTestEventCode('TEST12345')
    ->execute();
```

### 5. Partner Agent
Always set a partner agent to identify your integration:

```php
$multi_request->setPartnerAgent('my-app-name-v1.0');
```

## Performance Considerations

### Parallel vs Sequential Execution

**Parallel (Default - Recommended)**
```php
$multi_request->setParallelExecution(true);  // Default
```
- Fastest option
- All pixels receive events simultaneously
- Uses curl_multi for concurrent requests
- Ideal for production environments

**Sequential**
```php
$multi_request->setParallelExecution(false);
```
- Slower but easier to debug
- Pixels receive events one after another
- Better for testing and debugging
- Use when rate limiting is a concern

## Troubleshooting

### Common Issues

#### Issue: Events not appearing in Facebook Events Manager
**Solutions:**
1. Check pixel IDs are correct
2. Verify access token has correct permissions
3. Ensure event_time is in seconds (not milliseconds)
4. Check UserData has at least one identifier (email, phone, fbp, etc.)

#### Issue: Some pixels fail while others succeed
**Solutions:**
1. Check response errors: `$response->getErrors()`
2. Verify each pixel's access token and permissions
3. Check network connectivity
4. Review rate limiting on specific pixels

#### Issue: Geotargeting not capturing location
**Solutions:**
1. Verify CloudFlare or CloudFront headers are available
2. Use manual location specification: `GeotargetingHelper::createFromRequest()`
3. Check IP address is being captured correctly

## Examples

Complete working examples are available in the `examples/` directory:
- `MultiPixelTrackingExample.php`: Comprehensive multi-pixel examples

## Testing

Run the multi-pixel tests:

```bash
vendor/bin/phpunit test/FacebookAdsTest/Object/ServerSide/MultiPixelEventRequestTest.php
vendor/bin/phpunit test/FacebookAdsTest/Object/ServerSide/MultiPixelEventResponseTest.php
vendor/bin/phpunit test/FacebookAdsTest/Object/ServerSide/GeotargetingHelperTest.php
```

## Support

For issues, questions, or contributions, please visit:
- GitHub Repository: https://github.com/facebook/facebook-php-business-sdk
- Facebook Marketing API Documentation: https://developers.facebook.com/docs/marketing-api/conversions-api

## License

This software is licensed under the Facebook Platform License. See LICENSE file for details.
