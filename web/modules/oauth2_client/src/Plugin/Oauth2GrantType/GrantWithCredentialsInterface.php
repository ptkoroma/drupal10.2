<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2GrantType;

use Drupal\oauth2_client\OwnerCredentials;

/**
 * Additional interface for grant plugins which need credentials.
 */
interface GrantWithCredentialsInterface {

  /**
   * Pass credentials in memory to be used in the oauth request.
   *
   * @param \Drupal\oauth2_client\OwnerCredentials $credentials
   *   A value object containing the username and password.
   */
  public function setUsernamePassword(OwnerCredentials $credentials): void;

}
