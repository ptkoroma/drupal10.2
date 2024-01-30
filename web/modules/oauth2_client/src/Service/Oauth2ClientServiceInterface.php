<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Service;

use Drupal\oauth2_client\OwnerCredentials;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Interface for the OAuth2 Client service.
 */
interface Oauth2ClientServiceInterface {

  /**
   * Retrieve an OAuth2 Client Plugin.
   *
   * @param string $pluginId
   *   The plugin ID of the client to be retrieved.
   *
   * @return \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface
   *   The OAuth2 Client plugin.
   *
   * @throws \Drupal\oauth2_client\Exception\InvalidOauth2ClientException
   *   Thrown in the plugin.
   */
  public function getClient(string $pluginId): Oauth2ClientPluginInterface;

  /**
   * Obtains an existing or a new access token.
   *
   * @param string $pluginId
   *   The Oauth2Client plugin id.
   * @param \Drupal\oauth2_client\OwnerCredentials|null $credentials
   *   Optional value object containing the username and password.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface|null
   *   Returns a token or null.
   *
   * @throws \Drupal\oauth2_client\Exception\InvalidOauth2ClientException
   *   Thrown in the plugin.
   */
  public function getAccessToken(string $pluginId, ?OwnerCredentials $credentials): ?AccessTokenInterface;

  /**
   * Retrieve an access token from storage.
   *
   * @param string $pluginId
   *   The client for which a provider should be created.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface|null
   *   The Access Token for the given client ID.
   *
   * @throws \Drupal\oauth2_client\Exception\InvalidOauth2ClientException
   *   Thrown in the plugin.
   */
  public function retrieveAccessToken(string $pluginId): ?AccessTokenInterface;

  /**
   * Clears the access token for the given client.
   *
   * @param string $pluginId
   *   The client for which a provider should be created.
   *
   * @throws \Drupal\oauth2_client\Exception\InvalidOauth2ClientException
   *   Thrown in the plugin.
   */
  public function clearAccessToken(string $pluginId): void;

}
