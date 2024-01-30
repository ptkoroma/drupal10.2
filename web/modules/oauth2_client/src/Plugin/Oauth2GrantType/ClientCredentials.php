<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2GrantType;

use Drupal\oauth2_client\OAuth2\Client\OptionProvider\ClientCredentialsOptionProvider;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Handles Client Credential Grants for the OAuth2 Client module..
 *
 * @Oauth2GrantType(
 *   id = "client_credentials",
 *   label = @Translation("Client Credential Grant"),
 *   description = @Translation("Makes Client Credentiale grant requests.")
 * )
 */
class ClientCredentials extends Oauth2GrantTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(Oauth2ClientPluginInterface $clientPlugin): ?AccessTokenInterface {
    $provider = $clientPlugin->getProvider();
    $optionProvider = $provider->getOptionProvider();
    // If the provider was just created, our OptionProvder must be set.
    if (!($optionProvider instanceof ClientCredentialsOptionProvider)) {
      $provider->setOptionProvider(new ClientCredentialsOptionProvider($clientPlugin));
    }
    try {
      return $provider->getAccessToken('client_credentials', $clientPlugin->getRequestOptions());
    }
    catch (\Exception $e) {
      // Failed to get the access token.
      watchdog_exception('OAuth2 Client', $e);
      return NULL;
    }
  }

}
