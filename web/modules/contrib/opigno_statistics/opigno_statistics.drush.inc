<?php

/**
 * @file
 * Opigno Statistics drush functions.
 */

use Drupal\group\Entity\Group;

/**
 * Implements custom drush command.
 */
function opigno_statistics_drush_command() {
  $commands['statistics-update'] = [
    'description' => 'Makes update of trainings statistics.',
    'arguments' => [
      'uid' => 'User entity ID.',
      'gid' => 'Training group entity ID.',
    ],
    'examples' => [
      'drush statistics-update [uid] [gid]' => '',
      'drush stup [uid] [gid]' => '',
      '-' => '',
      'Examples:' => '',
      '--' => '',
      'drush statistics-update 12 23' => '',
      'drush stup 12 23' => 'Removes statistics records for user with id 12 and a training with id 23 and re-creates them.',
      '---' => '',
      'drush statistics-update' => '',
      'drush stup' => 'Removes all the trainings statistics records and re-creates them.',
    ],
    'callback' => 'opigno_statistics_update_command',
    'aliases' => ['stup'],
  ];

  return $commands;
}

/**
 * Callback for the opigno-statistics-update command.
 */
function opigno_statistics_update_command($uid = NULL, $gid = NULL) {
  if (($uid && !$gid) || (!$uid && $gid)) {
    $message = 'Should be two params - user ID and a training ID.';
    \Drupal::logger('opigno_statistics')->error($message);
    return 2;
  }

  $achievements_table = 'opigno_learning_path_achievements';
  $achievements_steps_table = 'opigno_learning_path_step_achievements';

  $db_connection = \Drupal::service('database');

  if ($gid) {
    $ids = [$gid];
  }
  else {
    $ids = $db_connection->select('groups_field_data', 'g')
      ->fields('g', ['id'])
      ->condition('type', 'learning_path')
      ->execute()->fetchCol();
  }

  if ($ids) {
    $groups = Group::loadMultiple($ids);
    if ($groups) {
      if ($uid && $gid) {
        $db_connection->delete($achievements_table)
          ->condition('uid', $uid)
          ->condition('gid', $gid)
          ->execute();

        $db_connection->delete($achievements_steps_table)
          ->condition('uid', $uid)
          ->condition('gid', $gid)
          ->execute();
      }
      else {
        $db_connection->truncate($achievements_table)->execute();
        $db_connection->truncate($achievements_steps_table)->execute();
      }

      foreach ($groups as $group) {
        // Each training.
        $gid = $group->id();

        $message = 'Group (' . $gid . ') - "' . $group->label() . '"';
        \Drupal::logger('opigno_statistics')->notice($message);
        \Drupal::messenger()->addMessage($message, 'status');

        if ($members = $group->getMembers()) {
          foreach ($members as $group_membership) {
            // Each training member user.
            $user = $group_membership->getUser();
            $member_uid = $user->id();

            if ($uid && $uid != $member_uid) {
              continue;
            }

            $message = ' - user (' . $member_uid . ') - "' . $user->getDisplayName() . '"';
            \Drupal::logger('opigno_statistics')->notice($message);
            \Drupal::messenger()->addMessage($message, 'status');

            try {
              opigno_learning_path_save_achievements($gid, $member_uid);
            }
            catch (\Exception $e) {
              \Drupal::logger('itsp_statistics')->error($e->getMessage());
              \Drupal::messenger()->addMessage($e->getMessage(), 'error');
            }

            if ($steps = opigno_learning_path_get_all_steps($gid, $member_uid)) {
              foreach ($steps as $step) {
                // Each training steps.
                try {
                  // Save current step parent achievements.
                  $parent_id = isset($current_step['parent']) ?
                    opigno_learning_path_save_step_achievements($gid, $member_uid, $step['parent']) : 0;
                  // Save current step achievements.
                  opigno_learning_path_save_step_achievements($gid, $member_uid, $step, $parent_id);
                }
                catch (\Exception $e) {
                  \Drupal::logger('opigno_statistics')->error($e->getMessage());
                  \Drupal::messenger()->addMessage($e->getMessage(), 'error');
                }
              }
            }
          }
        }
      }
    }
  }
}
