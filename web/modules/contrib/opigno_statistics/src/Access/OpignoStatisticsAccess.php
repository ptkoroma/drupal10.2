<?php

namespace Drupal\opigno_statistics\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\user\UserInterface;

/**
 * Opigno Statistics Access.
 */
class OpignoStatisticsAccess {

  /**
   * Checks access for a route with group in params.
   */
  public function accessGroup(UserInterface $user, GroupInterface $group) {
    $account = \Drupal::currentUser();
    return opigno_statistics_group_access($group, 'view statistics', $account);
  }

  /**
   * Checks access for a route with group and module in params.
   */
  public function accessModule(UserInterface $user, GroupInterface $training, OpignoModule $module) {
    $account = \Drupal::currentUser();
    // Allow users to view their own profile.
    if ($account->id() === $user->id()) {
      return AccessResult::allowed();
    }
    return opigno_statistics_group_access($training, 'view statistics', $account);
  }

}
