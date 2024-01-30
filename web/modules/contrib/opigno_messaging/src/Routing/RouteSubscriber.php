<?php

namespace Drupal\opigno_messaging\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('private_message.private_message_page')) {
      $route->setDefault('_title', 'Messages');
    }

    if ($route = $collection->get('entity.private_message_thread.canonical')) {
      $route->setDefault('_title', 'Messages');
    }
  }

}
