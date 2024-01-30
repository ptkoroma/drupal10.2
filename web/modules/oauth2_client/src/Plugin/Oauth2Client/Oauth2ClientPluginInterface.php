<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2Client;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oauth2_client\OwnerCredentials;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Interface for Oauth2 Client plugins.
 */
interface Oauth2ClientPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Retrieves the human-readable name of the Oauth2 Client plugin.
   *
   * @return string
   *   The name of the plugin.
   */
  public function getName(): string;

  /**
   * Retrieves the id of the OAuth2 Client plugin.
   *
   * @return string
   *   The id of the plugin.
   */
  public function getId(): string;

  /**
   * Retrieves the grant type of the plugin.
   *
   * @return string
   *   Possible values:
   *   - authorization_code
   *   - client_credentials
   *   - refresh_token
   *   - resource_owner
   */
  public function getGrantType(): string;

  /**
   * Retrieves the client_id of the OAuth2 server.
   *
   * @return string
   *   The client_id of the OAuth2 server.
   */
  public function getClientId(): string;

  /**
   * Retrieves the client_secret of the OAuth2 server.
   *
   * @return string
   *   The client_secret of the OAuth2 server.
   */
  public function getClientSecret(): string;

  /**
   * Retrieves the redirect_uri of the OAuth2 server.
   *
   * @return string
   *   The redirect_uri of the OAuth2 server.
   */
  public function getRedirectUri(): string;

  /**
   * Retrieves the authorization_uri of the OAuth2 server.
   *
   * @return string
   *   The authorization_uri of the OAuth2 server.
   */
  public function getAuthorizationUri(): string;

  /**
   * Retrieves the token_uri of the OAuth2 server.
   *
   * @return string
   *   The authorization_uri of the OAuth2 server.
   */
  public function getTokenUri(): string;

  /**
   * Retrieves the resource owner uri of the OAuth2 server.
   *
   * @return string
   *   The resource_uri of the OAuth2 server.
   */
  public function getResourceUri(): string;

  /**
   * Get an array of additional request parameters on the token request.
   *
   * Merges the request_options parameter from the plugin definition with
   * any passed in options, such as 'code', '`username' or 'password'.
   *
   * @param array $additionalOptions
   *   An array of additional options to merge with request options.
   *
   * @return array
   *   The associative array of parameters.
   */
  public function getRequestOptions(array $additionalOptions = []): array;

  /**
   * Get the set of scopes for the provider to use by default.
   *
   * @return array|null
   *   The list of scopes for the provider to use.
   */
  public function getScopes(): array|null;

  /**
   * Get the separator used to join the scopes in the OAuth2 query string.
   *
   * @return string
   *   The scopes separator to join the list of scopes in the query string.
   */
  public function getScopeSeparator(): string;

  /**
   * Returns a set of collaborator objects for use in the provider.
   *
   * Override this method in your plugin if you wish to provide a collaborator
   * object that requires constructor arguments.
   *
   * @return object[]
   *   An array of collborator objects.
   */
  public function getCollaborators(): array;

  /**
   * Returns the plugin credentials if they are set, otherwise returns NULL.
   *
   * @return string|null
   *   The data.
   */
  public function getCredentialProvider(): ?string;

  /**
   * Stores access tokens obtained by this client.
   *
   * @param \League\OAuth2\Client\Token\AccessTokenInterface $accessToken
   *   The token to store.
   */
  public function storeAccessToken(AccessTokenInterface $accessToken): void;

  /**
   * Retrieve the access token storage.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface|null
   *   The stored token, or NULL if no value exists.
   */
  public function retrieveAccessToken(): ?AccessTokenInterface;

  /**
   * Clears the access token from storage.
   */
  public function clearAccessToken(): void;

  /**
   * Check the plugin definition for success_message or return a static value.
   *
   * @return bool
   *   Should a success message be displayed to the user?
   */
  public function displaySuccessMessage(): bool;

  /**
   * Obtains an existing or a new access token.
   *
   * @param \Drupal\oauth2_client\OwnerCredentials|null $credentials
   *   Optional value object containing the username and password.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface|null
   *   Returns a token or null.
   *
   * @throws \Drupal\oauth2_client\Exception\InvalidOauth2ClientException Thrown in the upstream League library.
   */
  public function getAccessToken(?OwnerCredentials $credentials): ?AccessTokenInterface;

  /**
   * Creates a new provider object.
   *
   * @return \League\OAuth2\Client\Provider\AbstractProvider
   *   The provider of the OAuth2 Server.
   */
  public function getProvider(): AbstractProvider;

}
