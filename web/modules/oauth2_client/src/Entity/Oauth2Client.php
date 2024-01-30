<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;

/**
 * Defines the oauth2 client entity type.
 *
 * @ConfigEntityType(
 *   id = "oauth2_client",
 *   label = @Translation("OAuth2 Client"),
 *   label_collection = @Translation("OAuth2 Clients"),
 *   label_singular = @Translation("oauth2 client"),
 *   label_plural = @Translation("oauth2 clients"),
 *   label_count = @PluralTranslation(
 *     singular = "@count oauth2 client",
 *     plural = "@count oauth2 clients",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\oauth2_client\Entity\Oauth2ClientListBuilder",
 *     "form" = {
 *       "edit" = "Drupal\oauth2_client\Form\Oauth2ClientForm",
 *       "disable" = "Drupal\oauth2_client\Form\Oauth2ClientDisableForm"
 *     }
 *   },
 *   config_prefix = "oauth2_client",
 *   admin_permission = "administer oauth2_clients",
 *   links = {
 *     "collection" = "/admin/config/system/oauth2-client",
 *     "edit-form" = "/admin/config/system/oauth2-client/{oauth2_client}/edit",
 *     "enable" = "/admin/config/system/oauth2-client/{oauth2_client}/enable",
 *     "disable" = "/admin/config/system/oauth2-client/{oauth2_client}/disable"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "oauth2_client_plugin_id",
 *     "credential_provider",
 *     "credential_storage_key"
 *   }
 * )
 */
class Oauth2Client extends ConfigEntityBase implements Oauth2ClientInterface {

  /**
   * The oauth2 client ID.
   */
  protected string $id;

  /**
   * The oauth2 client label.
   */
  protected string $label;

  /**
   * The oauth2 client status: defaults to false as credentials must be set.
   *
   * @var bool
   */
  protected $status = FALSE;

  /**
   * The oauth2_client description.
   */
  protected string $description = '';

  /**
   * The ID of the associated plugin.
   */
  protected string $oauth2_client_plugin_id;

  /**
   * Credential provider: this module or key module.
   *
   * @var string[
   */
  protected string $credential_provider;

  /**
   * Storage key used by the credential provider.
   *
   * @var string[
   */
  protected string $credential_storage_key;

  /**
   * The associated plugin, configured for use.
   */
  protected DefaultSingleLazyPluginCollection $clientCollection;

  /**
   * {@inheritdoc}
   */
  public function getCredentialProvider(): string {
    return $this->credential_provider ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentialStorageKey(): string {
    return $this->credential_storage_key ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    if ($this->isNew()) {
      return [];
    }
    return [
      'client' => $this->clientPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): ?Oauth2ClientPluginInterface {
    $collections = $this->getPluginCollections();
    if (!empty($collections)) {
      $clientCollection = $collections['client'];
      if ($clientCollection instanceof DefaultSingleLazyPluginCollection) {
        return $clientCollection->get($this->oauth2_client_plugin_id);
      }
    }
    return NULL;
  }

  /**
   * A helper method to build the optional key "collection".
   *
   * Dependency injection is not available in entities.
   *
   * @see https://www.drupal.org/project/drupal/issues/2142515
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   *   The client collection.
   */
  protected function clientPluginCollection(): ?DefaultSingleLazyPluginCollection {
    if ($this->isNew()) {
      return NULL;
    }
    if (!isset($this->clientCollection) && !empty($this->oauth2_client_plugin_id)) {
      $client_config = [
        'uuid' => $this->uuid(),
        'credentials' => [],
      ];
      if (isset($this->credential_provider) && isset($this->credential_storage_key)) {
        $client_config['credentials'] = [
          'credential_provider' => $this->credential_provider,
          'storage_key' => $this->credential_storage_key,
        ];
      }
      $pluginManager = \Drupal::service('oauth2_client.plugin_manager');
      $this->clientCollection = new DefaultSingleLazyPluginCollection($pluginManager, $this->oauth2_client_plugin_id, $client_config);
    }
    return $this->clientCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $credentialProvider = $this->getCredentialProvider();
    if (\Drupal::hasService('key.repository') && $credentialProvider === 'key') {
      /** @var \Drupal\key\KeyRepositoryInterface $keyRepository */
      $keyRepository = \Drupal::service('key.repository');
      $storageKey = $this->getCredentialStorageKey();
      $keyEntity = $keyRepository->getKey($storageKey);
      if (!is_null($keyEntity)) {
        $this->addDependency('config', $keyEntity->getConfigDependencyName());
      }
    }
    return $this;
  }

}
