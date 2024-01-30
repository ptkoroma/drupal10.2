<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;

/**
 * Provides an interface defining an oauth2 client entity type.
 */
interface Oauth2ClientInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Returns the configured Oauth2Client Plugin associated with this entity.
   *
   * @return \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface
   *   The plugin.
   */
  public function getClient(): ?Oauth2ClientPluginInterface;

  /**
   * Get the credential provider stored in this config entity.
   *
   * @return string|null
   *   The credential provider configured in the plugin.
   */
  public function getCredentialProvider(): string;

  /**
   * Get the credential storage key stored in this config entity.
   *
   * @return string|null
   *   The credential storage key configured in the plugin.
   */
  public function getCredentialStorageKey(): string;

}
