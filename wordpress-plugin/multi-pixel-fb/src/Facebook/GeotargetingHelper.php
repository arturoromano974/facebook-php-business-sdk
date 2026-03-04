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
 * Geotargeting Helper
 *
 * Utility class to automatically extract and enrich UserData with
 * geotargeting information for optimal Facebook Ads tracking.
 *
 * @category    Class
 */
class GeotargetingHelper {

  /**
   * Auto-detect and enrich UserData with geotargeting information
   *
   * This method extracts geolocation data from various sources and
   * enriches the UserData object with location-based information.
   *
   * @param UserData $user_data The UserData object to enrich
   * @param array $options Optional configuration:
   *   - 'ip_address' (string): Override IP address (defaults to $_SERVER['REMOTE_ADDR'])
   *   - 'user_agent' (string): Override user agent (defaults to $_SERVER['HTTP_USER_AGENT'])
   *   - 'accept_language' (string): Browser Accept-Language header
   *   - 'timezone' (string): User timezone (e.g., 'America/New_York')
   *   - 'locale' (string): User locale (e.g., 'en_US', 'pt_BR')
   *   - 'latitude' (float): Geographic latitude
   *   - 'longitude' (float): Geographic longitude
   *   - 'city' (string): City name
   *   - 'state' (string): State/province code
   *   - 'country_code' (string): ISO country code
   *   - 'zip_code' (string): Postal/ZIP code
   * @return UserData The enriched UserData object
   */
  public static function enrichUserData(UserData $user_data, array $options = array()) {
    // Set IP address if not already set
    if ($user_data->getClientIpAddress() === null) {
      $ip = isset($options['ip_address'])
        ? $options['ip_address']
        : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);

      if ($ip) {
        $user_data->setClientIpAddress($ip);
      }
    }

    // Set user agent if not already set
    if ($user_data->getClientUserAgent() === null) {
      $ua = isset($options['user_agent'])
        ? $options['user_agent']
        : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null);

      if ($ua) {
        $user_data->setClientUserAgent($ua);
      }
    }

    // Set geographic location data
    if (isset($options['city']) && $user_data->getCity() === null) {
      $user_data->setCity($options['city']);
    }

    if (isset($options['state']) && $user_data->getState() === null) {
      $user_data->setState($options['state']);
    }

    if (isset($options['country_code']) && $user_data->getCountryCode() === null) {
      $user_data->setCountryCode($options['country_code']);
    }

    if (isset($options['zip_code']) && $user_data->getZipCode() === null) {
      $user_data->setZipCode($options['zip_code']);
    }

    return $user_data;
  }

  /**
   * Extract locale from Accept-Language header
   *
   * @param string|null $accept_language The Accept-Language header value
   * @return string|null The primary locale (e.g., 'en-US', 'pt-BR')
   */
  public static function extractLocale($accept_language = null) {
    if ($accept_language === null && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
      $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    }

    if (!$accept_language) {
      return null;
    }

    // Parse Accept-Language header (e.g., "en-US,en;q=0.9,pt-BR;q=0.8")
    $languages = explode(',', $accept_language);
    if (empty($languages)) {
      return null;
    }

    // Get the first (highest priority) language
    $primary = explode(';', $languages[0])[0];
    return trim($primary);
  }

  /**
   * Detect timezone from JavaScript (requires client-side integration)
   *
   * This method helps create the client-side code needed to capture timezone.
   *
   * @return string JavaScript code to capture timezone
   */
  public static function getTimezoneDetectionScript() {
    return <<<'JAVASCRIPT'
<script>
(function() {
  var timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  var locale = navigator.language || navigator.userLanguage;

  // Store in a form field or send via AJAX
  document.addEventListener('DOMContentLoaded', function() {
    var timezoneField = document.getElementById('user_timezone');
    var localeField = document.getElementById('user_locale');

    if (timezoneField) timezoneField.value = timezone;
    if (localeField) localeField.value = locale;
  });
})();
</script>
JAVASCRIPT;
  }

  /**
   * Create UserData from HTTP request with automatic geotargeting
   *
   * This is a convenience method that creates a UserData object and
   * automatically populates it with all available geotargeting data.
   *
   * @param array $user_info User identification info (emails, phones, etc.)
   * @param array $geo_options Geotargeting options (see enrichUserData)
   * @return UserData
   */
  public static function createFromRequest(array $user_info = array(), array $geo_options = array()) {
    $user_data = new UserData();

    // Set user identification data
    if (isset($user_info['email'])) {
      $user_data->setEmail($user_info['email']);
    }
    if (isset($user_info['emails'])) {
      $user_data->setEmails($user_info['emails']);
    }
    if (isset($user_info['phone'])) {
      $user_data->setPhone($user_info['phone']);
    }
    if (isset($user_info['phones'])) {
      $user_data->setPhones($user_info['phones']);
    }
    if (isset($user_info['first_name'])) {
      $user_data->setFirstName($user_info['first_name']);
    }
    if (isset($user_info['last_name'])) {
      $user_data->setLastName($user_info['last_name']);
    }
    if (isset($user_info['external_id'])) {
      $user_data->setExternalId($user_info['external_id']);
    }
    if (isset($user_info['fbp'])) {
      $user_data->setFbp($user_info['fbp']);
    }
    if (isset($user_info['fbc'])) {
      $user_data->setFbc($user_info['fbc']);
    }

    // Enrich with geotargeting data
    return self::enrichUserData($user_data, $geo_options);
  }

  /**
   * Extract FBP cookie from request
   *
   * @return string|null
   */
  public static function extractFbp() {
    return isset($_COOKIE['_fbp']) ? $_COOKIE['_fbp'] : null;
  }

  /**
   * Extract FBC cookie from request
   *
   * @return string|null
   */
  public static function extractFbc() {
    if (isset($_COOKIE['_fbc'])) {
      return $_COOKIE['_fbc'];
    }

    // Try to build from fbclid URL parameter
    if (isset($_GET['fbclid'])) {
      $timestamp = time();
      return 'fb.1.' . $timestamp . '.' . $_GET['fbclid'];
    }

    return null;
  }

  /**
   * Extract all Facebook tracking parameters
   *
   * @return array Array with 'fbp' and 'fbc' keys
   */
  public static function extractFacebookParams() {
    return array(
      'fbp' => self::extractFbp(),
      'fbc' => self::extractFbc(),
    );
  }

  /**
   * Get country code from IP address using a simple approach
   * Note: For production use, consider using a GeoIP service/database
   *
   * @param string $ip_address
   * @return string|null Two-letter country code
   */
  public static function getCountryFromIp($ip_address) {
    // This is a placeholder. In production, you would use:
    // - MaxMind GeoIP2 database
    // - ip-api.com service
    // - ipinfo.io service
    // - CloudFlare headers (CF-IPCountry)
    // - AWS CloudFront headers

    // Check for CloudFlare country header
    if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
      return strtolower($_SERVER['HTTP_CF_IPCOUNTRY']);
    }

    // Check for AWS CloudFront headers
    if (isset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'])) {
      return strtolower($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY']);
    }

    return null;
  }

  /**
   * Validate and normalize country code
   *
   * @param string $country_code
   * @return string|null Normalized two-letter country code
   */
  public static function normalizeCountryCode($country_code) {
    if (!$country_code || strlen($country_code) !== 2) {
      return null;
    }
    return strtolower($country_code);
  }

  /**
   * Create a fully enriched UserData with all automatic detection
   *
   * @param array $user_info Optional user identification data
   * @return UserData
   */
  public static function autoDetect(array $user_info = array()) {
    $fb_params = self::extractFacebookParams();
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $country_code = self::getCountryFromIp($ip_address);

    $options = array(
      'ip_address' => $ip_address,
      'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
    );

    if ($country_code) {
      $options['country_code'] = $country_code;
    }

    // Merge FB params with user_info
    if ($fb_params['fbp'] && !isset($user_info['fbp'])) {
      $user_info['fbp'] = $fb_params['fbp'];
    }
    if ($fb_params['fbc'] && !isset($user_info['fbc'])) {
      $user_info['fbc'] = $fb_params['fbc'];
    }

    return self::createFromRequest($user_info, $options);
  }
}
