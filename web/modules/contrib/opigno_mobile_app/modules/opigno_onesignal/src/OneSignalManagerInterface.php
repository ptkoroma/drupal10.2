<?php

namespace Drupal\opigno_onesignal;

use Drupal\Core\Session\AccountProxyInterface;

/**
 * Interface OneSignalManagerInterface.
 */
interface OneSignalManagerInterface {

  public function sendNotificationForUsers($parameters);

  public function checkApiConfigs();

}
