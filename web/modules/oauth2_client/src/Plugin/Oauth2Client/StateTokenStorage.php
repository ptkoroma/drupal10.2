<?php

namespace Drupal\oauth2_client\Plugin\Oauth2Client;

use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Implements token storage using State API.
 *
 * This trait implements storage appropriate for a Drupal site is using a
 * shared resource from a third-party service that provides a service to all
 * users of the site. Storing a single AccessToken in state for the plugin
 * shares access to the external resource for ALL users of this plugin.
 */
trait StateTokenStorage {

  /**
   * Stores access tokens obtained by the client.
   *
   * @param \League\OAuth2\Client\Token\AccessTokenInterface $accessToken
   *   The token to store.
   */
  public function storeAccessToken(AccessTokenInterface $accessToken): void {
    $this->state->set('oauth2_client_access_token-' . $this->getId(), $accessToken);
    if ($this->displaySuccessMessage()) {
      $this->messenger->addStatus(
        $this->t('OAuth token stored.')
      );
    }
  }

  /**
   * Retrieve the access token from storage.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface|null
   *   The stored token, or NULL if no value exists.
   */
  public function retrieveAccessToken(): ?AccessTokenInterface {
    return $this->state->get('oauth2_client_access_token-' . $this->getId());
  }

  /**
   * Clears the access token from storage.
   */
  public function clearAccessToken(): void {
    $this->state->delete('oauth2_client_access_token-' . $this->getId());
  }

}
