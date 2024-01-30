<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oauth2_client\Entity\Oauth2Client;

/**
 * Auto-create an Oauth2Client config entity for every plugin.
 */
class Oauth2ClientDiscoveryDecorator implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * Storage for our config entity.
   */
  protected EntityStorageInterface $oauth2ClientStorage;

  /**
   * Constructs a decorated discovery object.
   */
  public function __construct(
    protected DiscoveryInterface $decorated,
    EntityTypeManagerInterface $manager) {
    $this->oauth2ClientStorage = $manager->getStorage('oauth2_client');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = $this->decorated->getDefinitions();
    foreach ($definitions as $id => $definition) {
      $client = $this->oauth2ClientStorage->load($id);
      if (!($client instanceof Oauth2Client)) {
        // Create the matching client config entity.
        $newClient = $this->oauth2ClientStorage->create([
          'id' => $id,
          'label' => $definition['name'],
          'description' => '',
          'oauth2_client_plugin_id' => $id,
          'credential_provider' => 'oauth2_client',
          'credential_storage_key' => '',
        ]);
        $newClient->save();
      }
    }
    return $definitions;
  }

  /**
   * Passes through all unknown calls onto the decorated object.
   *
   * @param string $method
   *   The function name to pass through.
   * @param mixed $args
   *   The arguments passed through.
   *
   * @return mixed
   *   The return from the upstream function.
   */
  public function __call(string $method, mixed $args): mixed {
    return call_user_func_array([$this->decorated, $method], $args);
  }

}
