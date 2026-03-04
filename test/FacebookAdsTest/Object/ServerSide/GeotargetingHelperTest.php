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
use FacebookAds\Object\ServerSide\GeotargetingHelper;
use FacebookAds\Object\ServerSide\UserData;

class GeotargetingHelperTest extends AbstractUnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    // Clear any existing $_SERVER values that might interfere with tests
    unset($_SERVER['REMOTE_ADDR']);
    unset($_SERVER['HTTP_USER_AGENT']);
    unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    unset($_SERVER['HTTP_CF_IPCOUNTRY']);
    unset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY']);
    unset($_COOKIE['_fbp']);
    unset($_COOKIE['_fbc']);
    unset($_GET['fbclid']);
  }

  public function testEnrichUserDataWithIpAddress() {
    $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

    $user_data = new UserData();
    $enriched = GeotargetingHelper::enrichUserData($user_data);

    $this->assertEquals('192.168.1.1', $enriched->getClientIpAddress());
  }

  public function testEnrichUserDataWithUserAgent() {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';

    $user_data = new UserData();
    $enriched = GeotargetingHelper::enrichUserData($user_data);

    $this->assertEquals('Mozilla/5.0 Test Browser', $enriched->getClientUserAgent());
  }

  public function testEnrichUserDataWithOptions() {
    $user_data = new UserData();
    $options = [
      'ip_address' => '10.0.0.1',
      'user_agent' => 'Custom Agent',
      'city' => 'São Paulo',
      'state' => 'SP',
      'country_code' => 'br',
      'zip_code' => '01310-100',
    ];

    $enriched = GeotargetingHelper::enrichUserData($user_data, $options);

    $this->assertEquals('10.0.0.1', $enriched->getClientIpAddress());
    $this->assertEquals('Custom Agent', $enriched->getClientUserAgent());
    $this->assertEquals('São Paulo', $enriched->getCity());
    $this->assertEquals('SP', $enriched->getState());
    $this->assertEquals('br', $enriched->getCountryCode());
    $this->assertEquals('01310-100', $enriched->getZipCode());
  }

  public function testEnrichUserDataDoesNotOverrideExisting() {
    $user_data = new UserData();
    $user_data->setClientIpAddress('existing-ip');
    $user_data->setCity('Existing City');

    $options = [
      'ip_address' => 'new-ip',
      'city' => 'New City',
    ];

    $enriched = GeotargetingHelper::enrichUserData($user_data, $options);

    // Should keep existing values
    $this->assertEquals('existing-ip', $enriched->getClientIpAddress());
    $this->assertEquals('Existing City', $enriched->getCity());
  }

  public function testExtractLocale() {
    $locale = GeotargetingHelper::extractLocale('en-US,en;q=0.9,pt-BR;q=0.8');
    $this->assertEquals('en-US', $locale);

    $locale2 = GeotargetingHelper::extractLocale('pt-BR');
    $this->assertEquals('pt-BR', $locale2);

    $locale3 = GeotargetingHelper::extractLocale('fr-FR,fr;q=0.9');
    $this->assertEquals('fr-FR', $locale3);
  }

  public function testExtractLocaleFromServer() {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9';
    $locale = GeotargetingHelper::extractLocale();
    $this->assertEquals('es-ES', $locale);
  }

  public function testExtractLocaleReturnsNullForEmpty() {
    $locale = GeotargetingHelper::extractLocale('');
    $this->assertNull($locale);

    $locale2 = GeotargetingHelper::extractLocale(null);
    $this->assertNull($locale2);
  }

  public function testGetTimezoneDetectionScript() {
    $script = GeotargetingHelper::getTimezoneDetectionScript();

    $this->assertStringContainsString('<script>', $script);
    $this->assertStringContainsString('Intl.DateTimeFormat', $script);
    $this->assertStringContainsString('timezone', $script);
    $this->assertStringContainsString('locale', $script);
  }

  public function testCreateFromRequest() {
    $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
    $_SERVER['HTTP_USER_AGENT'] = 'Test Browser';

    $user_info = [
      'email' => 'test@example.com',
      'phone' => '5511999999999',
      'first_name' => 'John',
      'last_name' => 'Doe',
    ];

    $geo_options = [
      'city' => 'São Paulo',
      'country_code' => 'br',
    ];

    $user_data = GeotargetingHelper::createFromRequest($user_info, $geo_options);

    $this->assertEquals('test@example.com', $user_data->getEmail());
    $this->assertEquals('5511999999999', $user_data->getPhone());
    $this->assertEquals('John', $user_data->getFirstName());
    $this->assertEquals('Doe', $user_data->getLastName());
    $this->assertEquals('192.168.1.100', $user_data->getClientIpAddress());
    $this->assertEquals('Test Browser', $user_data->getClientUserAgent());
    $this->assertEquals('São Paulo', $user_data->getCity());
    $this->assertEquals('br', $user_data->getCountryCode());
  }

  public function testExtractFbp() {
    $_COOKIE['_fbp'] = 'fb.1.1558571054389.1098115397';
    $fbp = GeotargetingHelper::extractFbp();
    $this->assertEquals('fb.1.1558571054389.1098115397', $fbp);
  }

  public function testExtractFbpReturnsNullWhenNotSet() {
    $fbp = GeotargetingHelper::extractFbp();
    $this->assertNull($fbp);
  }

  public function testExtractFbc() {
    $_COOKIE['_fbc'] = 'fb.1.1554763741205.AbCdEfGhIjKlMnOpQrStUvWxYz1234567890';
    $fbc = GeotargetingHelper::extractFbc();
    $this->assertEquals('fb.1.1554763741205.AbCdEfGhIjKlMnOpQrStUvWxYz1234567890', $fbc);
  }

  public function testExtractFbcFromFbclid() {
    $_GET['fbclid'] = 'IwAR1test123';
    $fbc = GeotargetingHelper::extractFbc();

    $this->assertStringStartsWith('fb.1.', $fbc);
    $this->assertStringContainsString('IwAR1test123', $fbc);
  }

  public function testExtractFbcReturnsNullWhenNotSet() {
    $fbc = GeotargetingHelper::extractFbc();
    $this->assertNull($fbc);
  }

  public function testExtractFacebookParams() {
    $_COOKIE['_fbp'] = 'fb.1.123.456';
    $_COOKIE['_fbc'] = 'fb.1.789.012';

    $params = GeotargetingHelper::extractFacebookParams();

    $this->assertArrayHasKey('fbp', $params);
    $this->assertArrayHasKey('fbc', $params);
    $this->assertEquals('fb.1.123.456', $params['fbp']);
    $this->assertEquals('fb.1.789.012', $params['fbc']);
  }

  public function testGetCountryFromIpWithCloudflare() {
    $_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
    $country = GeotargetingHelper::getCountryFromIp('1.2.3.4');
    $this->assertEquals('us', $country);
  }

  public function testGetCountryFromIpWithCloudFront() {
    $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] = 'BR';
    $country = GeotargetingHelper::getCountryFromIp('1.2.3.4');
    $this->assertEquals('br', $country);
  }

  public function testGetCountryFromIpReturnsNullWithoutHeaders() {
    $country = GeotargetingHelper::getCountryFromIp('1.2.3.4');
    $this->assertNull($country);
  }

  public function testNormalizeCountryCode() {
    $this->assertEquals('us', GeotargetingHelper::normalizeCountryCode('US'));
    $this->assertEquals('br', GeotargetingHelper::normalizeCountryCode('BR'));
    $this->assertEquals('gb', GeotargetingHelper::normalizeCountryCode('gb'));

    // Invalid codes
    $this->assertNull(GeotargetingHelper::normalizeCountryCode('USA'));
    $this->assertNull(GeotargetingHelper::normalizeCountryCode('B'));
    $this->assertNull(GeotargetingHelper::normalizeCountryCode(''));
    $this->assertNull(GeotargetingHelper::normalizeCountryCode(null));
  }

  public function testAutoDetect() {
    $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
    $_COOKIE['_fbp'] = 'fb.1.123.456';
    $_COOKIE['_fbc'] = 'fb.1.789.012';
    $_SERVER['HTTP_CF_IPCOUNTRY'] = 'BR';

    $user_info = [
      'email' => 'auto@example.com',
      'phone' => '5511999999999',
    ];

    $user_data = GeotargetingHelper::autoDetect($user_info);

    $this->assertEquals('auto@example.com', $user_data->getEmail());
    $this->assertEquals('5511999999999', $user_data->getPhone());
    $this->assertEquals('192.168.1.1', $user_data->getClientIpAddress());
    $this->assertEquals('Mozilla/5.0', $user_data->getClientUserAgent());
    $this->assertEquals('fb.1.123.456', $user_data->getFbp());
    $this->assertEquals('fb.1.789.012', $user_data->getFbc());
    $this->assertEquals('br', $user_data->getCountryCode());
  }

  public function testAutoDetectDoesNotOverrideFbParams() {
    $_COOKIE['_fbp'] = 'fb.1.cookie.value';
    $_COOKIE['_fbc'] = 'fb.1.cookie.fbc';

    $user_info = [
      'email' => 'test@example.com',
      'fbp' => 'fb.1.custom.fbp',
      'fbc' => 'fb.1.custom.fbc',
    ];

    $user_data = GeotargetingHelper::autoDetect($user_info);

    // Should use provided values, not cookie values
    $this->assertEquals('fb.1.custom.fbp', $user_data->getFbp());
    $this->assertEquals('fb.1.custom.fbc', $user_data->getFbc());
  }
}
