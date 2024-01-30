<?php

namespace Drupal\opigno_learning_path;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;
use Drupal\opigno_moxtra\MeetingInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\opigno_ilt\ILTInterface;

/**
 * Defines the LP steps service.
 *
 * @package Drupal\opigno_learning_path
 */
class LpSteps {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The database layer.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LpSteps constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user account.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    AccountInterface $current_user,
    Connection $database,
    ModuleHandler $module_handler,
    EntityRepositoryInterface $entity_repository,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->moduleHandler = $module_handler;
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get list of module steps.
   */
  public function getModuleStep($group_id, $uid, OpignoModule $module, $latest_cert_date = NULL) {
    $id = $module->id();
    $key = "{$group_id}_{$uid}_{$id}_{$latest_cert_date}";
    $results = &drupal_static(__FUNCTION__);

    if (!isset($results[$key])) {
      /** @var \Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent $content */
      $content = current(OpignoGroupManagedContent::loadByProperties([
        'group_id' => $group_id,
        'group_content_type_id' => 'ContentTypeModule',
        'entity_id' => $id,
      ]));

      if (!is_object($content)) {
        return array_shift($results);
      }

      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      $module = $this->entityRepository->getTranslationFromContext($module);

      // Get actual score for Module.
      $actual_score = $module->getUserScore($user, $latest_cert_date, $group_id);
      // Get required score.
      $required_score = (int) $content->getSuccessScoreMin();
      // Get attempts.
      $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date, FALSE, $group_id);
      $last_attempt = !empty($attempts) ? end($attempts) : NULL;
      $last_attempt_score = $this->getLastAttemptScore($attempts);
      // Get activities.
      $activities = $module->getModuleActivities(TRUE);

      $options = [
        'required_score' => $required_score,
        'module' => $module,
        'user' => $user,
        'activities' => $activities,
        'latest_cert_date' => $latest_cert_date,
        'actual_score' => $actual_score,
        'attempts' => $attempts,
        'last_attempt' => $last_attempt,
      ];

      $completed_on = $this->stepIsCompleted($options, $last_attempt_score);
      // Check if the module attempt exists in current LP attempt.
      $group_context_id = OpignoGroupContext::getCurrentGroupId() ?? $group_id;
      $is_in_current_lp_attempt = FALSE;
      foreach ($attempts as $attempt) {
        if ($attempt instanceof UserModuleStatusInterface && $attempt->isInCurrentLpAttempt($uid, $group_context_id)) {
          $is_in_current_lp_attempt = TRUE;
          break;
        }
      }

      $results[$key] = [
        // OpignoGroupManagedContent id.
        'cid' => $content->id(),
        // Entity id.
        'id' => $id,
        'name' => $module->label(),
        'description' => $module->get('description')->view(),
        'typology' => 'Module',
        'best score' => $actual_score,
        'current attempt score' => $last_attempt_score,
        'required score' => $required_score,
        'attempts' => count($attempts),
        'activities' => count($activities),
        'time spent' => $this->getTimeSpent($attempts),
        'current attempt time' => 0,
        'completed on' => $completed_on,
        'mandatory' => (int) $content->isMandatory(),
        'is_in_current_lp_attempt' => $is_in_current_lp_attempt,
        'is_last_attempt_not_finished' => $last_attempt instanceof UserModuleStatusInterface && !$last_attempt->isFinished(),
      ];
    }

    return $results[$key];
  }

  /**
   * Builds up a training course step.
   *
   * @param int $group_id
   *   Training group ID.
   * @param int $uid
   *   User ID.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Group entity of the course.
   * @param int|null $latest_cert_date
   *   The timestamp of latest certification date.
   *
   * @return array
   *   Data array about step in a group for a user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCourseStep($group_id, $uid, GroupInterface $course, ?int $latest_cert_date = NULL) {
    $id = $course->id();
    $key = "{$group_id}_{$uid}_{$id}_{$latest_cert_date}";
    $results = &drupal_static(__FUNCTION__);
    if (!isset($results[$key])) {
      /** @var \Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent $content */
      $content = current(OpignoGroupManagedContent::loadByProperties([
        'group_id' => $group_id,
        'group_content_type_id' => 'ContentTypeCourse',
        'entity_id' => $id,
      ]));

      $course_steps = opigno_learning_path_get_steps($id, $uid, NULL, $latest_cert_date);

      // Get best score as an average modules best score.
      if (!empty($course_steps)) {
        $step_count = count($course_steps);
        $best_score = round(array_sum(array_map(function ($step) {
            return $step['best score'];
        }, $course_steps)) / $step_count);
        $score = $this->courseGetScore($course_steps);
      }
      else {
        $best_score = 0;
        $score = 0;
      }

      // Sum of steps attempts.
      $attempts = array_sum(array_map(function ($step) {
        return (int) $step['attempts'];
      }, $course_steps));

      // Sum of steps time spent.
      $time_spent = array_sum(array_map(function ($step) {
        return (int) $step['time spent'];
      }, $course_steps));

      // Sum of steps time spent in the current attempt.
      $time = array_sum(array_map(function ($step) {
        return (int) $step['current attempt time'];
      }, $course_steps));

      // Get completed steps.
      $completed_steps = array_filter($course_steps, function ($step) {
        return $step['completed on'] > 0;
      });

      // If all steps completed.
      if ($course_steps && count($course_steps) === count($completed_steps)) {
        // Get the last completion time.
        $completed_on = max(array_map(function ($step) {
          return $step['completed on'];
        }, $course_steps));
      }
      else {
        $completed_on = 0;
      }

      $description = $course->field_course_description->view();

      $results[$key] = [
        // OpignoGroupManagedContent id.
        'cid' => $content->id(),
        // Entity id.
        'id' => $id,
        'name' => $course->label(),
        'description' => $description,
        'typology' => 'Course',
        'best score' => $best_score,
        'current attempt score' => $score,
        'required score' => (int) $content->getSuccessScoreMin(),
        'attempts' => $attempts,
        'activities' => 0,
        'time spent' => $time_spent,
        'current attempt time' => $time,
        'completed on' => $completed_on,
        'mandatory' => (int) $content->isMandatory(),
      ];
    }

    return $results[$key];
  }

  /**
   * Calculate course score.
   */
  private function courseGetScore(array $course_steps): float {
    $step_count = count($course_steps);
    // If a course contains a mandatory module that has a total max score of 0
    // (because all activities inside have max score 0) then we should
    // consider that score is 100%.
    $set_full = TRUE;
    foreach ($course_steps as $step) {
      if (($step['best score'] != 100) || ($step['mandatory'] != 1)) {
        $set_full = FALSE;
      }
    }
    if ($set_full) {
      return 100;
    }

    return round(array_sum(array_map(function ($step) {
        return $step['current attempt score'];
    }, $course_steps)) / $step_count);
  }

  /**
   * Builds up a training live meeting step.
   *
   * @param int $group_id
   *   Training group ID.
   * @param int $uid
   *   User ID.
   * @param \Drupal\opigno_moxtra\MeetingInterface $meeting
   *   Opigno Moxtra Meeting entity.
   *
   * @return array
   *   Data array about step in a group for a user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMeetingStep($group_id, $uid, MeetingInterface $meeting) {
    $id = $meeting->id();
    $key = "{$group_id}_{$uid}_$id";
    $results = &drupal_static(__FUNCTION__);
    if (!isset($results[$key])) {
      /** @var \Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent $content */
      $content = current(OpignoGroupManagedContent::loadByProperties([
        'group_id' => $group_id,
        'group_content_type_id' => 'ContentTypeMeeting',
        'entity_id' => $meeting->id(),
      ]));

      /** @var \Drupal\opigno_moxtra\MeetingResultInterface[] $meeting_results */
      $meeting_results = $this->entityTypeManager
        ->getStorage('opigno_moxtra_meeting_result')
        ->loadByProperties([
          'user_id' => $uid,
          'meeting' => $meeting->id(),
        ]);

      $presence = 0;
      if (!empty($meeting_results)) {
        $presence = current($meeting_results)->getStatus();
      }

      $scores = array_map(function ($result) {
        /** @var \Drupal\opigno_moxtra\MeetingResultInterface $result */
        return $result->getScore();
      }, $meeting_results);

      if (!empty($scores)) {
        $score = end($scores);
      }
      else {
        $score = 0;
      }

      $start_date = $meeting->getStartDate();
      $end_date = $meeting->getEndDate();

      $start_timestamp = isset($start_date)
        ? DrupalDateTime::createFromFormat(DrupalDateTime::FORMAT, $start_date)
          ->getTimestamp()
        : 0;

      $end_timestamp = isset($end_date)
        ? DrupalDateTime::createFromFormat(DrupalDateTime::FORMAT, $end_date)
          ->getTimestamp()
        : 0;

      $description = (!empty($start_timestamp) && !empty($end_timestamp)) ?
        date('d/m/Y H:i', $start_timestamp) . ' - ' . date('H:i', $end_timestamp) . '<br />' : '';

      $results[$key] = [
        // OpignoGroupManagedContent id.
        'cid' => $content->id(),
        // Entity id.
        'id' => $meeting->id(),
        'name' => $meeting->label(),
        'description' => $description,
        'typology' => 'Meeting',
        'best score' => !empty($scores) ? max($scores) : 0,
        'current attempt score' => $score,
        'required score' => (int) $content->getSuccessScoreMin(),
        'attempts' => count($meeting_results),
        'activities' => 0,
        'time spent' => !empty($scores) ? $end_timestamp - $start_timestamp : 0,
        'current attempt time' => !empty($scores) ? $end_timestamp - $start_timestamp : 0,
        'completed on' => !empty($scores) ? $end_timestamp : 0,
        'mandatory' => (int) $content->isMandatory(),
        'presence' => $presence,
      ];
    }

    return $results[$key];
  }

  /**
   * Builds up a training ILT step.
   *
   * @param int $group_id
   *   Training group ID.
   * @param int $uid
   *   User ID.
   * @param \Drupal\opigno_ilt\ILTInterface $ilt
   *   Opigno ILT entity.
   *
   * @return array
   *   Data array about step in a group for a user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getIltStep($group_id, $uid, ILTInterface $ilt) {
    $id = $ilt->id();
    $key = "{$group_id}_{$uid}_$id";
    $results = &drupal_static(__FUNCTION__);
    if (!isset($results[$key])) {
      /** @var \Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent $content */
      $content = current(OpignoGroupManagedContent::loadByProperties([
        'group_id' => $group_id,
        'group_content_type_id' => 'ContentTypeILT',
        'entity_id' => $ilt->id(),
      ]));

      /** @var \Drupal\opigno_ilt\ILTResultInterface[] $ilt_results */
      $ilt_results = $this->entityTypeManager
        ->getStorage('opigno_ilt_result')
        ->loadByProperties([
          'user_id' => $uid,
          'opigno_ilt' => $ilt->id(),
        ]);

      $presence = 0;
      if (!empty($ilt_results)) {
        $presence = current($ilt_results)->getStatus();
      }

      $scores = array_map(function ($result) {
        /** @var \Drupal\opigno_ilt\ILTResultInterface $result */
        return $result->getScore();
      }, $ilt_results);

      if (!empty($scores)) {
        $score = end($scores);
      }
      else {
        $score = 0;
      }

      $start_date = $ilt->getStartDate();
      $end_date = $ilt->getEndDate();

      $start_timestamp = isset($start_date)
        ? DrupalDateTime::createFromFormat(DrupalDateTime::FORMAT, $start_date)
          ->getTimestamp()
        : 0;

      $end_timestamp = isset($end_date)
        ? DrupalDateTime::createFromFormat(DrupalDateTime::FORMAT, $end_date)
          ->getTimestamp()
        : 0;

      $description = $ilt->getPlace();

      $results[$key] = [
        // OpignoGroupManagedContent id.
        'cid' => $content->id(),
        // Entity id.
        'id' => $ilt->id(),
        'name' => $ilt->label(),
        'description' => $description,
        'typology' => 'ILT',
        'best score' => !empty($scores) ? max($scores) : 0,
        'current attempt score' => $score,
        'required score' => (int) $content->getSuccessScoreMin(),
        'attempts' => count($ilt_results),
        'activities' => 0,
        'time spent' => !empty($scores) ? $end_timestamp - $start_timestamp : 0,
        'current attempt time' => !empty($scores) ? $end_timestamp - $start_timestamp : 0,
        'completed on' => !empty($scores) ? $end_timestamp : 0,
        'mandatory' => (int) $content->isMandatory(),
        'presence' => $presence,
      ];
    }

    return $results[$key];
  }

  /**
   * Get last Attempt score.
   *
   * @param array $attempts
   *   List of attempts.
   *
   * @return numeric
   *   Last score.
   */
  public function getLastAttemptScore(array $attempts) {
    // Get last attempt.
    $last_attempt = end($attempts);
    // Get last attempt score.
    if (!empty($last_attempt)) {
      return $last_attempt->getAttemptScore();
    }
    return 0;
  }

  /**
   * Get Time spent.
   *
   * @param array $attempts
   *   List of attempts.
   *
   * @return numeric
   *   Time was spending for attempts.
   */
  public function getTimeSpent(array $attempts) {
    return array_sum(array_map(function ($attempt) {
      /** @var \Drupal\opigno_module\Entity\UserModuleStatus $attempt */
      $started = (int) $attempt->get('started')->getString();
      $finished = (int) $attempt->get('finished')->getString();

      return $finished > $started ? $finished - $started : 0;
    }, $attempts));
  }

  /**
   * Get list of passed attempts.
   */
  public function passedAttempts($options) {
    return array_filter($options['attempts'], function ($attempt) use (
      $options
    ) {
      /** @var \Drupal\opigno_module\Entity\UserModuleStatus $attempt */
      // Check that all actual module activities is evaluated.
      $evaluated = TRUE;
      $answered_count = 0;
      foreach ($options['activities'] as $activity) {
        $answer = $activity->getUserAnswer($options['module'], $attempt, $options['user'], $options['latest_cert_date']);

        if ($answer === NULL) {
          $evaluated = FALSE;
        }
        else {
          $answered_count++;
        }
      }
      // For random Module option the number of answered activities
      // should be greater than number of random.
      if ($options['module']->getRandomization() == 2) {
        if ($answered_count >= $options['module']->getRandomActivitiesCount()) {
          $evaluated = TRUE;
        }
      }

      $score = $attempt->getAttemptScore();
      return $evaluated && $score >= $options['required_score'];
    });
  }

  /**
   * Get list of passed attempts.
   *
   * @param array $options
   *   List of options.
   * @param float|int|string|null $last_attempt_score
   *   Last Score.
   *
   * @return numeric
   *   When completed.
   */
  public function stepIsCompleted(array $options, &$last_attempt_score = NULL) {
    // If the latest module attempt isn't finished, display it.
    $last_attempt = $options['last_attempt'] ?? NULL;
    if ($last_attempt instanceof UserModuleStatusInterface && !$last_attempt->isFinished()) {
      return 0;
    }

    $passed_attempts = $this->passedAttempts($options);
    $completed_on = 0;

    if ($options['module']->getKeepResultsOption() === 'newest') {
      // Get finish date of the last attempt if passed.
      $last_passed_attempt = end($passed_attempts);
      $last_attempt = end($options['attempts']);
      if (!empty($last_attempt) && !empty($last_passed_attempt)
      && $last_passed_attempt->id() == $last_attempt->id()) {
        $completed_on = $last_attempt->get('finished')->getString();
      }
    }
    // Set correct finished time for 'automatic skills module'.
    elseif ($this->moduleHandler->moduleExists('opigno_skills_system')
      && $options['module']->getSkillsActive()
      && !empty($options['last_attempt'])
    ) {
      if ($options['last_attempt']->isFinished()) {
        $completed_on = $options['last_attempt']->get('finished')->getString();
      }

      // Add cheat for skills modules to jump to the next module if the user
      // already has needed skills.
      if ($options['last_attempt']->getScore() > $options['last_attempt_score']) {
        $last_attempt_score = $options['last_attempt']->getScore();

        if ($completed_on == 0) {
          $completed_on = $options['last_attempt']->get('finished')->getString();
        }
      }
    }
    else {
      // Get finish date of the first attempt that has passed.
      $completed_on = !empty($passed_attempts) ? min(array_map(function ($attempt) {
        /** @var \Drupal\opigno_module\Entity\UserModuleStatus $attempt */
        return (int) $attempt->get('finished')->getString();
      }, $passed_attempts)) : 0;

      // Check if the module contains manually evaluated activities.
      if (!empty($activities)) {
        foreach ($activities as $activity) {
          if ($activity->hasField('opigno_evaluation_method') && $activity->get('opigno_evaluation_method')->value) {
            // Set current attempt score.
            $last_attempt_score = $options['actual_score'];
            break;
          }
        }
      }
    }

    return $completed_on;
  }

}
