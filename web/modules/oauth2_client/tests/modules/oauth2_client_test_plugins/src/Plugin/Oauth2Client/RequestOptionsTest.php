<?php

namespace Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client;

use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Auth code with access example.
 *
 * @Oauth2Client(
 *   id = "request_options_test",
 *   name = @Translation("Request Options Test plugin"),
 *   grant_type = "authorization_code",
 *   authorization_uri = "https://www.example.com/oauth/authorize",
 *   token_uri = "https://www.example.com/oauth/token",
 *   resource_owner_uri = "https://www.example.com/userinfo",
 *   request_options = {
 *     "test_parameter" = "test_value",
 *   },
 *   scopes = {"a", "b"}
 * )
 */
class RequestOptionsTest extends Oauth2ClientPluginBase {

  /**
   * {@inheritdoc}
   */
  public function storeAccessToken(AccessTokenInterface $accessToken): void {
    // No storage needed for this test.
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken(): ?AccessTokenInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function clearAccessToken(): void {
    // No storage needed for this test.
  }

}
