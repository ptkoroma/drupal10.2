<?php

namespace Drupal\opigno_onesignal\Config;

use Drupal\Core\Config\ConfigFactory;

/**
 * Manages Opigno One Signal module configuration.
 *
 * @package Drupal\onesignal\Config
 */
class ConfigManager implements ConfigManagerInterface {
  
  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;
  
  /**
   * ConfigManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->config = $configFactory->getEditable('opigno_onesignal.config');
  }
  
  /**
   * {@inheritdoc}
   */
  public function getAppId() {
    return $this->config->get('onesignal_app_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getRestApiKey() {
    return $this->config->get('onesignal_rest_api_key');
  }
}
