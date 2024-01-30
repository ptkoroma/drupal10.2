<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oauth2_client\Entity\Oauth2Client;
use Drupal\oauth2_client\Exception\InvalidOauth2ClientException;
use Drupal\oauth2_client\OwnerCredentials;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * The OAuth2 Client service.
 */
class Oauth2ClientService implements Oauth2ClientServiceInterface {

  /**
   * Constructs an Oauth2ClientService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Injected Oauth2 Client plugin manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   Injected Drupal state.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(string $pluginId, ?OwnerCredentials $credentials = NULL): ?AccessTokenInterface {
    try {
      $client = $this->getClient($pluginId);
      return $client->getAccessToken($credentials);
    }
    catch (InvalidOauth2ClientException $e) {
      watchdog_exception('Oauth2 Client', $e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken(string $pluginId): ?AccessTokenInterface {
    try {
      $client = $this->getClient($pluginId);
      return $client->retrieveAccessToken();
    }
    catch (InvalidOauth2ClientException $e) {
      watchdog_exception('Oauth2 Client', $e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearAccessToken(string $pluginId): void {
    try {
      $client = $this->getClient($pluginId);
      $client->clearAccessToken();
    }
    catch (InvalidOauth2ClientException $e) {
      watchdog_exception('Oauth2 Client', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(string $pluginId): Oauth2ClientPluginInterface {
    $config = $this->entityTypeManager->getStorage('oauth2_client')->load($pluginId);
    if ($config instanceof Oauth2Client && $config->status()) {
      $plugin = $config->getClient();
      if ($plugin instanceof Oauth2ClientPluginInterface) {
        return $plugin;
      }
    }
    $disabled = $config instanceof Oauth2Client && !$config->status();
    throw new InvalidOauth2ClientException($pluginId, $disabled);
  }

}
