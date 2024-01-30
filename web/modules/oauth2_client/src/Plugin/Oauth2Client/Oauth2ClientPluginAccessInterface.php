<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2Client;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * An interface for plugins that provide route access methods.
 *
 * @package Drupal\oauth2_client\Plugin\Oauth2Client
 */
interface Oauth2ClientPluginAccessInterface {

  /**
   * Override the default access with this method.
   *
   * The sole route that checks this method is `oauth2_client.code` which
   * captures the returned authorization code from the remote service for
   * plugins that implement the grant type of the same name.  If you are
   * implementing the authorization_code grant and the default, permission
   * controlled access meets your use case, then you can simply implement
   * Oauth2ClientPluginInterface.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Access is checked against this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function codeRouteAccess(AccountInterface $account): AccessResultInterface;

}
