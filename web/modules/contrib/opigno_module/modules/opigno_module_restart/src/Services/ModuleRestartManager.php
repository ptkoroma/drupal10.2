<?php

namespace Drupal\opigno_module_restart\Services;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_module\Entity\OpignoModuleInterface;

/**
 * Defines the Opigno module/activity restart manager service.
 *
 * @package Drupal\opigno_module_restart\Services
 */
class ModuleRestartManager {

  /**
   * User module status entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $moduleStatusStorage;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * ModuleRestartManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $account) {
    $this->moduleStatusStorage = $entity_type_manager->getStorage('user_module_status');
    $this->account = $account;
  }

  /**
   * Checks if the free navigation enabled for the group or not.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $group
   *   The group to check.
   *
   * @return bool
   *   Whether the free navigation enabled for the group or not.
   */
  public static function isGroupFreeNavigation(?EntityInterface $group): bool {
    return $group instanceof GroupInterface && !OpignoGroupManagerController::getGuidedNavigation($group);
  }

  /**
   * Checks if any not finished attempts exist for the module and LP.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $module
   *   The module entity.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The LP group entity.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user to get attempts for. The current user will be taken by default.
   *
   * @return bool
   *   Whether any not finished attempts exist for the module and LP or not.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function isNotFinishedAttemptsExist(
    OpignoModuleInterface $module,
    GroupInterface $group,
    ?AccountInterface $user = NULL
  ): bool {
    $user = $user ?? $this->account;
    $lp_status = LPStatus::getCurrentLpAttempt($group, $user);

    $query = $this->moduleStatusStorage->getQuery()
      ->condition('module', $module->id())
      ->condition('user_id', $user->id())
      ->condition('learning_path', $group->id())
      ->condition('finished', 0);
    if ($lp_status) {
      $query->condition('lp_status', $lp_status->id());
    }
    $not_finished = $query->count()
      ->execute();

    return (bool) $not_finished;
  }

}
