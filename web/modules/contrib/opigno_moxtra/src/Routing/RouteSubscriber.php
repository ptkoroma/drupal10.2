<?php

namespace Drupal\opigno_moxtra\Routing;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Session\AccountInterface;
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
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.opigno_moxtra_meeting.canonical')) {
      $route->setRequirement('_custom_access', self::class . '::checkAccess');
    }
  }

  /**
   * Custom access requirement callback.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultForbidden
   */
  public function checkAccess(AccountInterface $account) {
    // Hide 'View' tab for meeting canonical view.
    // Moxtra use opigno_moxtra.meeting route to render meeting page.
    return AccessResult::forbidden();
  }
}
