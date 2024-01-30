<?php

namespace Drupal\opigno_statistics\Commands;

use Drupal\opigno_module\Entity\OpignoAnswer;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drush\Commands\DrushCommands;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\UserModuleStatus;

/**
 * Class OpignoModuleResultsCommands.
 */
class OpignoModuleResultsCommands extends DrushCommands {

  /**
   * Create answer for best result.
   *
   * @param array $options
   *   Command options.
   *
   * @command module-result-update
   * @aliases mru
   * @option module Opigno module ID.
   * @option attempt User module status ID.
   *
   * @throws \Exception
   */
  public function updateStatistics(array $options = [
    'module' => NULL,
    'attempt' => NULL,
    'score' => 0,
  ]) {

    if (empty($options['module']) || empty($options['attempt'])) {
      $this->logger()->error('Module ID and attempt ID are required.');
      return;
    }

    $module = OpignoModule::load($options['module']);
    if (empty($module)) {
      $this->logger()->error('Module not found.');
      return;
    }
    $attempt = UserModuleStatus::load($options['attempt']);
    if (empty($attempt)) {
      $this->logger()->error('Attempt not found.');
      return;
    }

    $score = $options['score'] ?: 0;

    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus $attempt */
    $db = \Drupal::database();
    $query = $db->select('user_module_status', 'um')
      ->fields('um', ['id', 'user_id', 'module', 'evaluated'])
      ->fields('omr', ['child_id', 'parent_id'])
      ->fields('opan', ['score']);
    $query->innerJoin('opigno_module_relationship', 'omr', 'omr.parent_id = um.module');
    $query->leftJoin('opigno_answer_field_data', 'opan',
      'um.id = opan.user_module_status and omr.child_id = opan.activity and opan.module = :module',
      [':module' => $module->id()]
    );
    $query->addExpression('count(*)', 'count1');
    $users_opigno_answers = $query->groupBy('um.id')
      ->groupBy('um.user_id')
      ->groupBy('omr.parent_id')
      ->groupBy('omr.child_id')
      ->condition('um.user_id', $attempt->getOwner()->id())
      ->condition('um.id', $attempt->id())
      ->execute()
      ->fetchAll();

    foreach ($users_opigno_answers as $users_opigno_answer) {
      if (is_null($users_opigno_answer->score)) {
        $activity = OpignoActivity::load($users_opigno_answer->child_id);
        $answer = OpignoAnswer::create([
          'type' => $activity->getType(),
          'user_id' => $attempt->getOwnerId(),
          'user_module_status' => $attempt->id(),
          'module' => $module->id(),
          'activity' => $activity->id(),
          'score' => $score,
          'evaluated' => 1,
        ]);
        $answer->save();

        $this->logger()->info('Answer created for user ' . $attempt->getOwner()->id() . ' and activity ' . $activity->id());
      }
    }
    $this->logger()->info('Done.');
  }

}
