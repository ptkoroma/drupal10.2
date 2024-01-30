<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_ilt\Entity\ILT;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_learning_path\Progress;
use Drupal\opigno_learning_path\Services\LearningPathContentService;
use Drupal\opigno_learning_path\Services\UserAccessManager;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;
use Drupal\opigno_module\OpignoModuleBadges;
use Drupal\opigno_moxtra\Entity\Meeting;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the LP achievements controller.
 *
 * @package Drupal\opigno_learning_path\Controller
 */
class LearningPathAchievementController extends ControllerBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Progress bar service.
   *
   * @var \Drupal\opigno_learning_path\Progress
   */
  protected $progress;

  /**
   * Formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The user access manager.
   *
   * @var \Drupal\opigno_learning_path\Services\UserAccessManager
   */
  protected $userAccessManager;

  /**
   * Service entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * LearningPathAchievementController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\opigno_learning_path\Progress $progress
   *   The LP progress service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\opigno_learning_path\Services\UserAccessManager $user_access_manager
   *   The user access manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(
    Connection $database,
    Progress $progress,
    DateFormatterInterface $date_formatter,
    UserAccessManager $user_access_manager,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    EntityRepositoryInterface $entity_repository
  ) {
    $this->database = $database;
    $this->progress = $progress;
    $this->dateFormatter = $date_formatter;
    $this->userAccessManager = $user_access_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('opigno_learning_path.progress'),
      $container->get('date.formatter'),
      $container->get('opigno_learning_path.user_access_manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('entity.repository')
    );
  }

  /**
   * Returns max score that user can have in this module & activity.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module object.
   * @param \Drupal\opigno_module\Entity\OpignoActivity $activity
   *   Activity object.
   *
   * @return int
   *   Max score.
   */
  protected function getActivityMaxScore(OpignoModule $module, OpignoActivity $activity): int {
    $query = $this->database->select('opigno_module_relationship', 'omr')
      ->fields('omr', ['max_score'])
      ->condition('omr.child_id', $activity->id())
      ->condition('omr.child_vid', $activity->getRevisionId())
      ->condition('omr.activity_status', 1);

    if (!$this->moduleHandler->moduleExists('opigno_skills_system')
      || !$module->getSkillsActive()
      || !$module->getModuleSkillsGlobal()
    ) {
      $query->condition('omr.parent_id', $module->id())
        ->condition('omr.parent_vid', $module->getRevisionId());
    }
    $results = $query->execute()->fetchAll();

    if (empty($results)) {
      return 0;
    }

    $result = reset($results);
    return $result->max_score;
  }

  /**
   * Returns module panel renderable array.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Group.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   * @param \Drupal\group\Entity\GroupInterface|null $course
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return array
   *   Module panel renderable array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function buildModulePanel(
    GroupInterface $training,
    OpignoModule $module,
    ?GroupInterface $course = NULL,
    ?AccountInterface $account = NULL
  ): array {
    $user = $this->currentUser($account);

    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($training, $user->id());

    $parent = $course ?? $training;
    // It will keep the parent id (training) as the group context for the course
    // page.
    $update_group_context = !isset($course);
    $step = opigno_learning_path_get_module_step($parent->id(), $user->id(), $module, $latest_cert_date, $update_group_context);

    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = OpignoModule::load($step['id']);
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $attempts */
    $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date);

    if ($this->moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive() && $module->getModuleSkillsGlobal() && !empty($attempts)) {
      $activities_from_module = $module->getModuleActivities();
      $activity_ids = array_keys($activities_from_module);
      $attempt = $this->getTargetAttempt($attempts, $module);

      $query = $this->database->select('opigno_answer_field_data', 'o_a_f_d');
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
      $activity = OpignoActivity::load($activity->id);
      return $this->entityRepository->getTranslationFromContext($activity);
    }, $activities);

    if (!empty($attempts)) {
      // If "newest" score - get the last attempt,
      // else - get the best attempt.
      $attempt = $this->getTargetAttempt($attempts, $module);
      $max_score = $attempt->calculateMaxScore();
      $score_percent = $attempt->getAttemptScore();
      $score = round($score_percent * $max_score / 100);
    }
    else {
      $attempt = NULL;
      $max_score = !empty($activities)
        ? array_sum(array_map(function ($activity) use ($module) {
          return (int) $this->getActivityMaxScore($module, $activity);
        }, $activities))
        : 0;
      $score = 0;
    }
    $activities_done = 0;
    $activities = array_map(function ($activity) use ($user, $module, $attempt, &$activities_done) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
      /** @var \Drupal\opigno_module\Entity\OpignoAnswer $answer */
      $answer = isset($attempt)
        ? $activity->getUserAnswer($module, $attempt, $user)
        : NULL;
      $score = isset($answer) ? $answer->getScore() : 0;
      $max_score = (int) $this->getActivityMaxScore($module, $activity);

      if ($max_score == 0 && $activity->get('auto_skills')->getValue()[0]['value'] == 1) {
        $max_score = 10;
      }

      if ($answer && $activity->hasField('opigno_evaluation_method') && $activity->get('opigno_evaluation_method')->value && !$answer->isEvaluated()) {
        $state_class = 'pending';
      }
      else {
        $state_class = isset($answer) ? ($answer->isEvaluated() ? 'passed' : 'failed') : ('pending');
      }

      if ($state_class == 'passed') {
        $activities_done++;
      }
      return [
        [
          'class' => 'name',
          'data' => $activity->getName(),
        ],
        [
          'class' => 'progress',
          'data' => [
            '#markup' => $score . '/' . $max_score,
          ],
        ],
        [
          'class' => 'status',
          'data' => [
            '#type' => 'inline_template',
            '#template' => '<div class="status-wrapper"><span class="led {{state_class}}">{{state_label}}</span></div>',
            '#context' => [
              'state_class' => $state_class,
              'state_label' => [
                'passed' => $this->t('Done'),
                'pending' => $this->t('Pending'),
                'failed' => $this->t('Failed'),
              ][$state_class],
            ],
          ],
        ],
      ];
    }, $activities);

    $activities = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table'],
      ],
      '#header' => [
        $this->t('Activity'),
        $this->t('Score'),
        $this->t('Status'),
      ],
      '#rows' => $activities,
    ];

    $info_items = [
      [
        '#type' => 'inline_template',
        '#template' => '<div class="activity-info__item"><div class="label">{{"Activities Done"|t}}</div><div class="value"><span>{{activities_done}}/{{activities}}</span></div></div>',
        '#context' => [
          'activities_done' => (int) $activities_done,
          'activities' => (int) $step["activities"],
        ],
      ],
      [
        '#type' => 'inline_template',
        '#template' => '<div class="activity-info__item"><div class="label">{{"Score"|t}}</div><div class="value"><span>{{score}}/{{max_score}}</span></div></div>',
        '#context' => [
          'score' => (int) $score,
          'max_score' => (int) $max_score,
        ],
      ],
    ];
    if ($module && $attempt && $attempt->isEvaluated()) {
      $see_activity = Link::createFromRoute(
        $this->t('See activity results'),
        'opigno_module.module_result',
        [
          'opigno_module' => $module->id(),
          'user_module_status' => $attempt->id(),
        ],
        ['query' => ['skip-links' => TRUE]]
      )->toRenderable();
      $see_activity['#attributes'] = [
        'class' => 'btn btn-rounded btn-small',
      ];
    }
    return [
      '#type' => 'container',
      'activities' => $activities,
      'info_items' => $info_items,
      'link' => $see_activity ?? [],
    ];
  }

  /**
   * Returns module approved activities.
   *
   * @param int $parent
   *   Group ID.
   * @param int $module
   *   Module ID.
   * @param int|null $latest_cert_date
   *   The latest certification date timestamp.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return int
   *   Approved activities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function moduleApprovedActivities(
    int $parent,
    int $module,
    ?int $latest_cert_date = NULL,
    ?AccountInterface $account = NULL
  ): int {
    $approved = 0;
    $user = $this->currentUser($account);
    $parent = Group::load($parent);
    $module = OpignoModule::load($module);
    $step = opigno_learning_path_get_module_step($parent->id(), $user->id(), $module, $latest_cert_date);

    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = OpignoModule::load($step['id']);
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $attempts */
    $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date);

    if ($this->moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive() && $module->getModuleSkillsGlobal() && !empty($attempts)) {
      $activities_from_module = $module->getModuleActivities();
      $activity_ids = array_keys($activities_from_module);
      $attempt = $this->getTargetAttempt($attempts, $module);

      $query = $this->database->select('opigno_answer_field_data', 'o_a_f_d');
      $query->leftJoin('opigno_module_relationship', 'o_m_r', 'o_a_f_d.activity = o_m_r.child_id');
      $query->addExpression('MAX(o_a_f_d.activity)', 'id');
      $query->condition('o_a_f_d.user_id', $user->id())
        ->condition('o_a_f_d.module', $module->id());

      if (!$module->getModuleSkillsGlobal()) {
        $query->condition('o_a_f_d.activity', $activity_ids, 'IN');
      }

      $query->condition('o_a_f_d.user_module_status', $attempt->id())
        ->condition('o_m_r.max_score', '', '<>')
        ->groupBy('o_a_f_d.activity');

      $activities = $query->execute()->fetchAllAssoc('id');
    }
    else {
      $activities = $module->getModuleActivities();
    }
    /** @var \Drupal\opigno_module\Entity\OpignoActivity[] $activities */
    $activities = array_map(function ($activity) {
      $activity = OpignoActivity::load($activity->id);
      return $this->entityRepository->getTranslationFromContext($activity);
    }, $activities);

    if (!empty($attempts)) {
      // If "newest" score - get the last attempt,
      // else - get the best attempt.
      $attempt = $this->getTargetAttempt($attempts, $module);
    }
    else {
      $attempt = NULL;
    }

    $activities = array_map(function ($activity) use ($user, $module, $attempt) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
      /** @var \Drupal\opigno_module\Entity\OpignoAnswer $answer */
      $answer = isset($attempt)
        ? $activity->getUserAnswer($module, $attempt, $user)
        : NULL;

      return [
        isset($answer) ? 'lp_step_state_passed' : 'lp_step_state_failed',
      ];
    }, $activities);

    foreach ($activities as $activity) {

      if ($activity[0] == 'lp_step_state_passed') {
        $approved++;
      }
    }

    return $approved;
  }

  /**
   * Returns course steps renderable array.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Parent training group entity.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course group entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   User account entity.
   *
   * @return array
   *   Course steps renderable array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function buildCourseSteps(
    GroupInterface $training,
    GroupInterface $course,
    ?AccountInterface $account = NULL
  ): array {
    $user = $this->currentUser($account);
    $steps = opigno_learning_path_get_steps($course->id(), $user->id());

    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($training, $user->id());

    $steps = array_map(static function ($step) use ($user, $latest_cert_date) {
      $step['status'] = opigno_learning_path_get_step_status($step, $user->id(), TRUE, $latest_cert_date);
      $step['attempted'] = opigno_learning_path_is_attempted($step, $user->id());
      $step['progress'] = opigno_learning_path_get_step_progress($step, $user->id(), FALSE, $latest_cert_date);
      return $step;
    }, $steps);

    $course_steps = array_map(function ($step) use ($training, $course, $user, $latest_cert_date) {
      $time_spent = $this->getTimeSpentByStep($step);
      $completed = $this->getComplitedByStep($step);
      $time_spent = $time_spent ? $this->dateFormatter->formatInterval($time_spent) : 0;
      $completed = $completed ? $this->dateFormatter->format($completed, 'custom', 'm/d/Y') : '';
      [$approved, $approved_percent] = $this->getApprovedModuleByStep($step, $user, $latest_cert_date, $training);
      $badges = $this->getModulesStatusBadges($step, $training, $user->id());

      /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
      $module = OpignoModule::load($step['id']);
      return [
        '#theme' => 'opigno_learning_path_training_module',
        '#status' => $this->mapStatusToTemplateClasses($step['status']),
        '#group_id' => $course->id(),
        '#step' => $step,
        '#time_spent' => $time_spent,
        '#completed' => $completed,
        '#badges' => $badges,
        '#approved' => [
          'value' => $approved,
          'percent' => $approved_percent,
        ],
        '#activities' => $this->buildModulePanel($training, $module, $course, $user),
      ];
    }, $steps);

    return [
      '#theme' => 'opigno_learning_path_training_course_content',
      '#course_id' => $course->id(),
      [$course_steps],
    ];
  }

  /**
   * Returns course passed steps.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Parent training group entity.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course group entity.
   * @param int|null $latest_cert_date
   *   The latest certificate date timestamp.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user account.
   *
   * @return array
   *   Course passed steps.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function courseStepsPassed(
    GroupInterface $training,
    GroupInterface $course,
    ?int $latest_cert_date = NULL,
    ?AccountInterface $user = NULL
  ): array {
    $user = $user ?: $this->currentUser();
    $steps = opigno_learning_path_get_steps($course->id(), $user->id(), NULL, $latest_cert_date);

    $passed = 0;
    foreach ($steps as $step) {
      $status = opigno_learning_path_get_step_status($step, $user->id(), FALSE, $latest_cert_date);
      if ($status == 'passed') {
        $passed++;
      }
    }

    return [
      'passed' => $passed,
      'total' => count($steps),
    ];
  }

  /**
   * Matching a status to a class for template rendering.
   */
  public function mapStatusToTemplateClasses($status): array {
    $steps_status = [
      'pending' => [
        'class' => 'in progress',
        'title' => $this->t('Pending'),
      ],
      'failed' => [
        'class' => 'failed',
        'title' => $this->t('Failed'),
      ],
      'passed' => [
        'class' => 'passed',
        'title' => $this->t('Passed'),
      ],
    ];
    return $steps_status[$status] ?? $steps_status['pending'];
  }

  /**
   * Gets an approved state by the step.
   *
   * Copy of legacy code.
   */
  public function getApprovedModuleByStep(&$step, $user, $latest_cert_date, $group): array {
    $module = OpignoModule::load($step['id']);
    if ($this->moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive()) {
      $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date);
      $attempt = $this->getTargetAttempt($attempts, $module);

      $account = $this->entityTypeManager->getStorage('user')->load($attempt->getOwnerId());
      if (!$account instanceof AccountInterface) {
        return [0, 0];
      }

      $answers = $module->userAnswers($account, $attempt);
      $count_answers_from_step = count($answers);

      $approved = $count_answers_from_step . '/' . $count_answers_from_step;
      $approved_percent = 100;

      $step['progress'] = 1;
    }
    else {
      $approved_activities = $this->moduleApprovedActivities($group->id(), $step['id'], $latest_cert_date, $user);
      $approved = $approved_activities . '/' . $step['activities'];
      $approved_percent = $approved_activities / $step['activities'] * 100;
    }
    return [
      $approved ?? 0,
      $approved_percent ?? 0,
    ];
  }

  /**
   * Gets an passed/percent of course state by the step.
   *
   * Copy of legacy code.
   */
  public function getStatusPercentCourseByStep($step, $latest_cert_date, $group, $user): array {
    $course_steps = $this->courseStepsPassed($group, Group::load($step['id']), $latest_cert_date, $user);
    $passed = $course_steps['passed'] . '/' . $course_steps['total'];
    $passed_percent = round(($course_steps['passed'] / $course_steps['total']) * 100);
    $score = $step['best score'];
    $score_percent = $score;
    return [
      $passed,
      $passed_percent,
      $score_percent,
    ];
  }

  /**
   * Gets the badges by the step.
   *
   * Copy of legacy code.
   */
  public function getModulesStatusBadges($step, $group, $uid) {
    // Get existing badge count.
    $badges = 0;
    if (
      in_array($step['typology'], ['Course', 'Module']) &&
      $this->moduleHandler->moduleExists('opigno_module')
    ) {
      $result = OpignoModuleBadges::opignoModuleGetBadges($uid, $group->id(), $step['typology'], $step['id']);
      if ($result) {
        $badges = $result;
      }
    }
    return $badges;
  }

  /**
   * Returns LP steps.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return array
   *   LP steps.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildLpSteps(GroupInterface $group, AccountInterface $account = NULL): array {
    $user = $this->currentUser($account);
    $uid = $user->id();

    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);

    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);
    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = opigno_learning_path_get_all_steps($group->id(), $uid, NULL, $latest_cert_date);
    }
    else {
      // Get guided steps.
      $steps = opigno_learning_path_get_steps($group->id(), $uid, NULL, $latest_cert_date);
    }

    $steps = array_filter($steps, function ($step) use ($user) {
      return LearningPathContentService::filterStep($step, $user);
    });

    $steps = array_map(static function ($step) use ($uid, $latest_cert_date) {
      $step['status'] = opigno_learning_path_get_step_status($step, $uid, TRUE, $latest_cert_date);
      $step['attempted'] = opigno_learning_path_is_attempted($step, $uid);
      $step['progress'] = opigno_learning_path_get_step_progress($step, $uid, FALSE, $latest_cert_date);
      return $step;
    }, $steps);

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'training_steps_' . $group->id(),
      ],
      0 => array_map(function ($step) use ($group, $user, $latest_cert_date) {
        return [
          '#theme' => 'opigno_learning_path_training_step',
          '#step' => $step,
          '#is_module' => $this->isModule($step),
          [$this->trainingStepContentBuild($step, $group, $user, $latest_cert_date)],
        ];
      }, $steps),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function currentUser(AccountInterface $account = NULL) {
    if ($account) {
      return $account;
    }
    return parent::currentUser();
  }

  /**
   * Returns training summary.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return array
   *   Training summary.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildTrainingSummary(GroupInterface $group, AccountInterface $account = NULL): array {
    $gid = $group->id();
    $uid = $this->currentUser($account)->id();
    return $this->progress->getProgressAjaxContainer($gid, $uid, '', 'achievements-page', TRUE);
  }

  /**
   * Returns training array.
   *
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Group.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return array
   *   Training array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildTraining(GroupInterface $group = NULL, AccountInterface $account = NULL): array {
    return [
      '#theme' => 'opigno_learning_path_training',
      '#label' => $group->label(),
      'summary' => $this->buildTrainingSummary($group, $account),
      'details' => $this->buildLpSteps($group, $account),
      'image' => $group->get('field_learning_path_media_image')->view([
        'label' => 'hidden',
        'type' => 'media_thumbnail',
        'settings' => [
          'image_style' => 'catalog_image',
        ],
      ]),
    ];
  }

  /**
   * Loads module panel with a AJAX.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training group.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course group.
   * @param \Drupal\opigno_module\Entity\OpignoModule $opigno_module
   *   Opigno module.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function courseModulePanelAjax(
    GroupInterface $training,
    GroupInterface $course,
    OpignoModule $opigno_module
  ): AjaxResponse {
    $training_id = $training->id();
    $course_id = $course->id();
    $module_id = $opigno_module->id();
    $selector = "#module_panel_${training_id}_${course_id}_${module_id}";
    $content = $this->buildModulePanel($training, $opigno_module, $course);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Loads module panel with a AJAX.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\opigno_module\Entity\OpignoModule $opigno_module
   *   Opigno module.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function trainingModulePanelAjax(GroupInterface $group, OpignoModule $opigno_module): AjaxResponse {
    $training_id = $group->id();
    $module_id = $opigno_module->id();
    $selector = "#module_panel_${training_id}_${module_id}";
    $content = $this->buildModulePanel($group, $opigno_module);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Loads steps for a training with a AJAX.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function trainingStepsAjax(Group $group): AjaxResponse {
    $selector = '#training_steps_' . $group->id();
    $content = $this->buildLpSteps($group);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Checks training progress access.
   */
  public function buildTrainingProgressAccess(AccountInterface $account) {
    return AccessResult::allowedIf($account->id() > 0 && $this->userAccessManager->canAccessUserStatistics($account)
    );
  }

  /**
   * Get the training progress page title.
   *
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   The group entity to get statistics for.
   * @param \Drupal\user\UserInterface|null $account
   *   The user account to get statistics for.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The training progress page title.
   */
  public function buildTrainingProgressTitle(?GroupInterface $group = NULL, ?UserInterface $account = NULL): TranslatableMarkup {
    return $account instanceof UserInterface && (int) $this->currentUser()->id() !== (int) $account->id()
      ? $this->t('My training progress - @user', ['@user' => $account->getDisplayName()])
      : $this->t('My training progress');
  }

  /**
   * Returns training progress page.
   */
  public function buildTrainingProgress($group = NULL, $account = NULL) {
    return array_map(function ($group) use ($account) {
      return $this->buildTraining($group, $account);
    }, [$group]);
  }

  /**
   * Returns training page array.
   *
   * @param int $page
   *   Page id.
   *
   * @return array
   *   Training page array.
   */
  protected function buildPage(int $page = 0): array {
    $per_page = 5;

    $user = $account = $this->currentUser();
    $uid = $user->id();

    $query = $this->database->select('group_content_field_data', 'gc');
    $query->innerJoin(
      'groups_field_data',
      'g',
      'g.id = gc.gid'
    );
    // Opigno Module group content.
    $query->leftJoin(
      'group_content_field_data',
      'gc2',
      'gc2.gid = gc.gid AND gc2.type = \'group_content_type_162f6c7e7c4fa\''
    );
    $query->leftJoin(
      'opigno_group_content',
      'ogc',
      'ogc.entity_id = gc2.entity_id AND ogc.is_mandatory = 1'
    );
    $query->leftJoin(
      'user_module_status',
      'ums',
      'ums.user_id = gc.uid AND ums.module = gc2.entity_id'
    );
    $query->addExpression('max(ums.started)', 'started');
    $query->addExpression('max(ums.finished)', 'finished');
    $gids = $query->fields('gc', ['gid'])
      ->condition('gc.type', 'learning_path-group_membership')
      ->condition('gc.entity_id', $uid)
      ->groupBy('gc.gid')
      ->orderBy('finished', 'DESC')
      ->orderBy('started', 'DESC')
      ->orderBy('gc.gid', 'DESC')
      ->range($page * $per_page, $per_page)
      ->execute()
      ->fetchCol();
    $groups = Group::loadMultiple($gids);

    return array_map(function ($group) use ($account) {
      return $this->buildTraining($group, $account);
    }, $groups);
  }

  /**
   * Get last or best user attempt for Module.
   *
   * @param array $attempts
   *   User module attempts.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatus
   *   $attempt
   */
  protected function getTargetAttempt(array $attempts, OpignoModule $module) {
    $latest = end($attempts);
    if ($module->getKeepResultsOption() === 'newest'
      || ($latest instanceof UserModuleStatusInterface && !$latest->isFinished())
    ) {
      $attempt = $latest;
    }
    else {
      $attempt = opigno_learning_path_best_attempt($attempts);
    }

    return $attempt;
  }

  /**
   * Loads next achievements page with a AJAX.
   *
   * @param int $page
   *   Page id.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function pageAjax(int $page = 0): AjaxResponse {
    $selector = '#achievements-wrapper';

    $content = $this->buildPage($page);

    $response = new AjaxResponse();
    if (!empty($content)) {
      $response->addCommand(new AppendCommand($selector, $content));
    }
    return $response;
  }

  /**
   * Returns index array.
   *
   * @param int $page
   *   Page id.
   *
   * @return array
   *   Index array.
   */
  public function index($page = 0) {
    $content = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'achievements-wrapper',
      ],
      [
        '#theme' => 'opigno_learning_path_message',
        '#markup' => $this->t('Consult your results and download the certificates for the trainings.'),
      ],
      '#attached' => [
        'library' => [
          'opigno_learning_path/achievements',
          'opigno_learning_path/achievements_slick',
        ],
      ],
    ];

    $content[] = $this->buildPage($page);
    return $content;
  }

  /**
   * Prepares a render array of content.
   */
  protected function trainingStepContentBuild($step, $group, $user, $latest_cert_date = NULL): array {
    $build = [];
    switch ($step['typology']) {
      case 'Module':
        return $this->trainingStepModuleBuild($step, $group, $user, $latest_cert_date);

      case 'Course':
        return $this->trainingStepCourseBuild($step, $group, $user, $latest_cert_date);

      case 'ILT':
        return $this->trainingStepIltBuild($step, $group, $user, $latest_cert_date);

      case 'Meeting':
        return $this->trainingStepMeetingBuild($step, $group, $user, $latest_cert_date);
    }
    return $build;
  }

  /**
   * If step is module prepares a render array of content.
   */
  protected function trainingStepModuleBuild($step, $group, $user, $latest_cert_date = NULL): array {
    $time_spent = $this->getTimeSpentByStep($step);
    $completed = $this->getComplitedByStep($step);
    $time_spent = $time_spent ? $this->dateFormatter->formatInterval($time_spent) : 0;
    $completed = $completed ? $this->dateFormatter->format($completed, 'custom', 'm/d/Y') : '';
    $completed = $completed ?: '';
    [
      $approved,
      $approved_percent,
    ] = $this->getApprovedModuleByStep($step, $user, $latest_cert_date, $group);
    $badges = $this->getModulesStatusBadges($step, $group, $user->id());

    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = OpignoModule::load($step['id']);
    return [
      '#theme' => 'opigno_learning_path_training_module',
      '#status' => $this->mapStatusToTemplateClasses($step['status']),
      '#group_id' => $group->id(),
      '#step' => $step,
      '#time_spent' => $time_spent,
      '#completed' => $completed,
      '#badges' => $badges,
      '#approved' => [
        'value' => $approved,
        'percent' => $approved_percent,
      ],
      '#activities' => $this->buildModulePanel($group, $module, NULL, $user),
    ];
  }

  /**
   * If step is course prepares a render array of content.
   */
  protected function trainingStepCourseBuild($step, $group, $user, $latest_cert_date = NULL): array {
    $time_spent = $this->getTimeSpentByStep($step);
    $completed = $this->getComplitedByStep($step);
    $time_spent = $time_spent ? $this->dateFormatter->formatInterval($time_spent) : 0;
    $completed = $completed ? $this->dateFormatter->format($completed, 'custom', 'm/d/Y') : '';
    $badges = $this->getModulesStatusBadges($step, $group, $user->id());
    [
      $passed,
      $passed_percent,
      $score_percent,
    ] = $this->getStatusPercentCourseByStep($step, $latest_cert_date, $group, $user);
    return [
      '#type' => 'container',
      '#attributes' => [],
      [
        '#theme' => 'opigno_learning_path_training_course',
        '#passed' => [
          'value' => $passed,
          'percent' => $passed_percent,
        ],
        '#score' => $score_percent,
        '#step' => $step,
        '#completed' => $completed,
        '#badges' => $badges,
        '#time_spent' => $time_spent,
      ],
      [$this->buildCourseSteps($group, Group::load($step['id']), $user)],
    ];
  }

  /**
   * Prepare render array for ILT step.
   */
  private function trainingStepIltBuild($step, $group, $user, $latest_cert_date) {

    // If the user is not a member of the meeting.
    $ilt = $this->entityTypeManager
      ->getStorage('opigno_ilt')
      ->load($step['id']);

    if (!($ilt instanceof ILT)) {
      return [];
    }
    if (!($valid_unix = strtotime($ilt->getStartDate()))) {
      return [];
    }
    $date = $valid_unix ? $this->dateFormatter->format($valid_unix, 'custom', 'm/d/Y') : '';
    return [
      '#theme' => 'opigno_learning_path_training_ilt',
      '#date' => $date,
      '#status' => $this->mapStatusToTemplateClasses($step['status']),
      '#attended' => $step["attempted"] ? $this->t('Yes') : $this->t('No'),
      '#step' => $step,
      '#place' => $ilt->getPlace(),
      '#approved' => [
        'value' => $step["presence"],
        'percent' => $step["progress"],
      ],
    ];
  }

  /**
   * Prepare render array for Meeting step.
   */
  private function trainingStepMeetingBuild($step, $group, $user, $latest_cert_date) {
    // If the user is not a member of the meeting.
    $meeting = $this->entityTypeManager
      ->getStorage('opigno_moxtra_meeting')
      ->load($step['id']);
    if (!($meeting instanceof Meeting)) {
      return [];
    }
    if (!($valid_unix = strtotime($meeting->getStartDate()))) {
      return [];
    }
    $date = $valid_unix ? $this->dateFormatter->format($valid_unix, 'custom', 'm/d/Y') : '';
    return [
      '#theme' => 'opigno_learning_path_training_meeting',
      '#date' => $date,
      '#status' => $this->mapStatusToTemplateClasses($step['status']),
      '#attended' => $step["attempted"] ? $this->t('Yes') : $this->t('No'),
      '#step' => $step,
      '#place' => $meeting->toLink()->toRenderable(),
      '#approved' => [
        'value' => $step["presence"],
        'percent' => $step["progress"],
      ],
    ];
  }

  /**
   * Checks if step is module.
   */
  protected function isModule($step): bool {
    return $step['typology'] == 'Module';
  }

  /**
   * Time spent if module is attempted.
   */
  protected function getTimeSpentByStep($step) {
    return (isset($step['attempted']) && $step['time spent'] > 0) ? $step['time spent'] : FALSE;
  }

  /**
   * Completed if module is attempted.
   */
  protected function getComplitedByStep($step) {
    return (isset($step['attempted']) && $step['completed on'] > 0) ? $step['completed on'] : FALSE;
  }

}
