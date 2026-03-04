<?php
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

require __DIR__ . '/vendor/autoload.php';

use FacebookAds\Api;
use FacebookAds\Logger\CurlLogger;
use FacebookAds\Object\ServerSide\ActionSource;
use FacebookAds\Object\ServerSide\Content;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\DeliveryCategory;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\MultiPixelEventRequest;
use FacebookAds\Object\ServerSide\GeotargetingHelper;

/**
 * MULTI-PIXEL TRACKING EXAMPLE
 *
 * This example demonstrates how to send the same conversion events
 * to multiple Facebook Pixels simultaneously with optimal tracking,
 * geotargeting, and real-time data transmission.
 *
 * Use cases:
 * - Tracking for multiple brands/accounts
 * - Client + Agency pixel tracking
 * - A/B testing with different pixel configurations
 * - Multi-region campaigns with separate pixels
 */

// Configuration
$access_token = '<ACCESS_TOKEN>';
$pixel_ids = [
    '<PIXEL_ID_1>',  // Main business pixel
    '<PIXEL_ID_2>',  // Agency pixel
    '<PIXEL_ID_3>',  // Backup/testing pixel
];

// Initialize API
$api = Api::init(null, null, $access_token);
$api->setLogger(new CurlLogger());

// ============================================================================
// EXAMPLE 1: Basic Multi-Pixel Tracking
// ============================================================================
echo "=== Example 1: Basic Multi-Pixel Tracking ===\n\n";

// Create UserData with automatic geotargeting
$user_data = GeotargetingHelper::autoDetect([
    'email' => 'customer@example.com',
    'phone' => '5511999999999',
    'first_name' => 'João',
    'last_name' => 'Silva',
]);

// Create custom data for the purchase event
$content = (new Content())
    ->setProductId('product-123')
    ->setQuantity(2)
    ->setDeliveryCategory(DeliveryCategory::HOME_DELIVERY);

$custom_data = (new CustomData())
    ->setContents([$content])
    ->setCurrency('BRL')
    ->setValue(299.90);

// Create the conversion event
$event = (new Event())
    ->setEventName('Purchase')
    ->setEventTime(time())
    ->setEventSourceUrl('https://www.example.com/checkout/success')
    ->setUserData($user_data)
    ->setCustomData($custom_data)
    ->setActionSource(ActionSource::WEBSITE)
    ->setEventId(uniqid('order_', true));  // Unique ID for deduplication

// Send to multiple pixels simultaneously
$multi_request = (new MultiPixelEventRequest())
    ->setEvents([$event])
    ->setPixels($pixel_ids);

try {
    $response = $multi_request->execute();

    echo "Multi-Pixel Response:\n";
    echo $response . "\n\n";

    // Check individual pixel results
    foreach ($pixel_ids as $pixel_id) {
        if ($response->isPixelSuccessful($pixel_id)) {
            echo "✓ Pixel $pixel_id: SUCCESS\n";
        } else {
            echo "✗ Pixel $pixel_id: FAILED\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 2: Advanced Multi-Pixel with Different Configurations
// ============================================================================
echo "\n\n=== Example 2: Advanced Configuration Per Pixel ===\n\n";

// Create multiple events (e.g., PageView + ViewContent + AddToCart)
$events = [];

// Event 1: PageView
$events[] = (new Event())
    ->setEventName('PageView')
    ->setEventTime(time() - 120)
    ->setEventSourceUrl('https://www.example.com/product/123')
    ->setUserData($user_data)
    ->setActionSource(ActionSource::WEBSITE)
    ->setEventId(uniqid('page_', true));

// Event 2: ViewContent
$view_content_data = (new CustomData())
    ->setContentName('Premium Product')
    ->setContentCategory('Electronics')
    ->setContentIds(['product-123'])
    ->setCurrency('BRL')
    ->setValue(299.90);

$events[] = (new Event())
    ->setEventName('ViewContent')
    ->setEventTime(time() - 60)
    ->setEventSourceUrl('https://www.example.com/product/123')
    ->setUserData($user_data)
    ->setCustomData($view_content_data)
    ->setActionSource(ActionSource::WEBSITE)
    ->setEventId(uniqid('view_', true));

// Event 3: AddToCart
$add_to_cart_data = (new CustomData())
    ->setContents([$content])
    ->setCurrency('BRL')
    ->setValue(299.90);

$events[] = (new Event())
    ->setEventName('AddToCart')
    ->setEventTime(time() - 30)
    ->setEventSourceUrl('https://www.example.com/product/123')
    ->setUserData($user_data)
    ->setCustomData($add_to_cart_data)
    ->setActionSource(ActionSource::WEBSITE)
    ->setEventId(uniqid('cart_', true));

// Configure different settings per pixel
$multi_request = new MultiPixelEventRequest($events);

// Pixel 1: Main business pixel with production settings
$multi_request->addPixel('<PIXEL_ID_1>', [
    'partner_agent' => 'my-ecommerce-platform-v1.0',
]);

// Pixel 2: Agency pixel with their access token
$multi_request->addPixel('<PIXEL_ID_2>', [
    'access_token' => '<AGENCY_ACCESS_TOKEN>',
    'partner_agent' => 'agency-platform-v2.0',
]);

// Pixel 3: Test pixel with test event code
$multi_request->addPixel('<PIXEL_ID_3>', [
    'test_event_code' => 'TEST12345',
]);

// Execute with parallel processing (default)
try {
    $response = $multi_request->execute();

    $summary = $response->getSummary();
    echo "Sent {$summary['total_events_received']} events to {$summary['total_pixels']} pixels\n";
    echo "Success Rate: {$summary['success_rate']}%\n";
    echo "Successful: {$summary['successful_pixels']} | Failed: {$summary['failed_pixels']}\n\n";

    if ($response->getFailureCount() > 0) {
        echo "Errors:\n";
        foreach ($response->getErrors() as $pixel_id => $error) {
            echo "  - $pixel_id: $error\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 3: Geotargeting with Manual Location Data
// ============================================================================
echo "\n\n=== Example 3: Enhanced Geotargeting ===\n\n";

// Create UserData with specific geotargeting information
$geo_user_data = GeotargetingHelper::createFromRequest(
    [
        'email' => 'customer@example.com',
        'phone' => '5511999999999',
        'fbp' => GeotargetingHelper::extractFbp(),
        'fbc' => GeotargetingHelper::extractFbc(),
    ],
    [
        'city' => 'São Paulo',
        'state' => 'SP',
        'country_code' => 'br',
        'zip_code' => '01310-100',
    ]
);

$geo_event = (new Event())
    ->setEventName('Lead')
    ->setEventTime(time())
    ->setEventSourceUrl('https://www.example.com/contact')
    ->setUserData($geo_user_data)
    ->setActionSource(ActionSource::WEBSITE)
    ->setEventId(uniqid('lead_', true));

$multi_request = (new MultiPixelEventRequest([$geo_event]))
    ->setPixels($pixel_ids)
    ->setPartnerAgent('my-crm-v1.0');

try {
    $response = $multi_request->execute();
    echo "Geotargeted event sent successfully!\n";
    echo $response . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 4: Sequential Execution (for debugging or rate-limiting)
// ============================================================================
echo "\n\n=== Example 4: Sequential Execution ===\n\n";

$multi_request = (new MultiPixelEventRequest([$event]))
    ->setPixels($pixel_ids)
    ->setParallelExecution(false);  // Disable parallel execution

try {
    $response = $multi_request->execute();
    echo "Sequential execution completed\n";

    foreach ($pixel_ids as $pixel_id) {
        $pixel_response = $response->getPixelResponse($pixel_id);
        echo "Pixel $pixel_id: " . ($pixel_response['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 5: Real-time Purchase Tracking with Complete Data
// ============================================================================
echo "\n\n=== Example 5: Complete E-commerce Purchase Tracking ===\n\n";

// Simulate a complete purchase with all Facebook standard parameters
$purchase_user_data = GeotargetingHelper::autoDetect([
    'email' => 'customer@example.com',
    'phone' => '5511999999999',
    'first_name' => 'Maria',
    'last_name' => 'Santos',
    'external_id' => 'customer_12345',  // Your internal customer ID
]);

// Create detailed product contents
$product1 = (new Content())
    ->setProductId('SKU-001')
    ->setTitle('Premium Headphones')
    ->setQuantity(1)
    ->setItemPrice(199.90)
    ->setDeliveryCategory(DeliveryCategory::HOME_DELIVERY);

$product2 = (new Content())
    ->setProductId('SKU-002')
    ->setTitle('Phone Case')
    ->setQuantity(2)
    ->setItemPrice(49.90)
    ->setDeliveryCategory(DeliveryCategory::HOME_DELIVERY);

$purchase_data = (new CustomData())
    ->setContents([$product1, $product2])
    ->setCurrency('BRL')
    ->setValue(299.70)  // 199.90 + (49.90 * 2)
    ->setNumItems(3)
    ->setContentType('product')
    ->setContentName('Electronics Purchase')
    ->setOrderId('ORDER-2024-00123');

$purchase_event = (new Event())
    ->setEventName('Purchase')
    ->setEventTime(time())
    ->setEventSourceUrl('https://www.example.com/checkout/success?order=123')
    ->setUserData($purchase_user_data)
    ->setCustomData($purchase_data)
    ->setActionSource(ActionSource::WEBSITE)
    ->setEventId('ORDER-2024-00123');  // Use order ID for deduplication

// Send to all configured pixels with optimal tracking
$multi_request = (new MultiPixelEventRequest([$purchase_event]))
    ->setPixels($pixel_ids)
    ->setPartnerAgent('ecommerce-platform-v2.5');

try {
    $response = $multi_request->execute();

    if ($response->isAllSuccessful()) {
        echo "✓ Purchase tracked successfully across all pixels!\n";
        echo "Order Value: R$ 299.70\n";
        echo "Pixels Updated: " . implode(', ', $response->getSuccessfulPixels()) . "\n";
    } else {
        echo "⚠ Partial success - some pixels failed\n";
        echo "Successful: " . $response->getSuccessCount() . "\n";
        echo "Failed: " . $response->getFailureCount() . "\n";

        if ($response->getFailureCount() > 0) {
            echo "\nFailed pixels:\n";
            foreach ($response->getErrors() as $pixel_id => $error) {
                echo "  - $pixel_id: $error\n";
            }
        }
    }

    // Export full response data for logging/debugging
    $full_data = $response->exportAllData();
    echo "\nFull Response Data:\n";
    print_r($full_data);

} catch (Exception $e) {
    echo "Error tracking purchase: " . $e->getMessage() . "\n";
}

echo "\n=== Examples completed ===\n";
