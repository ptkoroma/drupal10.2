<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Annotation definition Oauth2Client plugins.
 *
 * @Annotation
 */
class Oauth2Client extends Plugin {

  /**
   * The OAuth 2 plugin ID.
   */
  public string $id;

  /**
   * The human-readable name of the OAuth2 Client.
   *
   * @ingroup plugin_translatable
   */
  public Translation $name;

  /**
   * The grant type of the OAuth2 authorization.
   *
   * Possible values are authorization_code, client_credentials, resource_owner.
   */
  public string $grant_type;

  /**
   * The authorization endpoint of the OAuth2 server.
   */
  public string $authorization_uri;

  /**
   * The token endpoint of the OAuth2 server.
   */
  public string $token_uri;

  /**
   * The resource endpoint of the OAuth2 Server.
   *
   * @deprecated in oauth2_client:3.1.0 and is removed from oauth2_client:4.0.0. Use
   *   the new request options parameter in this annotation instead.
   * @see https://www.drupal.org/project/oauth2_client/issues/3256272
   */
  public string $resource_uri;

  /**
   * The Resource Owner Details endpoint.
   */
  public string $resource_owner_uri = '';

  /**
   * The set of scopes for the provider to use by default.
   *
   * OPTIONAL.
   *
   * @var string[]|null
   */
  public ?array $scopes;

  /**
   * The separator used to join the scopes in the OAuth2 query string.
   *
   * OPTIONAL.
   */
  public string $scope_separator = ',';

  /**
   * An optional set of additional parameters on the token request.
   *
   *  OPTIONAL.
   *  The array key will be used as the request parameter:
   *
   *   request_options = {
   *     "parameter" = "value",
   *   },
   *
   * @var array
   */
  public array $request_options = [];

  /**
   * A flag that may be used by Oauth2ClientPluginInterface::storeAccessToken.
   *
   * OPTIONAL.
   *
   * Implementations may conditionally display a message on successful storage.
   */
  public bool $success_message = FALSE;

  /**
   * An associative array of classes that are composed into the provider.
   *
   * OPTIONAL.
   *
   * Allowed keys are:
   * - grantFactory
   * - requestFactory
   * - httpClient
   * - optionProvider
   *
   * @var string[]|null
   *
   * @see \League\OAuth2\Client\Provider\AbstractProvider::__construct
   * @see \League\OAuth2\Client\Provider\AbstractProvider::setGrantFactory
   * @see \League\OAuth2\Client\Provider\AbstractProvider::setRequestFactory
   * @see \League\OAuth2\Client\Provider\AbstractProvider::setHttpClient
   * @see \League\OAuth2\Client\Provider\AbstractProvider::setOptionProvider
   */
  public ?array $collaborators;

}
