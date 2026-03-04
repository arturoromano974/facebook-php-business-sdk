<?php
/**
 * Copyright (c) 2015-present, Facebook, Inc. All rights reserved.
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

namespace FacebookAds\Object\ServerSide;

/**
 * Multi-Pixel Event Response
 *
 * Contains the aggregated responses from multiple pixel event requests
 *
 * @category    Class
 */
class MultiPixelEventResponse {
  /**
   * Array of responses keyed by pixel_id
   * @var array
   */
  protected $responses = array();

  /**
   * Constructor
   * @param array $responses Array of responses from each pixel
   */
  public function __construct(array $responses) {
    $this->responses = $responses;
  }

  /**
   * Get all responses
   * @return array
   */
  public function getResponses() {
    return $this->responses;
  }

  /**
   * Get response for a specific pixel
   * @param string $pixel_id
   * @return array|null
   */
  public function getPixelResponse($pixel_id) {
    return isset($this->responses[$pixel_id]) ? $this->responses[$pixel_id] : null;
  }

  /**
   * Check if all pixels succeeded
   * @return bool
   */
  public function isAllSuccessful() {
    foreach ($this->responses as $response) {
      if (!$response['success']) {
        return false;
      }
    }
    return true;
  }

  /**
   * Get count of successful pixels
   * @return int
   */
  public function getSuccessCount() {
    $count = 0;
    foreach ($this->responses as $response) {
      if ($response['success']) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Get count of failed pixels
   * @return int
   */
  public function getFailureCount() {
    $count = 0;
    foreach ($this->responses as $response) {
      if (!$response['success']) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Get list of successful pixel IDs
   * @return string[]
   */
  public function getSuccessfulPixels() {
    $successful = array();
    foreach ($this->responses as $pixel_id => $response) {
      if ($response['success']) {
        $successful[] = $pixel_id;
      }
    }
    return $successful;
  }

  /**
   * Get list of failed pixel IDs
   * @return string[]
   */
  public function getFailedPixels() {
    $failed = array();
    foreach ($this->responses as $pixel_id => $response) {
      if (!$response['success']) {
        $failed[] = $pixel_id;
      }
    }
    return $failed;
  }

  /**
   * Get errors for failed pixels
   * @return array Associative array of pixel_id => error_message
   */
  public function getErrors() {
    $errors = array();
    foreach ($this->responses as $pixel_id => $response) {
      if (!$response['success'] && isset($response['error'])) {
        $errors[$pixel_id] = $response['error'];
      }
    }
    return $errors;
  }

  /**
   * Get total events received across all pixels
   * @return int
   */
  public function getTotalEventsReceived() {
    $total = 0;
    foreach ($this->responses as $response) {
      if ($response['success'] && isset($response['response'])) {
        $event_response = $response['response'];
        if ($event_response instanceof EventResponse) {
          $data = $event_response->exportAllData();
          if (isset($data['events_received'])) {
            $total += $data['events_received'];
          }
        }
      }
    }
    return $total;
  }

  /**
   * Get summary statistics
   * @return array
   */
  public function getSummary() {
    return array(
      'total_pixels' => count($this->responses),
      'successful_pixels' => $this->getSuccessCount(),
      'failed_pixels' => $this->getFailureCount(),
      'success_rate' => count($this->responses) > 0
        ? round(($this->getSuccessCount() / count($this->responses)) * 100, 2)
        : 0,
      'total_events_received' => $this->getTotalEventsReceived(),
    );
  }

  /**
   * Check if a specific pixel was successful
   * @param string $pixel_id
   * @return bool
   */
  public function isPixelSuccessful($pixel_id) {
    $response = $this->getPixelResponse($pixel_id);
    return $response && $response['success'];
  }

  /**
   * Get string representation
   * @return string
   */
  public function __toString() {
    $summary = $this->getSummary();
    $output = "Multi-Pixel Event Response:\n";
    $output .= "  Total Pixels: {$summary['total_pixels']}\n";
    $output .= "  Successful: {$summary['successful_pixels']}\n";
    $output .= "  Failed: {$summary['failed_pixels']}\n";
    $output .= "  Success Rate: {$summary['success_rate']}%\n";
    $output .= "  Total Events Received: {$summary['total_events_received']}\n";

    if ($this->getFailureCount() > 0) {
      $output .= "\nFailed Pixels:\n";
      foreach ($this->getErrors() as $pixel_id => $error) {
        $output .= "  - $pixel_id: $error\n";
      }
    }

    return $output;
  }

  /**
   * Export all data as array
   * @return array
   */
  public function exportAllData() {
    return array(
      'summary' => $this->getSummary(),
      'responses' => $this->responses,
      'successful_pixels' => $this->getSuccessfulPixels(),
      'failed_pixels' => $this->getFailedPixels(),
      'errors' => $this->getErrors(),
    );
  }
}
