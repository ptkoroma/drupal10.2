<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2GrantType;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Interface for oauth2_grant_type plugins.
 */
interface Oauth2GrantTypeInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Get an OAuth2 access token.
   *
   * @param \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $clientPlugin
   *   The client plugin itself so public data can be retrieved.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface|null
   *   The access token provided by the grant.
   */
  public function getAccessToken(Oauth2ClientPluginInterface $clientPlugin): ?AccessTokenInterface;

}
