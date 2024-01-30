<?php

namespace Drupal\opigno_module_restart\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\opigno_learning_path\LPStatusInterface;
use Drupal\opigno_module\Entity\OpignoModule as OpignoModuleBase;
use Drupal\opigno_module_restart\Services\ModuleRestartManager;

/**
 * Overrides the default OpignoModule entity class definition.
 *
 * @package Drupal\opigno_module_restart\Entity
 */
class OpignoModule extends OpignoModuleBase {

  /**
   * {@inheritdoc}
   */
  public function getModuleActiveAttempt(
    AccountInterface $user,
    ?string $activity_link_type = NULL,
    $group_id = NULL
  ): ?EntityInterface {
    $group_id = $group_id ?? $this->getGroupIdCurrentTraining($group_id);
    if (!$group_id) {
      return parent::getModuleActiveAttempt($user, $activity_link_type, $group_id);
    }

    // Module restart should be available only for groups with the free
    // navigation.
    $group = Group::load($group_id);
    if (!ModuleRestartManager::isGroupFreeNavigation($group)) {
      return parent::getModuleActiveAttempt($user, $activity_link_type, $group_id);
    }

    // First check if there is any unfinished module attempt that doesn't relate
    // to any LP attempt.
    $uid = $user->id();
    $unfinished_attempt = $this->getUnfinishedAttempt($uid, $group_id);
    if ($unfinished_attempt) {
      return $unfinished_attempt;
    }

    // Get the last attempt, no matter if it's finished or not.
    // If it's finished, the answers will be updated.
    $lp_attempt = $this->getTrainingActiveAttempt($user, $group);
    $status_storage = static::entityTypeManager()->getStorage('user_module_status');
    $query = $status_storage->getQuery()
      ->condition('module', $this->id())
      ->condition('user_id', $uid)
      ->condition('learning_path', $group_id);
    if ($lp_attempt instanceof LPStatusInterface) {
      $query->condition('lp_status', $lp_attempt->id());
    }
    $attempt = $query->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();

    return $attempt ? $status_storage->load(reset($attempt)) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrainingActiveAttempt(AccountInterface $user, Group $group): ?EntityInterface {
    // The user can restart the activity. In this case the training can be
    // finished and it shouldn't be restarted.
    return static::getLastTrainingAttempt((int) $user->id(), (int) $group->id(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getTakenAttemptsNumber(
    ?AccountInterface $account = NULL,
    ?int $range = NULL,
    ?int $latest_cert_date = NULL,
    bool $finished = FALSE,
    ?int $gid = NULL
  ): int {
    $attempts_number = parent::getTakenAttemptsNumber($account, $range, $latest_cert_date, $finished, $gid);

    // Add the number of re-taken activities.
    $uid = $account ? $account->id() : \Drupal::currentUser()->id();
    $gid = $gid ?? $this->getGroupIdCurrentTraining($gid);
    $last_lp_attempt = static::getLastTrainingAttempt($uid, $gid);
    $query = \Drupal::database()->select('user_module_status', 'ums')
      ->fields('ums', ['restarted_activities_number'])
      ->condition('ums.module', $this->id())
      ->condition('ums.user_id', $uid)
      ->condition('ums.learning_path', $gid);

    // Count only module attempts related to the current LP attempt.
    if ($last_lp_attempt) {
      $query->condition('ums.lp_status', $last_lp_attempt);
    }

    if ($latest_cert_date) {
      $query->condition('ums.started', $latest_cert_date, '>=');
    }

    $restarted_activities_number = $query->execute()->fetchCol();
    if ($restarted_activities_number) {
      $attempts_number += array_sum($restarted_activities_number);
    }

    return $attempts_number;
  }

}
