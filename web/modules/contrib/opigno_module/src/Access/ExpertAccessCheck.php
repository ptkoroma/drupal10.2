<?php

namespace Drupal\opigno_module\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Routing\Route;

/**
 * Defines the expert-specific access check.
 *
 * @package Drupal\opigno_module\Access
 */
class ExpertAccessCheck implements AccessInterface {

  use StringTranslationTrait;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * ExpertAccessCheck constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user account.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   */
  public function __construct(AccountInterface $current_user, Connection $database) {
    $this->currentUser = $current_user;
    $this->database = $database;
  }

  /**
   * Checks the access based on the expert roles.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check the access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check the access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account): AccessResultInterface {
    return $this->checkAccess($account);
  }

  /**
   * Checks if the user has an expert-specific access.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account to check access for.
   * @param bool $as_bool
   *   If TRUE the boolean value will be returned, AccessResult otherwise.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|bool
   *   The access result.
   */
  public function checkAccess(?AccountInterface $account = NULL, bool $as_bool = FALSE) {
    $account = $account ?? $this->currentUser;
    $allowed_roles = [
      'administrator',
      'content_manager',
      'user_manager',
    ];

    // Uid 1.
    $uid = $account->id();
    if ($uid == 1) {
      return $as_bool ? TRUE : AccessResult::allowed()->addCacheableDependency($account);
    }

    // User roles.
    $roles = $account->getRoles(TRUE);
    if (!empty(array_intersect($roles, $allowed_roles))) {
      return $as_bool ? TRUE : AccessResult::allowed()->addCacheableDependency($account);
    }

    // If class manager in any.
    $query = $this->database->select('group_content_field_data', 'gcfd')
      ->fields('gcfd', ['gid']);
    $query->leftJoin('group_content__group_roles', 'gcgr', 'gcfd.id = gcgr.entity_id');
    $classes = $query->condition('gcgr.group_roles_target_id', 'opigno_class-class_manager')
      ->condition('gcfd.entity_id', $uid)
      ->condition('gcfd.type', 'opigno_class-group_membership')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($classes) {
      return $as_bool ? TRUE : AccessResult::allowed()->addCacheableDependency($account);
    }

    return $as_bool
      ? FALSE
      : AccessResult::forbidden('No custom expert conditions have been met.')->addCacheableDependency($account);
  }

}
