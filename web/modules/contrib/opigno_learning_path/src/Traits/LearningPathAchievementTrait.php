<?php

namespace Drupal\opigno_learning_path\Traits;

use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_learning_path\LearningPathContent;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\OpignoModuleInterface;

/**
 * Provides the LP achievements trait.
 *
 * @package Drupal\opigno_learning_path\Traits
 */
trait LearningPathAchievementTrait {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Retrieves the steps by group.
   */
  public function getStepsByGroup(GroupInterface $group): array {
    $static_steps = &drupal_static(__FUNCTION__, []);

    if (empty($group)) {
      return [];
    }

    if (isset($static_steps[$group->id()])) {
      return $static_steps[$group->id()];
    }
    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);

    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = LearningPathContent::getAllStepsOnlyModules($group->id(), $this->currentUser()
        ->id(), TRUE);
    }
    else {
      // Get guided steps.
      $steps = LearningPathContent::getAllStepsOnlyModules($group->id(), $this->currentUser()
        ->id());
    }
    $user = $this->currentUser();
    $steps = array_filter($steps, function ($step) use ($user) {
      if ($step['typology'] === 'Meeting') {
        // If the user have not the collaborative features role.
        if (!$user->hasPermission('view meeting entities')) {
          return FALSE;
        }

        // If the user is not a member of the meeting.
        /** @var \Drupal\opigno_moxtra\MeetingInterface $meeting */
        $meeting = $this->entityTypeManager()
          ->getStorage('opigno_moxtra_meeting')
          ->load($step['id']);
        if (!$meeting->isMember($user->id())) {
          return FALSE;
        }
      }
      elseif ($step['typology'] === 'ILT') {
        // If the user is not a member of the ILT.
        /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
        $ilt = $this->entityTypeManager()
          ->getStorage('opigno_ilt')
          ->load($step['id']);
        if (!$ilt->isMember($user->id())) {
          return FALSE;
        }
      }

      return TRUE;
    });
    $static_steps[$group->id()] = $steps;
    return $steps;
  }

  /**
   * Current Group Id.
   */
  protected function getCurrentGroupId() {
    if ($group_id = OpignoGroupContext::getCurrentGroupId()) {
      return $group_id;
    }
  }

  /**
   * Current Group Content Id.
   */
  protected function getCurrentGroupContentId() {
    if ($cid = OpignoGroupContext::getCurrentGroupContentId()) {
      return $cid;
    }
  }

  /**
   * Current Opigno Activity Id.
   */
  protected function getCurrentActivityId() {
    if ($opigno_activity = $this->routeMatch()
      ->getParameter('opigno_activity') ?? FALSE) {
      return $opigno_activity->id();
    }
  }

  /**
   * Get last or best user attempt for Module.
   *
   * @param array $attempts
   *   User module attempts.
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $module
   *   Module.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatus
   *   $attempt
   */
  protected function getTargetAttempt(array $attempts, OpignoModuleInterface $module) {
    if ($module->getKeepResultsOption() == 'newest') {
      $attempt = end($attempts);
    }
    else {
      $attempt = opigno_learning_path_best_attempt($attempts);
    }

    return $attempt;
  }

  /**
   * Gets the activities list by the group and module.
   */
  public function getActivities(GroupInterface $training, GroupInterface $course = NULL, OpignoModule $module): array {

    $user = $this->currentUser();
    // @codingStandardsIgnoreLine
    $moduleHandler = \Drupal::moduleHandler();

    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($training, $user->id());
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $attempts */
    $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date);

    if ($moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive() && $module->getModuleSkillsGlobal() && !empty($attempts)) {
      $activities_from_module = $module->getModuleActivities();
      $activity_ids = array_keys($activities_from_module);
      $attempt = $this->getTargetAttempt($attempts, $module);

      // @codingStandardsIgnoreLine
      $db_connection = \Drupal::service('database');
      $query = $db_connection->select('opigno_answer_field_data', 'o_a_f_d');
      $query->leftJoin('opigno_module_relationship', 'o_m_r', 'o_a_f_d.activity = o_m_r.child_id');
      $query->addExpression('MAX(o_a_f_d.activity)', 'id');
      $query->condition('o_a_f_d.user_id', $user->id())
        ->condition('o_a_f_d.module', $module->id());

      if (!$module->getModuleSkillsGlobal()) {
        $query->condition('o_a_f_d.activity', $activity_ids, 'IN');
      }

      $query->condition('o_a_f_d.user_module_status', $attempt->id())
        ->groupBy('o_a_f_d.activity');

      $activities = $query->execute()->fetchAllAssoc('id');
    }
    else {
      $activities = $module->getModuleActivities();
    }
    /** @var \Drupal\opigno_module\Entity\OpignoActivity[] $activities */
    $activities = array_map(function ($activity) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
      return OpignoActivity::load($activity->id);
    }, $activities);
    $attempts = array_filter($attempts, function ($attempt) use ($module, $user, $activities) {
      return !$attempt->isEvaluated() || ($attempt->isEvaluated() && $this->hasAnsweredActivities($module, $user, $attempt, $activities));
    });
    return [$activities, $attempts];
  }

  /**
   * Check if user has answered all activities.
   */
  public function hasAnsweredActivities($module, $user, $attempt, $activities) {
    $answers = [];
    foreach ($activities as $activity) {
      $answers[] = $activity->getUserAnswer($module, $attempt, $user);
    }
    return count(array_filter($answers)) == count($activities);
  }

  /**
   * Gets a statuses of activities.
   */
  public function getActivityStatus($activities, $attempts, $module): array {
    $user = $this->currentUser();
    $attempt = !empty($attempts) ? $this->getTargetAttempt($attempts, $module) : NULL;
    /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
    /** @var \Drupal\opigno_module\Entity\OpignoAnswer $answer */
    $array_map = [];
    foreach ($activities as $key => $activity) {
      $answer = isset($attempt) ? $activity->getUserAnswer($module, $attempt, $user) : NULL;
      if ($answer && $activity->hasField('opigno_evaluation_method') && $activity->get('opigno_evaluation_method')->value && !$answer->isEvaluated()) {
        $state_class = 'pending';
      }
      else {
        $state_class = isset($answer) ? ($answer->isEvaluated() ? 'passed' : 'failed') : ('pending');
      }

      $array_map[$key] = $state_class;
    }
    return $array_map;

  }

  /**
   * Retrieves the entity type manager.
   */
  protected function entityTypeManager() {
    if (!$this->entityTypeManager) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the currently active route match object.
   */
  protected function routeMatch() {
    if (!$this->routeMatch) {
      $this->routeMatch = \Drupal::routeMatch();
    }
    return $this->routeMatch;
  }

  /**
   * Gets a current user.
   */
  public function currentUser() {
    return \Drupal::currentUser();
  }

}
