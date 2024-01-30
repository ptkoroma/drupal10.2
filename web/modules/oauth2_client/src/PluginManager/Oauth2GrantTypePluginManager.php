<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\PluginManager;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypeInterface;

/**
 * Oauth2GrantType plugin manager.
 */
class Oauth2GrantTypePluginManager extends DefaultPluginManager {

  /**
   * Constructs Oauth2GrantTypePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Oauth2GrantType',
      $namespaces,
      $module_handler,
      'Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypeInterface',
      'Drupal\oauth2_client\Annotation\Oauth2GrantType'
    );
    $this->setCacheBackend($cache_backend, 'oauth2_grant_type_plugins');

  }

  /**
   * Creates a pre-configured instance of a plugin.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypeInterface
   *   A fully configured plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []): Oauth2GrantTypeInterface {
    /** @var \Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypeInterface $instance */
    $instance = parent::createInstance($plugin_id, $configuration);
    return $instance;
  }

  /**
   * Gets a preconfigured instance of a plugin.
   *
   * @param array $options
   *   An array of options that can be used to determine a suitable plugin to
   *   instantiate and how to configure it.
   *
   * @return \Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypeInterface
   *   A fully configured plugin instance. The interface of the plugin instance
   *   will depend on the plugin type. If no instance can be retrieved, FALSE
   *   will be returned.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be found.
   */
  public function getInstance(array $options): Oauth2GrantTypeInterface {
    $instance = parent::getInstance($options);
    if ($instance === FALSE) {
      throw new PluginException('Oauth2GrantTypePluginManager::getInstance failed to find a plugin for the given options');
    }
    /** @var \Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypeInterface $instance */
    return $instance;
  }

}
