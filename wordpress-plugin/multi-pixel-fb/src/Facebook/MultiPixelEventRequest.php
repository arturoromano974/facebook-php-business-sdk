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

namespace MultiPixelFB\Facebook;

/**
 * Multi-Pixel Conversions API Event Request
 *
 * Enables sending the same events to multiple Facebook Pixels simultaneously,
 * ensuring optimal tracking and real-time data transmission for Facebook Ads.
 *
 * @category    Class
 */
class MultiPixelEventRequest {
  /**
   * Array of pixel configurations
   * @var array[]
   */
  protected $pixel_configs = array();

  /**
   * Shared events to send to all pixels
   * @var \FacebookAds\Object\ServerSide\Event[]
   */
  protected $events = null;

  /**
   * Whether to execute requests in parallel (async)
   * @var bool
   */
  protected $parallel_execution = true;

  /**
   * Global test event code (can be overridden per pixel)
   * @var string|null
   */
  protected $test_event_code = null;

  /**
   * Global partner agent (can be overridden per pixel)
   * @var string|null
   */
  protected $partner_agent = null;

  /**
   * Constructor
   * @param \FacebookAds\Object\ServerSide\Event[] $events Array of events to send
   */
  public function __construct(?array $events = null) {
    $this->events = $events;
  }

  /**
   * Add a pixel to send events to
   *
   * @param string $pixel_id The Facebook Pixel ID
   * @param array $config Optional configuration for this pixel:
   *   - 'access_token' (string): Override the default access token for this pixel
   *   - 'test_event_code' (string): Test event code for this specific pixel
   *   - 'partner_agent' (string): Partner agent for this specific pixel
   *   - 'namespace_id' (string): Namespace ID for external ID resolution
   *   - 'upload_id' (string): Unique upload ID
   *   - 'upload_tag' (string): Upload tracking tag
   *   - 'upload_source' (string): Data source origin
   *   - 'http_client' (HttpServiceInterface): Custom HTTP client
   *   - 'custom_endpoint' (CustomEndpointRequest): Custom endpoint configuration
   * @return $this
   */
  public function addPixel($pixel_id, array $config = array()) {
    $this->pixel_configs[] = array_merge(
      array('pixel_id' => $pixel_id),
      $config
    );
    return $this;
  }

  /**
   * Set multiple pixels at once
   *
   * @param array $pixels Array of pixel IDs or pixel configurations
   *   Examples:
   *   - Simple: ['pixel_id_1', 'pixel_id_2', 'pixel_id_3']
   *   - With config: [
   *       ['pixel_id' => 'pixel_id_1', 'test_event_code' => 'TEST123'],
   *       ['pixel_id' => 'pixel_id_2', 'access_token' => 'token456']
   *     ]
   * @return $this
   */
  public function setPixels(array $pixels) {
    $this->pixel_configs = array();
    foreach ($pixels as $pixel) {
      if (is_string($pixel)) {
        $this->addPixel($pixel);
      } elseif (is_array($pixel) && isset($pixel['pixel_id'])) {
        $pixel_id = $pixel['pixel_id'];
        unset($pixel['pixel_id']);
        $this->addPixel($pixel_id, $pixel);
      }
    }
    return $this;
  }

  /**
   * Get all configured pixels
   * @return array[]
   */
  public function getPixelConfigs() {
    return $this->pixel_configs;
  }

  /**
   * Set events to send to all pixels
   * @param \FacebookAds\Object\ServerSide\Event[] $events
   * @return $this
   */
  public function setEvents($events) {
    $this->events = $events;
    return $this;
  }

  /**
   * Get events
   * @return \FacebookAds\Object\ServerSide\Event[]
   */
  public function getEvents() {
    return $this->events;
  }

  /**
   * Enable or disable parallel execution
   * When enabled (default), events are sent to all pixels simultaneously.
   * When disabled, events are sent sequentially.
   *
   * @param bool $parallel
   * @return $this
   */
  public function setParallelExecution($parallel) {
    $this->parallel_execution = $parallel;
    return $this;
  }

  /**
   * Get parallel execution setting
   * @return bool
   */
  public function isParallelExecution() {
    return $this->parallel_execution;
  }

  /**
   * Set global test event code (applies to all pixels unless overridden)
   * @param string $test_event_code
   * @return $this
   */
  public function setTestEventCode($test_event_code) {
    $this->test_event_code = $test_event_code;
    return $this;
  }

  /**
   * Set global partner agent (applies to all pixels unless overridden)
   * @param string $partner_agent
   * @return $this
   */
  public function setPartnerAgent($partner_agent) {
    $this->partner_agent = $partner_agent;
    return $this;
  }

  /**
   * Validate the request
   * @throws \Exception if validation fails
   */
  protected function validate() {
    if (empty($this->pixel_configs)) {
      throw new \Exception("At least one pixel must be configured. Use addPixel() or setPixels().");
    }

    if (empty($this->events)) {
      throw new \Exception("Events array cannot be empty. Use setEvents().");
    }

    foreach ($this->events as $event) {
      if (!($event instanceof Event)) {
        throw new \Exception("All events must be instances of FacebookAds\\Object\\ServerSide\\Event");
      }
      if (!$event->valid()) {
        $invalid_props = $event->listInvalidProperties();
        throw new \Exception("Event validation failed: " . implode(", ", $invalid_props));
      }
    }
  }

  /**
   * Execute the multi-pixel request
   * Sends the same events to all configured pixels
   *
   * @return MultiPixelEventResponse
   * @throws \Exception if validation fails or if any pixel request fails
   */
  public function execute() {
    $this->validate();

    $responses = array();
    $errors = array();

    if ($this->parallel_execution) {
      $responses = $this->executeParallel();
    } else {
      $responses = $this->executeSequential();
    }

    return new MultiPixelEventResponse($responses);
  }

  /**
   * Execute requests sequentially
   * @return array
   */
  protected function executeSequential() {
    $responses = array();

    foreach ($this->pixel_configs as $config) {
      $pixel_id = $config['pixel_id'];
      try {
        $request = $this->createEventRequest($pixel_id, $config);
        $response = $request->execute();
        $responses[$pixel_id] = array(
          'success' => true,
          'response' => $response,
          'pixel_id' => $pixel_id
        );
      } catch (\Exception $e) {
        $responses[$pixel_id] = array(
          'success' => false,
          'error' => $e->getMessage(),
          'pixel_id' => $pixel_id
        );
      }
    }

    return $responses;
  }

  /**
   * Execute requests in parallel using multi-curl
   * @return array
   */
  protected function executeParallel() {
    $responses = array();
    $curl_handles = array();
    $multi_handle = curl_multi_init();
    $handle_to_pixel = array();

    // Initialize all curl handles
    foreach ($this->pixel_configs as $config) {
      $pixel_id = $config['pixel_id'];

      try {
        $request = $this->createEventRequest($pixel_id, $config);

        // For parallel execution, we need to prepare the request manually
        $curl_handle = $this->prepareCurlHandle($request, $pixel_id, $config);

        if ($curl_handle) {
          curl_multi_add_handle($multi_handle, $curl_handle);
          $curl_handles[] = $curl_handle;
          $handle_to_pixel[(int)$curl_handle] = $pixel_id;
        }
      } catch (\Exception $e) {
        $responses[$pixel_id] = array(
          'success' => false,
          'error' => $e->getMessage(),
          'pixel_id' => $pixel_id
        );
      }
    }

    // Execute all curl handles
    $running = null;
    do {
      curl_multi_exec($multi_handle, $running);
      curl_multi_select($multi_handle);
    } while ($running > 0);

    // Collect responses
    foreach ($curl_handles as $handle) {
      $pixel_id = $handle_to_pixel[(int)$handle];
      $response_data = curl_multi_getcontent($handle);
      $curl_info = curl_getinfo($handle);
      $curl_error = curl_error($handle);

      if ($curl_error) {
        $responses[$pixel_id] = array(
          'success' => false,
          'error' => $curl_error,
          'pixel_id' => $pixel_id
        );
      } else {
        try {
          $response = $this->parseResponse($response_data, $curl_info);
          $responses[$pixel_id] = array(
            'success' => true,
            'response' => $response,
            'pixel_id' => $pixel_id,
            'http_code' => $curl_info['http_code']
          );
        } catch (\Exception $e) {
          $responses[$pixel_id] = array(
            'success' => false,
            'error' => $e->getMessage(),
            'pixel_id' => $pixel_id
          );
        }
      }

      curl_multi_remove_handle($multi_handle, $handle);
      curl_close($handle);
    }

    curl_multi_close($multi_handle);

    return $responses;
  }

  /**
   * Create an EventRequest for a specific pixel
   * @param string $pixel_id
   * @param array $config
   * @return EventRequest
   */
  protected function createEventRequest($pixel_id, $config) {
    $request = new EventRequest($pixel_id);
    $request->setEvents($this->events);

    // Apply global settings
    if ($this->test_event_code !== null) {
      $request->setTestEventCode($this->test_event_code);
    }
    if ($this->partner_agent !== null) {
      $request->setPartnerAgent($this->partner_agent);
    }

    // Apply pixel-specific overrides
    if (isset($config['test_event_code'])) {
      $request->setTestEventCode($config['test_event_code']);
    }
    if (isset($config['partner_agent'])) {
      $request->setPartnerAgent($config['partner_agent']);
    }
    if (isset($config['namespace_id'])) {
      $request->setNamespaceId($config['namespace_id']);
    }
    if (isset($config['upload_id'])) {
      $request->setUploadId($config['upload_id']);
    }
    if (isset($config['upload_tag'])) {
      $request->setUploadTag($config['upload_tag']);
    }
    if (isset($config['upload_source'])) {
      $request->setUploadSource($config['upload_source']);
    }
    if (isset($config['http_client'])) {
      $request->setHttpClient($config['http_client']);
    }
    if (isset($config['custom_endpoint'])) {
      $request->setCustomEndpoint($config['custom_endpoint']);
    }

    return $request;
  }

  /**
   * Prepare a curl handle for parallel execution
   * @param EventRequest $request
   * @param string $pixel_id
   * @param array $config
   * @return resource|false
   */
  protected function prepareCurlHandle($request, $pixel_id, $config) {
    $normalized_params = $request->normalize();

    // Get access token
    $access_token = isset($config['access_token'])
      ? $config['access_token']
      : (HttpServiceClientConfig::getInstance()->getAccessToken()
        ?? \FacebookAds\Api::instance()->getSession()->getAccessToken());

    $normalized_params['access_token'] = $access_token;

    // Add appsecret_proof if available
    $appsecret = isset($config['app_secret'])
      ? $config['app_secret']
      : (HttpServiceClientConfig::getInstance()->getAppsecret()
        ?? \FacebookAds\Api::instance()->getSession()->getAppSecret());

    if ($appsecret) {
      $normalized_params['appsecret_proof'] = Util::getAppsecretProof($access_token, $appsecret);
    }

    // Build URL
    $url = 'https://graph.facebook.com/v' . \FacebookAds\ApiConfig::APIVersion
      . '/' . $pixel_id . '/events';

    // Initialize curl
    $ch = curl_init($url);

    curl_setopt_array($ch, array(
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($normalized_params),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_CAINFO => Util::getCaBundlePath(),
      CURLOPT_HTTPHEADER => array(
        'User-Agent: fbbizsdk-php-v' . \FacebookAds\ApiConfig::SDKVersion,
        'Accept-Encoding: *',
      ),
    ));

    return $ch;
  }

  /**
   * Parse curl response
   * @param string $response_data
   * @param array $curl_info
   * @return EventResponse
   */
  protected function parseResponse($response_data, $curl_info) {
    // Split headers and body
    $header_size = $curl_info['header_size'];
    $body = substr($response_data, $header_size);

    $decoded = json_decode($body, true);

    if ($decoded === null) {
      throw new \Exception("Failed to decode response: " . $body);
    }

    if (isset($decoded['error'])) {
      throw new \Exception("API Error: " . json_encode($decoded['error']));
    }

    return new EventResponse($decoded);
  }

  /**
   * Get count of configured pixels
   * @return int
   */
  public function getPixelCount() {
    return count($this->pixel_configs);
  }
}
