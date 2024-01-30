<?php

namespace Drupal\opigno_module_restart\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Overrides the module routes to be able to restart activities.
 *
 * @package Drupal\opigno_module_restart\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('opigno_module.take_module')) {
      $route->setDefault('_controller', '\Drupal\opigno_module_restart\Controller\OpignoModuleController::takeModule');
    }
  }

}
