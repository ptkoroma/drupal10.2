<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client_example_plugins\Plugin\Oauth2Client;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginAccessInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use Drupal\oauth2_client\Plugin\Oauth2Client\TempStoreTokenStorage;

/**
 * Auth code with access example.
 *
 * @Oauth2Client(
 *   id = "authcode_access_example",
 *   name = @Translation("Example for code capture access override"),
 *   grant_type = "authorization_code",
 *   authorization_uri = "https://oauth.mocklab.io/oauth/authorize",
 *   token_uri = "https://oauth.mocklab.io/oauth/token",
 *   resource_owner_uri = "https://oauth.mocklab.io/userinfo",
 * )
 */
class AuthCodeAccessExample extends Oauth2ClientPluginBase implements Oauth2ClientPluginAccessInterface {

  /*
   * This example assumes that a user is authenticating against a third-party
   * service to retrieve a token that Drupal can use to access resources on
   * that user's behalf.
   */
  use TempStoreTokenStorage;

  /**
   * {@inheritdoc}
   */
  public function codeRouteAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermissions($account, ['access content']);
  }

}
