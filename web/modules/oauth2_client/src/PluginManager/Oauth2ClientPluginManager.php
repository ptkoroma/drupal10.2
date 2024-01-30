<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\oauth2_client\Plugin\Discovery\Oauth2ClientDiscoveryDecorator;

/**
 * The OAuth 2 Client plugin manager.
 */
class Oauth2ClientPluginManager extends DefaultPluginManager {

  /**
   * Constructs an Oauth2ClientPluginManager object.
   *
   * @param \Traversable $namespaces
   *   Namespaces to be searched for the plugin.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct('Plugin/Oauth2Client', $namespaces, $moduleHandler, 'Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface', 'Drupal\oauth2_client\Annotation\Oauth2Client');

    $this->alterInfo('oauth2_client_info');
    $this->setCacheBackend($cacheBackend, 'oauth2_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $discovery = parent::getDiscovery();
      $this->discovery = new Oauth2ClientDiscoveryDecorator($discovery, $this->entityTypeManager);
    }
    return $this->discovery;
  }

}
