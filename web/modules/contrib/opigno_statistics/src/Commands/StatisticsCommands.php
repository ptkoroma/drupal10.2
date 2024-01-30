<?php

namespace Drupal\opigno_statistics\Commands;

use Drupal\opigno_statistics\Services\UserAchievementManager;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file.
 */
class StatisticsCommands extends DrushCommands {

  /**
   * The user achievement manager service.
   *
   * @var \Drupal\opigno_statistics\Services\UserAchievementManager
   */
  private $userAchievementManager;

  /**
   * Class constructor.
   */
  public function __construct(UserAchievementManager $user_achievement_manager) {
    parent::__construct();
    $this->userAchievementManager = $user_achievement_manager;
  }

  /**
   * Makes update of trainings statistics.
   *
   * @param array $options
   *   Command options.
   *
   * @usage drush statistics-update [uid] [gid]
   *   - Removes statistics records for user with id [uid] and a training with
   *   id [gid] and re-creates them.
   * @usage drush statistics-update 12 23
   *   - Removes statistics records for user with id 12 and a training with id
   *   23 and re-creates them.
   * @usage drush statistics-update
   *   - Removes all the trainings statistics records and re-creates them.
   *
   * @command statistics-update
   * @aliases stup
   * @option uid User entity ID.
   * @option gid Training group entity ID.
   *
   * @throws \Exception
   */
  public function updateStatistics(array $options = [
    'uid' => NULL,
    'gid' => NULL,
  ]) {
    $uid = $options['uid'] ?: FALSE;
    $gid = $options['gid'] ?: FALSE;
    $this->userAchievementManager->updateStatistics($uid, $gid, [$this, 'log']);
  }

  /**
   * Callable log wrapper.
   */
  public function log($message = NULL) {
    $this->output()->writeln($message);
  }

}
