<?php

namespace Drupal\oauth2_client\Plugin\Oauth2Client;

use Drupal\Core\TempStore\PrivateTempStore;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements token storage using PrivateTempStore.
 *
 * This trait implements storage appropriate for a Drupal site in which
 * a user is authenticating against a third-party service to retrieve
 * a token that Drupal can use to access resources on that user's behalf.
 * Storing an AccessToken in PrivateTempStore for the plugin is
 * ensured to be only for a particular user and users can never share data.
 */
trait TempStoreTokenStorage {

  /**
   * Per-user storage service.
   */
  private PrivateTempStore $tempStore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition): Oauth2ClientPluginInterface {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tempStore = $container->get('tempstore.private')
      ->get('authcode_private_temp_store_example');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function storeAccessToken(AccessTokenInterface $accessToken): void {
    $key = 'oauth2_client_access_token-' . $this->getId();
    $this->tempStore->set($key, $accessToken);
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken(): ?AccessTokenInterface {
    $key = 'oauth2_client_access_token-' . $this->getId();
    return $this->tempStore->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function clearAccessToken(): void {
    $key = 'oauth2_client_access_token-' . $this->getId();
    $this->tempStore->delete($key);
  }

}
