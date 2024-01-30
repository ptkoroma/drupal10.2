<?php

namespace Drupal\stripe_registration\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;

class SubscribeMenuLink extends MenuLinkDefault {

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\stripe_registration\Controller\UserSubscriptionsController::subscribeTitle()
   */
  public function getTitle() {
    $stripe_registration = \Drupal::service('stripe_registration.stripe_api');
    $current_user = \Drupal::service('current_user');
    if ($stripe_registration->userHasStripeSubscription($current_user)) {
      return 'Upgrade';
    }
    return 'Subscribe';
  }

}
