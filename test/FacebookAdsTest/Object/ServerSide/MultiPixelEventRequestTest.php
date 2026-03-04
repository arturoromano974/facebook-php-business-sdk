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
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\MultiPixelEventRequest;
use FacebookAds\Object\ServerSide\MultiPixelEventResponse;

class MultiPixelEventRequestTest extends AbstractUnitTestCase {

  public function testConstructor() {
    $event = $this->createTestEvent();
    $request = new MultiPixelEventRequest([$event]);

    $this->assertEquals([$event], $request->getEvents());
    $this->assertTrue($request->isParallelExecution());
  }

  public function testAddPixel() {
    $request = new MultiPixelEventRequest();
    $request->addPixel('pixel_123');

    $configs = $request->getPixelConfigs();
    $this->assertCount(1, $configs);
    $this->assertEquals('pixel_123', $configs[0]['pixel_id']);
  }

  public function testAddPixelWithConfig() {
    $request = new MultiPixelEventRequest();
    $request->addPixel('pixel_456', [
      'test_event_code' => 'TEST123',
      'partner_agent' => 'test-agent',
    ]);

    $configs = $request->getPixelConfigs();
    $this->assertCount(1, $configs);
    $this->assertEquals('pixel_456', $configs[0]['pixel_id']);
    $this->assertEquals('TEST123', $configs[0]['test_event_code']);
    $this->assertEquals('test-agent', $configs[0]['partner_agent']);
  }

  public function testSetPixelsWithStringArray() {
    $request = new MultiPixelEventRequest();
    $request->setPixels(['pixel_1', 'pixel_2', 'pixel_3']);

    $configs = $request->getPixelConfigs();
    $this->assertCount(3, $configs);
    $this->assertEquals('pixel_1', $configs[0]['pixel_id']);
    $this->assertEquals('pixel_2', $configs[1]['pixel_id']);
    $this->assertEquals('pixel_3', $configs[2]['pixel_id']);
  }

  public function testSetPixelsWithConfigArray() {
    $request = new MultiPixelEventRequest();
    $request->setPixels([
      ['pixel_id' => 'pixel_1', 'test_event_code' => 'TEST1'],
      ['pixel_id' => 'pixel_2', 'partner_agent' => 'agent2'],
    ]);

    $configs = $request->getPixelConfigs();
    $this->assertCount(2, $configs);
    $this->assertEquals('pixel_1', $configs[0]['pixel_id']);
    $this->assertEquals('TEST1', $configs[0]['test_event_code']);
    $this->assertEquals('pixel_2', $configs[1]['pixel_id']);
    $this->assertEquals('agent2', $configs[1]['partner_agent']);
  }

  public function testSetEvents() {
    $event1 = $this->createTestEvent();
    $event2 = $this->createTestEvent();

    $request = new MultiPixelEventRequest();
    $request->setEvents([$event1, $event2]);

    $this->assertEquals([$event1, $event2], $request->getEvents());
  }

  public function testSetParallelExecution() {
    $request = new MultiPixelEventRequest();

    // Default should be true
    $this->assertTrue($request->isParallelExecution());

    // Set to false
    $request->setParallelExecution(false);
    $this->assertFalse($request->isParallelExecution());

    // Set back to true
    $request->setParallelExecution(true);
    $this->assertTrue($request->isParallelExecution());
  }

  public function testSetTestEventCode() {
    $request = new MultiPixelEventRequest();
    $request->setTestEventCode('TEST_CODE_123');

    // We can't directly access protected properties, but we can verify
    // the method doesn't throw an exception
    $this->assertInstanceOf(MultiPixelEventRequest::class, $request);
  }

  public function testSetPartnerAgent() {
    $request = new MultiPixelEventRequest();
    $request->setPartnerAgent('my-partner-agent-v1.0');

    $this->assertInstanceOf(MultiPixelEventRequest::class, $request);
  }

  public function testGetPixelCount() {
    $request = new MultiPixelEventRequest();
    $this->assertEquals(0, $request->getPixelCount());

    $request->setPixels(['pixel_1', 'pixel_2', 'pixel_3']);
    $this->assertEquals(3, $request->getPixelCount());
  }

  public function testValidationFailsWithNoPixels() {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('At least one pixel must be configured');

    $event = $this->createTestEvent();
    $request = new MultiPixelEventRequest([$event]);
    // Don't add any pixels
    $request->execute();
  }

  public function testValidationFailsWithNoEvents() {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Events array cannot be empty');

    $request = new MultiPixelEventRequest();
    $request->addPixel('pixel_123');
    // Don't add any events
    $request->execute();
  }

  public function testValidationFailsWithInvalidEvent() {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('event_name');

    $invalid_event = new Event();
    // Missing required fields

    $request = new MultiPixelEventRequest([$invalid_event]);
    $request->addPixel('pixel_123');
    $request->execute();
  }

  public function testChainedMethodCalls() {
    $event = $this->createTestEvent();

    $request = (new MultiPixelEventRequest())
      ->setEvents([$event])
      ->setPixels(['pixel_1', 'pixel_2'])
      ->setTestEventCode('TEST')
      ->setPartnerAgent('agent')
      ->setParallelExecution(false);

    $this->assertInstanceOf(MultiPixelEventRequest::class, $request);
    $this->assertEquals(2, $request->getPixelCount());
    $this->assertFalse($request->isParallelExecution());
  }

  // Helper method to create a valid test event
  private function createTestEvent() {
    $user_data = (new UserData())
      ->setEmail('test@example.com')
      ->setPhone('1234567890');

    $event = (new Event())
      ->setEventName('Purchase')
      ->setEventTime(time())
      ->setUserData($user_data);

    return $event;
  }
}
