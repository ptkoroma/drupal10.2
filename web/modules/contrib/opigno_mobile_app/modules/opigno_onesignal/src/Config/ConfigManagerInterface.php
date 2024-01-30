<?php

namespace Drupal\opigno_onesignal\Config;

/**
 * Interface for manage Opigno One Signal module configuration.
 *
 * @package Drupal\opigno_onesignal\Config
 */
interface ConfigManagerInterface {
  
  /**
   * Provides One Signal App id.
   *
   * @return string
   */
  public function getAppId();

  /**
   * Provides Onesignal REST Api key.
   *
   * @return string
   */
  public function getRestApiKey();
}
