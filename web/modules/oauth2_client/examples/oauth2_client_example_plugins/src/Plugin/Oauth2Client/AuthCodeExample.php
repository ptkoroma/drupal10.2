<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client_example_plugins\Plugin\Oauth2Client;

use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use Drupal\oauth2_client\Plugin\Oauth2Client\StateTokenStorage;

/**
 * Auth code example.
 *
 * @Oauth2Client(
 *   id = "authcode_example",
 *   name = @Translation("Example for Authorization Code grant"),
 *   grant_type = "authorization_code",
 *   authorization_uri = "https://oauth.mocklab.io/oauth/authorize",
 *   token_uri = "https://oauth.mocklab.io/oauth/token",
 *   success_message = TRUE
 * )
 */
class AuthCodeExample extends Oauth2ClientPluginBase {

  /*
   * This example assumes that the Drupal site is using a shared resource
   * from a third-party service that provides a service to all uses of the site.
   *
   * Storing a single AccessToken in state for the plugin shares access to the
   * external resource for ALL users of this plugin.
   */

  use StateTokenStorage;

}
