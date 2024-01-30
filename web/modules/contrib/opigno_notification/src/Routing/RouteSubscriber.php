<?php

namespace Drupal\opigno_notification\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the access check for the notifications page.
 *
 * @package Drupal\opigno_notification\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // User notifications list (/notifications) is provided as a view, but the
    // access check was missed. Let's add the route access check to avoid the
    // config re-importing because the view can be already overridden on
    // existing sites.
    if ($route = $collection->get('view.opigno_notifications.page_all')) {
      $route->setRequirement('_role', AccountInterface::AUTHENTICATED_ROLE);
    }
  }

}
