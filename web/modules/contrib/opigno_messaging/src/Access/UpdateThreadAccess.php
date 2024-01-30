<?php

namespace Drupal\opigno_messaging\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\Routing\Route;

/**
 * Check if the user can edit the given PM thread.
 *
 * @package Drupal\opigno_messaging\Access
 */
class UpdateThreadAccess implements AccessInterface {

  /**
   * Checks the access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check the access to.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user's account.
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $private_message_thread
   *   The thread to check the access to.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, PrivateMessageThreadInterface $private_message_thread): AccessResultInterface {
    if (!$private_message_thread->hasField('field_author')) {
      return AccessResult::forbidden();
    }

    // Only group discussions can be edited be the author.
    $owner = (int) $private_message_thread->get('field_author')->getString();
    $is_group = count($private_message_thread->getMembers()) > 2;

    return AccessResult::allowedIf($is_group && $owner === (int) $account->id());
  }

}
