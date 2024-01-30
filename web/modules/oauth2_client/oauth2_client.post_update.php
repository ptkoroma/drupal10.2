<?php

/**
 * @file
 * Post-update hooks provided by the OAuth2 Client module.
 */

use Drupal\oauth2_client\Entity\Oauth2Client;

/**
 * Migrates configuration to new Oauth2Client config entities.
 */
function oauth2_client_post_update_4_0_migrate(): void {
  /** @var \Drupal\oauth2_client\PluginManager\Oauth2ClientPluginManager $manager */
  $manager = \Drupal::service('oauth2_client.plugin_manager');
  /** @var \Drupal\Core\Config\ConfigFactory $configFactory*/
  $configFactory = \Drupal::configFactory();
  $oauth2ClientStorage = \Drupal::entityTypeManager()->getStorage('oauth2_client');
  // Discovery auto-generates needed config entities.
  $plugins = $manager->getDefinitions();
  foreach ($plugins as $id => $plugin) {
    // Get any stored simple configuration for this plugin.
    $config = $configFactory->getEditable('oauth2_client.credentials.' . $id);
    if ($config->isNew()) {
      // Skip ahead - nothing to migrate.
      continue;
    }
    // Get the corresponding config entity.
    $entity = $oauth2ClientStorage->load($id);
    if ($entity instanceof Oauth2Client) {
      $credentialSettings = $config->get('credentials');
      // Move the data.
      $provider = $credentialSettings['credential_provider'] ?? '';
      $storage = $credentialSettings['storage_key'] ?? '';
      $entity->set('credential_provider', $provider);
      $entity->set('credential_storage_key', $storage);
      $entity->enable();
      $entity->save();
      $config->delete();
    }
  }
}
