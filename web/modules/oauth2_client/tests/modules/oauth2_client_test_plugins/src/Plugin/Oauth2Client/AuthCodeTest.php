<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginAccessInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use Drupal\oauth2_client\Plugin\Oauth2Client\StateTokenStorage;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Auth code with access example.
 *
 * @Oauth2Client(
 *   id = "authcode_access_test",
 *   name = @Translation("Auth Code Test plugin"),
 *   grant_type = "authorization_code",
 *   authorization_uri = "https://www.example.com/oauth/authorize",
 *   token_uri = "https://www.example.com/oauth/token",
 *   resource_owner_uri = "https://www.example.com/userinfo",
 * )
 */
class AuthCodeTest extends Oauth2ClientPluginBase implements Oauth2ClientPluginAccessInterface {
  use MockClientTrait;
  use StateTokenStorage;

  /**
   * {@inheritdoc}
   */
  public function codeRouteAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermissions($account, ['access content']);
  }

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
