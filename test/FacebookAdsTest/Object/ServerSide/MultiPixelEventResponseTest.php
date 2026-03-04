<?php
/**
 * Copyright (c) 2014-present, Facebook, Inc. All rights reserved.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Facebook.
 *
 * As with any software that integrates with the Facebook platform, your use
 * of this software is subject to the Facebook Developer Principles and
 * Policies [http://developers.facebook.com/policy/]. This copyright notice
 * shall be included in all copies or substantial portions of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */

namespace FacebookAdsTest\Object;

use FacebookAdsTest\AbstractUnitTestCase;
use FacebookAds\Object\ServerSide\MultiPixelEventResponse;
use FacebookAds\Object\ServerSide\EventResponse;

class MultiPixelEventResponseTest extends AbstractUnitTestCase {

  public function testConstructorAndGetResponses() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $this->assertEquals($responses, $multi_response->getResponses());
  }

  public function testGetPixelResponse() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $pixel1_response = $multi_response->getPixelResponse('pixel_1');
    $this->assertNotNull($pixel1_response);
    $this->assertTrue($pixel1_response['success']);
    $this->assertEquals('pixel_1', $pixel1_response['pixel_id']);

    $nonexistent = $multi_response->getPixelResponse('nonexistent');
    $this->assertNull($nonexistent);
  }

  public function testIsAllSuccessful() {
    $all_success = [
      'pixel_1' => ['success' => true, 'pixel_id' => 'pixel_1'],
      'pixel_2' => ['success' => true, 'pixel_id' => 'pixel_2'],
    ];
    $response = new MultiPixelEventResponse($all_success);
    $this->assertTrue($response->isAllSuccessful());

    $partial_success = $this->createMockResponses();
    $response2 = new MultiPixelEventResponse($partial_success);
    $this->assertFalse($response2->isAllSuccessful());
  }

  public function testGetSuccessCount() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $this->assertEquals(2, $multi_response->getSuccessCount());
  }

  public function testGetFailureCount() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $this->assertEquals(1, $multi_response->getFailureCount());
  }

  public function testGetSuccessfulPixels() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $successful = $multi_response->getSuccessfulPixels();
    $this->assertCount(2, $successful);
    $this->assertContains('pixel_1', $successful);
    $this->assertContains('pixel_2', $successful);
    $this->assertNotContains('pixel_3', $successful);
  }

  public function testGetFailedPixels() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $failed = $multi_response->getFailedPixels();
    $this->assertCount(1, $failed);
    $this->assertContains('pixel_3', $failed);
  }

  public function testGetErrors() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $errors = $multi_response->getErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('pixel_3', $errors);
    $this->assertEquals('Connection timeout', $errors['pixel_3']);
  }

  public function testGetTotalEventsReceived() {
    $responses = [
      'pixel_1' => [
        'success' => true,
        'pixel_id' => 'pixel_1',
        'response' => new EventResponse(['events_received' => 5])
      ],
      'pixel_2' => [
        'success' => true,
        'pixel_id' => 'pixel_2',
        'response' => new EventResponse(['events_received' => 3])
      ],
      'pixel_3' => [
        'success' => false,
        'pixel_id' => 'pixel_3',
        'error' => 'Failed'
      ],
    ];

    $multi_response = new MultiPixelEventResponse($responses);
    $this->assertEquals(8, $multi_response->getTotalEventsReceived());
  }

  public function testGetSummary() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $summary = $multi_response->getSummary();

    $this->assertArrayHasKey('total_pixels', $summary);
    $this->assertArrayHasKey('successful_pixels', $summary);
    $this->assertArrayHasKey('failed_pixels', $summary);
    $this->assertArrayHasKey('success_rate', $summary);
    $this->assertArrayHasKey('total_events_received', $summary);

    $this->assertEquals(3, $summary['total_pixels']);
    $this->assertEquals(2, $summary['successful_pixels']);
    $this->assertEquals(1, $summary['failed_pixels']);
    $this->assertEquals(66.67, $summary['success_rate']);
  }

  public function testIsPixelSuccessful() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $this->assertTrue($multi_response->isPixelSuccessful('pixel_1'));
    $this->assertTrue($multi_response->isPixelSuccessful('pixel_2'));
    $this->assertFalse($multi_response->isPixelSuccessful('pixel_3'));
    $this->assertFalse($multi_response->isPixelSuccessful('nonexistent'));
  }

  public function testToString() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $string = (string) $multi_response;

    $this->assertStringContainsString('Multi-Pixel Event Response', $string);
    $this->assertStringContainsString('Total Pixels: 3', $string);
    $this->assertStringContainsString('Successful: 2', $string);
    $this->assertStringContainsString('Failed: 1', $string);
    $this->assertStringContainsString('Success Rate: 66.67%', $string);
  }

  public function testExportAllData() {
    $responses = $this->createMockResponses();
    $multi_response = new MultiPixelEventResponse($responses);

    $data = $multi_response->exportAllData();

    $this->assertArrayHasKey('summary', $data);
    $this->assertArrayHasKey('responses', $data);
    $this->assertArrayHasKey('successful_pixels', $data);
    $this->assertArrayHasKey('failed_pixels', $data);
    $this->assertArrayHasKey('errors', $data);
  }

  // Helper method to create mock responses
  private function createMockResponses() {
    return [
      'pixel_1' => [
        'success' => true,
        'pixel_id' => 'pixel_1',
        'response' => new EventResponse(['events_received' => 1])
      ],
      'pixel_2' => [
        'success' => true,
        'pixel_id' => 'pixel_2',
        'response' => new EventResponse(['events_received' => 1])
      ],
      'pixel_3' => [
        'success' => false,
        'pixel_id' => 'pixel_3',
        'error' => 'Connection timeout'
      ],
    ];
  }
}
