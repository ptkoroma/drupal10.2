<?php

namespace Drupal\opigno_learning_path\Services;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoaderInterface;

/**
 * The Opigno user access manager service.
 *
 * @package Drupal\opigno_learning_path\Services
 */
class UserAccessManager {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * UserAccessManager constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader service.
   */
  public function __construct(AccountInterface $account, GroupMembershipLoaderInterface $membership_loader) {
    $this->account = $account;
    $this->membershipLoader = $membership_loader;
  }

  /**
   * Check the access to the given profile info based on group permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to check the access to.
   *
   * @return bool
   *   If the current user can view the given user's profile based on group
   *   permissions.
   */
  private function checkGroupManagersAccess(AccountInterface $user): bool {
    $access = FALSE;
    // Get all user groups.
    $memberships = $this->membershipLoader->loadByUser($user);
    if (!$memberships) {
      return $access;
    }

    // Check the specific group permissions.
    foreach ($memberships as $membership) {
      if (!$membership instanceof GroupMembership) {
        continue;
      }

      $group = $membership->getGroup();
      $bundle = $group->bundle();
      if (($bundle === 'opigno_class' && $group->hasPermission('view any private profile in class', $this->account))
        || ($bundle === 'learning_path' && $group->hasPermission('view any private profile in training', $this->account))
      ) {
        $access = TRUE;
        break;
      }
    }

    return $access;
  }

  /**
   * Check if the current user can view the given user's private profile.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to check the access to.
   *
   * @return bool
   *   If the current user can view the given user's private profile.
   */
  public function canViewPrivateProfile(AccountInterface $user): bool {
    // Current user and users with the global access can view the full profile
    // info.
    if ((int) $user->id() === (int) $this->account->id()
      || $this->account->hasPermission('view any private profile')
    ) {
      return TRUE;
    }

    return $this->checkGroupManagersAccess($user);
  }

  /**
   * Check if the current user can view the user's achievements page.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to check the achievements page access to.
   *
   * @return bool
   *   If the current user can view the given user's achievements page.
   */
  public function canAccessUserStatistics(AccountInterface $user): bool {
    // Current user and users with the global access can view the full profile
    // info.
    if ((int) $user->id() === (int) $this->account->id()
      || $this->account->hasPermission('view global statistics')
      || $this->account->hasPermission('view any user statistics')
    ) {
      return TRUE;
    }

    return $this->checkGroupManagersAccess($user);
  }

}
