<?php

namespace Drupal\opigno_mobile_app\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -300];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    /** @var \Symfony\Component\Routing\Route $route */
    // Restrict access to default route with jwt token.
    if ($route = $collection->get('jwt_auth_issuer.jwt_auth_issuer_controller_generateToken')) {
      $route->setRequirement('_access', 'FALSE');
    }
  }
}
