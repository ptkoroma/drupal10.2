<?php

namespace Drupal\Tests\oauth2_client\Unit;

use Drupal\oauth2_client\OwnerCredentials;
use Drupal\oauth2_client\Service\Oauth2ClientService;
use Drupal\Tests\UnitTestCase;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Verify Owner Credentials value object security.
 *
 * @group oauth2_client
 */
class CredentialsTest extends UnitTestCase {

  /**
   * Test if passed credentials visible in trace logs.
   */
  public function testCredentials() {
    $plugin_id = 'test_plugin';
    $username = 'username';
    $password = 'p2ssw0rd';
    try {
      (new TestService())->getAccessToken($plugin_id, new OwnerCredentials($username, $password));
    }
    catch (\Exception $e) {
      // Let's check what information populated in trace logs.
      $this->assertStringNotContainsStringIgnoringCase($password, $e->getTraceAsString());
    }
  }

}

/**
 * Test service class.
 */
final class TestService extends Oauth2ClientService {

  /**
   * Override the construct as it not needed in the test scope.
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public function getAccessToken($pluginId, ?OwnerCredentials $credentials=NULL): ?AccessTokenInterface {
    // Exception happened for some reason.
    throw new \Exception('Test');
  }

}
