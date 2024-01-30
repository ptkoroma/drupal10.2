<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2GrantType;

use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Handles Client Credential Grants for the OAuth2 Client module..
 *
 * @Oauth2GrantType(
 *   id = "refresh_token",
 *   label = @Translation("Refresh Token Grant"),
 *   description = @Translation("Makes Refresh Token grant requests.")
 * )
 */
class RefreshToken extends Oauth2GrantTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(Oauth2ClientPluginInterface $clientPlugin): ?AccessTokenInterface {
    $accessToken = $clientPlugin->retrieveAccessToken();
    if ($accessToken instanceof AccessTokenInterface) {
      $refreshToken = $accessToken->getRefreshToken();
      $provider = $clientPlugin->getProvider();
      try {
        $newAccessToken = $provider->getAccessToken('refresh_token', [
          'refresh_token' => $refreshToken,
        ]);
        $clientPlugin->storeAccessToken($newAccessToken);
        return $newAccessToken;
      }
      catch (IdentityProviderException $e) {
        // Failed to get a new access token.
        watchdog_exception('OAuth2 Client', $e);
      }
    }
    return NULL;
  }

}
