<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client;

use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use Drupal\oauth2_client\Plugin\Oauth2Client\TempStoreTokenStorage;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Auth code with access example.
 *
 * @Oauth2Client(
 *   id = "client_cred_test",
 *   name = @Translation("Client Credential Test plugin"),
 *   grant_type = "client_credentials",
 *   authorization_uri = "https://www.example.com/oauth/authorize",
 *   token_uri = "https://www.example.com/oauth/token",
 *   resource_owner_uri = "https://www.example.com/userinfo",
 *   scopes = {"test-1", "test-2"}
 * )
 */
class ClientCredTest extends Oauth2ClientPluginBase {
  use MockClientTrait;
  use TempStoreTokenStorage;

  /**
   * Array to hold response history.
   *
   * @var array
   */
  protected array $responses = [];

  /**
   * {@inheritdoc}
   */
  public function getProvider(): GenericProvider {
    $provider = parent::getProvider();
    $client = $this->getClient();

    $provider->setHttpClient($client);
    return $provider;
  }

}
