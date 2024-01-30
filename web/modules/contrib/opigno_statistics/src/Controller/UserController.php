<?php

namespace Drupal\opigno_statistics\Controller;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\opigno_learning_path\Services\UserAccessManager;
use Drupal\opigno_statistics\Services\UserStatisticsManager;
use Drupal\opigno_statistics\StatisticsPageTrait;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Statistics user controller.
 */
class UserController extends ControllerBase {

  use StatisticsPageTrait;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The user statistics manager service.
   *
   * @var \Drupal\opigno_statistics\Services\UserStatisticsManager
   */
  protected $statsManager;

  /**
   * The Opigno user access manager.
   *
   * @var \Drupal\opigno_learning_path\Services\UserAccessManager
   */
  protected $userAccessManager;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Opigno user connections manager service.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected $connectionsManager;

  /**
   * UserController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\opigno_statistics\Services\UserStatisticsManager $stats_manager
   *   Opigno user statistics manager.
   * @param \Drupal\opigno_learning_path\Services\UserAccessManager $user_access_manager
   *   User access manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\opigno_social\Services\UserConnectionManager $connection_manager
   *   The user connection manager.
   */
  public function __construct(
    Connection $database,
    UserStatisticsManager $stats_manager,
    UserAccessManager $user_access_manager,
    DateFormatterInterface $date_formatter,
    AccountInterface $account,
    UserConnectionManager $connection_manager
  ) {
    $this->database = $database;
    $this->statsManager = $stats_manager;
    $this->userAccessManager = $user_access_manager;
    $this->dateFormatter = $date_formatter;
    $this->account = $account;
    $this->connectionsManager = $connection_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('opigno_statistics.user_stats_manager'),
      $container->get('opigno_learning_path.user_access_manager'),
      $container->get('date.formatter'),
      $container->get('current_user'),
      $container->get('opigno_user_connection.manager')
    );
  }

  /**
   * Builds render array for a user info block.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param bool $is_private
   *   Whether the user's profile private or not.
   * @param bool $can_access_private
   *   If the current user can access the private profile info or not.
   * @param bool $is_in_network
   *   If the viewed user in the connections list of thr current one.
   *
   * @return array
   *   Data to display the user info.
   */
  public function buildUserInfo(UserInterface $user, bool $is_private, bool $can_access_private, bool $is_in_network) {
    $uid = (int) $user->id();
    $build = [
      'picture' => UserStatisticsManager::getUserPicture($user, 'user_profile'),
      'name' => $user->getDisplayName(),
    ];

    // Only first and last name should be accessible for the private profile.
    // Users with specific permissions can see the full info.
    if ($is_private && !$can_access_private) {
      return $build;
    }

    // Email should be accessible for not-private profiles.
    $build['email'] = $user->getEmail();
    if (!$is_private && !$is_in_network && !$can_access_private) {
      return $build;
    }

    $created_timestamp = $user->getCreatedTime();
    $accessed_timestamp = $user->getLastAccessedTime();

    // Achievements.
    $trainings = $this->statsManager->getUserTrainingsNumberByStatus(0, $uid);
    $certificates = $this->statsManager->getUserCertificates(0, $uid);
    $badges = $this->statsManager->getUserBadges($uid);
    $skills = count($this->statsManager->getSkillsAcquired($uid));
    $achievements_url = Url::fromRoute('opigno_statistics.user_achievements_page', ['user' => $uid]);

    return $build + [
      'data' => [
        'edit_link' => $user->access('update') ? Url::fromRoute('entity.user.edit_form', ['user' => $uid])->toString() : '',
        'role' => $this->statsManager->getUserRole($user),
        'join' => $this->dateFormatter->format($created_timestamp, 'year_month_day'),
        'access' => $this->dateFormatter->format($accessed_timestamp, 'year_month_day'),
        'member_for' => $this->dateFormatter->formatTimeDiffSince($created_timestamp),
        'connections' => $this->connectionsManager->renderUserConnectionsBlock($uid),
        'achievements' => [
          'trainings' => [
            '#theme' => 'opigno_statistics_user_achievement',
            '#count' => $trainings,
            '#text' => $this->formatPlural($trainings, 'Training completed', 'Trainings completed'),
            '#img' => $this->getAchievementImagePath('trainings.svg'),
            '#url' => $this->buildAchievementsPageUrl($achievements_url),
          ],
          'certificates' => [
            '#theme' => 'opigno_statistics_user_achievement',
            '#count' => $certificates,
            '#text' => $this->formatPlural($certificates, 'Certificate received', 'Certificates received'),
            '#img' => $this->getAchievementImagePath('certificate.svg'),
            '#url' => $this->buildAchievementsPageUrl($achievements_url, UserAchievements::CERTIFICATES_TAB),
          ],
          'badges' => [
            '#theme' => 'opigno_statistics_user_achievement',
            '#count' => $badges,
            '#text' => $this->formatPlural($badges, 'Badge earned', 'Badges earned'),
            '#img' => $this->getAchievementImagePath('badges.svg'),
            '#url' => $this->buildAchievementsPageUrl($achievements_url, UserAchievements::BADGES_TAB),
          ],
          'skills' => [
            '#theme' => 'opigno_statistics_user_achievement',
            '#count' => $skills,
            '#text' => $this->formatPlural($skills, 'Skill acquired', 'Skills acquired'),
            '#img' => $this->getAchievementImagePath('skills.svg'),
            '#url' => $this->buildAchievementsPageUrl($achievements_url, UserAchievements::SKILLS_TAB),
          ],
        ],
      ],
    ];
  }

  /**
   * Get the achievement image path.
   *
   * @param string $image
   *   The image name.
   *
   * @return string
   *   Path to the image in theme.
   */
  private function getAchievementImagePath(string $image): string {
    $path = \Drupal::service('extension.list.theme')->getPath('aristotle') . "/src/images/design/$image";

    return file_exists($path) ? \Drupal::service('file_url_generator')->transformRelative(base_path() . $path) : '';
  }

  /**
   * Build the url to the achievement page with the anchor to the needed tab.
   *
   * @param \Drupal\Core\Url $url
   *   The achievement page url object.
   * @param string $tab
   *   The machine name of the tab that should be opened.
   *
   * @return string
   *   The url to the achievement page with the anchor to the needed tab.
   */
  private function buildAchievementsPageUrl(Url $url, string $tab = ''): string {
    $result = '';
    if ($url->access()) {
      $url = $tab ? $url->setOptions(['query' => ['tab' => $tab]]) : $url;
      $url = $url->toString();
      $result = $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url;
    }

    return $result;
  }

  /**
   * Returns max score that user can have in this module & activity.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   * @param \Drupal\opigno_module\Entity\OpignoActivity $activity
   *   Activity.
   *
   * @return int
   *   Score.
   */
  protected function getActivityMaxScore(OpignoModule $module, OpignoActivity $activity) {
    $moduleHandler = \Drupal::service('module_handler');

    if ($moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive()) {
      $query = $this->database->select('opigno_module_relationship', 'omr')
        ->fields('omr', ['max_score'])
        ->condition('omr.child_id', $activity->id())
        ->condition('omr.child_vid', $activity->getRevisionId())
        ->condition('omr.activity_status', 1);
      $results = $query->execute()->fetchAll();
    }
    else {
      $query = $this->database->select('opigno_module_relationship', 'omr')
        ->fields('omr', ['max_score'])
        ->condition('omr.parent_id', $module->id())
        ->condition('omr.parent_vid', $module->getRevisionId())
        ->condition('omr.child_id', $activity->id())
        ->condition('omr.child_vid', $activity->getRevisionId())
        ->condition('omr.activity_status', 1);
      $results = $query->execute()->fetchAll();
    }

    if (empty($results)) {
      return 0;
    }

    $result = reset($results);
    return $result->max_score;
  }

  /**
   * Build render array for a user module details.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training.
   * @param null|\Drupal\group\Entity\GroupInterface $course
   *   Course.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildModuleDetails(
    UserInterface $user,
    GroupInterface $training,
    $course,
    OpignoModule $module
  ) {
    // Get user training expiration flag.
    $expired = LPStatus::isCertificateExpired($training, $user->id());

    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($training, $user->id());

    $moduleHandler = \Drupal::service('module_handler');
    $parent = isset($course) ? $course : $training;
    $step = opigno_learning_path_get_module_step($parent->id(), $user->id(), $module, $latest_cert_date);

    if ($expired) {
      $completed_on = '';
    }
    else {
      $completed_on = $step['completed on'];
      $completed_on = $completed_on > 0
        ? $this->dateFormatter->format($completed_on, 'custom', 'F d, Y')
        : '';
    }

    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = OpignoModule::load($step['id']);
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $attempts */
    $attempts = !$expired ? $module->getModuleAttempts($user, NULL, $latest_cert_date, FALSE, $training->id()) : [];

    if ($moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive()
        && $module->getModuleSkillsGlobal() && !empty($attempts)) {
      $activities_from_module = $module->getModuleActivities();
      $activity_ids = array_keys($activities_from_module);
      $attempt = $this->getTargetAttempt($attempts, $module);

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
      $score_percent = 0;
      $score = 0;
    }

    $activities = array_map(function ($activity) use ($user, $module, $attempt) {
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
        $state_class = 'step_state_pending';
      }
      else {
        $state_class = isset($answer)
          ? 'step_state_passed' : ($max_score == 0 ? 'step_state_pending' : 'step_state_failed');
      }

      return [
        ['data' => $activity->getName()],
        [
          'data' => [
            '#markup' => $score . '/' . $max_score,
          ],
        ],
        [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => [$state_class],
            ],
            '#value' => '',
          ],
        ],
      ];
    }, $activities);

    $activities = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['module_panel_activities_overview'],
      ],
      '#rows' => $activities,
    ];

    $training_id = $training->id();
    $module_id = $module->id();
    $user_id = $user->id();

    if (isset($course)) {
      $course_id = $course->id();
      $id = "module_panel_${user_id}_${training_id}_${course_id}_${module_id}";
    }
    else {
      $id = "module_panel_${user_id}_${training_id}_${module_id}";
    }

    if (isset($attempt)) {
      $details = Link::createFromRoute(
        $this->t('Details'),
        'opigno_module.module_result', [
          'opigno_module' => $module->id(),
          'user_module_status' => $attempt->id(),
        ],
        ['query' => ['skip-links' => TRUE]]
      )->toRenderable();
      $details['#attributes']['target'] = '_blank';
    }
    else {
      $details = [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => $id,
        'class' => ['module_panel'],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['module_panel_header'],
        ],
        [
          '#markup' => '<a href="#" class="module_panel_close">&times;</a>',
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => [
            'class' => ['module_panel_title'],
          ],
          '#value' => $step['name'] . ' ' . (!empty($completed_on) ? t('completed') : ''),
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'hr',
        '#value' => '',
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['module_panel_content'],
        ],
        !empty($completed_on) ? [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => t('@name completed on @date', [
            '@name' => $step['name'],
            '@date' => $completed_on,
          ]),
        ] : [],
        !($max_score == 0) ? [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => t('User got @score of @max_score possible points.', [
            '@score' => $score,
            '@max_score' => $max_score,
          ]),
        ] : [],
        !($max_score == 0) ? [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => t('Total score @percent%', [
            '@percent' => $score_percent,
          ]),
        ] : [],
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => [
            'class' => ['module_panel_overview_title'],
          ],
          '#value' => t('Activities Overview'),
        ],
        $activities,
        $details,
      ],
    ];
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
    if ($module->getKeepResultsOption() == 'newest') {
      $attempt = end($attempts);
    }
    else {
      $attempts = array_filter($attempts, function ($attempt) use ($module) {
        return $attempt->module->target_id == $module->id();
      });
      $attempt = opigno_learning_path_best_attempt($attempts);
    }

    return $attempt;
  }

  /**
   * Builds render array for a user course details.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildCourseDetails(
    UserInterface $user,
    GroupInterface $training,
    GroupInterface $course
  ) {
    // Load group steps.
    $steps = opigno_learning_path_get_steps($course->id(), $user->id());

    $query = $this->database->select(
      'opigno_learning_path_step_achievements',
      'sa'
    );
    $query->leftJoin(
      'opigno_learning_path_step_achievements',
      'sa2',
      'sa2.parent_id = sa.id'
    );
    $query = $query
      ->fields('sa2', ['entity_id', 'typology', 'name', 'score', 'status'])
      ->condition('sa.uid', $user->id())
      ->condition('sa.gid', $training->id())
      ->condition('sa.entity_id', $course->id())
      ->condition('sa.parent_id', 0);

    $modules = $query->execute()->fetchAll();
    $rows = array_map(function ($step) use ($modules, $training, $course, $user) {
      $module = NULL;
      foreach ($modules as $mod) {
        if ($mod->entity_id === $step['id']) {
          $module = $mod;
          break;
        }
      }

      $id = isset($module) ? $module->entity_id : $step['id'];
      $name = isset($module) ? $module->name : $step['name'];
      $score = isset($module) ? $module->score : 0;
      $status = isset($module) ? $module->status : 'pending';
      $typology = strtolower(isset($module) ? $module->typology : $step['typology']);

      $score = isset($score) ? $score : 0;
      $score = ['data' => $this->buildScore($score)];

      $status = isset($status) ? $status : 'pending';
      $status = ['data' => $this->buildStatus($status)];

      switch ($typology) {
        case 'module':
          $training_gid = $training->id();
          $course_gid = $course->id();
          $module_id = $id;
          $user_id = $user->id();
          $details = Link::createFromRoute('', 'opigno_statistics.user.course_module_details', [
            'user' => $user->id(),
            'training' => $training->id(),
            'course' => $course->id(),
            'module' => $id,
          ])->toRenderable();
          $details['#attributes']['class'][] = 'details';
          $details['#attributes']['class'][] = 'course-module-details-open';
          $details['#attributes']['data-user'] = $user_id;
          $details['#attributes']['data-training'] = $training_gid;
          $details['#attributes']['data-course'] = $course_gid;
          $details['#attributes']['data-id'] = $module_id;
          $details = [
            'data' => [
              $details,
              [
                '#type' => 'container',
                '#attributes' => [
                  'class' => ['module-panel-wrapper'],
                ],
                [
                  '#type' => 'html_tag',
                  '#tag' => 'span',
                  '#attributes' => [
                    'id' => "module_panel_${user_id}_${training_gid}_${course_gid}_${module_id}",
                  ],
                ],
              ],
            ],
          ];
          break;

        default:
          $details = '';
          break;
      }

      return [
        $name,
        $score,
        $status,
        $details,
      ];
    }, $steps);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['course-modules'],
      ],
      [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['statistics-table', 'course-modules-list'],
        ],
        '#header' => [],
        '#rows' => $rows,
      ],
    ];
  }

  /**
   * Builds render array for a user training details.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Training.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildTrainingDetails(UserInterface $user, GroupInterface $group) {
    $gid = $group->id();
    $uid = $user->id();

    // Get user training expiration flag.
    $expired = LPStatus::isCertificateExpired($group, $uid);
    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);

    // Load group steps.
    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);
    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = opigno_learning_path_get_all_steps($gid, $uid, NULL, $latest_cert_date);
    }
    else {
      // Get guided steps.
      $steps = opigno_learning_path_get_steps($gid, $uid, NULL, $latest_cert_date);
    }
    $steps_count = count($steps);

    if ($expired) {
      $passed_modules_count = 0;
      $training_data = [
        'progress' => 0,
        'score' => 0,
        'time' => 0,
      ];
      $modules = [];
    }
    else {
      $query = $this->database
        ->select('opigno_learning_path_achievements', 'a')
        ->fields('a', ['score', 'progress', 'time', 'completed'])
        ->condition('a.gid', $gid)
        ->condition('a.uid', $uid);
      $training_data = $query->execute()->fetchAssoc();

      $query = $this->database
        ->select('opigno_learning_path_step_achievements', 'sa')
        ->fields('sa', [
          'entity_id',
          'typology',
          'name',
          'score',
          'status',
          'time',
          'completed',
        ])
        ->condition('sa.gid', $gid)
        ->condition('sa.uid', $uid)
        ->condition('sa.parent_id', 0);
      $modules = $query->execute()->fetchAll();
      $passed_modules = array_filter($modules, function ($module) {
        return $module->status === 'passed';
      });
      $passed_modules_count = count($passed_modules);
    }

    $content = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['training-details-content'],
      ],
    ];
    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['module-passed'],
      ],
      'module_passed' => $this->buildValueWithIndicator(
        $this->t('Module Passed'),
        ($steps_count ? $passed_modules_count / $steps_count : 0),
        $this->t('@passed/@total', [
          '@passed' => $passed_modules_count,
          '@total' => $steps_count,
        ])
      ),
    ];
    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['completion'],
      ],
      'completion' => $this->buildValueWithIndicator(
        $this->t('Completion'),
        $training_data['progress'] / 100
      ),
    ];
    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['score'],
      ],
      'completion' => $this->buildValueWithIndicator(
        $this->t('Score'),
        $training_data['score'] / 100
      ),
    ];

    $time = isset($training_data['time']) && $training_data['time'] > 0
      ? $this->dateFormatter->formatInterval($training_data['time']) : '-';

    if (isset($training_data['completed'])) {
      $datetime = DrupalDateTime::createFromFormat(
        DrupalDateTime::FORMAT,
        $training_data['completed']
      );
      $timestamp = $datetime->getTimestamp();
      $completed_on = $this->dateFormatter->format($timestamp, 'custom', 'F d Y');
    }
    else {
      $completed_on = '-';
    }

    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['right-block'],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['time'],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['value-wrapper'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['label'],
            ],
            '#value' => $this->t('Time spent'),
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['value'],
            ],
            '#value' => $time,
          ],
        ],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['completed'],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['value-wrapper'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['label'],
            ],
            '#value' => $this->t('Completed on'),
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['value'],
            ],
            '#value' => $completed_on,
          ],
        ],
      ],
    ];

    $rows = array_map(function ($step) use ($modules, $uid, $gid) {
      $module = NULL;
      foreach ($modules as $mod) {
        if ($mod->entity_id == $step['id'] && $mod->typology == $step["typology"]) {
          $module = $mod;
          break;
        }
      }

      $id = isset($module) ? $module->entity_id : $step['id'];
      $name = isset($module) ? $module->name : $step['name'];
      $score = $step['current attempt score'];
      $status = isset($module) ? $module->status : 'pending';
      $typology = strtolower(isset($module) ? $module->typology : $step['typology']);

      $score = isset($score) ? $score : 0;
      $score = ['data' => $this->buildScore($score)];

      $status = isset($status) ? $status : 'pending';
      $status = ['data' => $this->buildStatus($status)];

      switch ($typology) {
        case 'course':
          $details = Link::createFromRoute('', 'opigno_statistics.user.course_details', [
            'user' => $uid,
            'training' => $gid,
            'course' => $id,
          ])->toRenderable();
          $details['#attributes']['class'][] = 'details';
          $details['#attributes']['class'][] = 'course-details-open';
          $details['#attributes']['data-user'] = $uid;
          $details['#attributes']['data-training'] = $gid;
          $details['#attributes']['data-id'] = $id;
          $details = ['data' => $details];
          break;

        case 'module':
          $module_id = $id;
          $details = Link::createFromRoute('', 'opigno_statistics.user.training_module_details', [
            'user' => $uid,
            'training' => $gid,
            'module' => $module_id,
          ])->toRenderable();
          $details['#attributes']['class'][] = 'details';
          $details['#attributes']['class'][] = 'training-module-details-open';
          $details['#attributes']['data-user'] = $uid;
          $details['#attributes']['data-training'] = $gid;
          $details['#attributes']['data-id'] = $module_id;
          $details = [
            'data' => [
              $details,
              [
                '#type' => 'container',
                '#attributes' => [
                  'class' => ['module-panel-wrapper'],
                ],
                [
                  '#type' => 'html_tag',
                  '#tag' => 'span',
                  '#attributes' => [
                    'id' => "module_panel_${uid}_${gid}_${module_id}",
                  ],
                ],
              ],
            ],
          ];
          break;

        default:
          $details = '';
          break;
      }

      $is_course = $typology === 'course';
      return [
        'class' => $is_course ? 'course' : 'module',
        'data-training' => $gid,
        'data-id' => $id,
        'data-user' => $uid,
        'data' => [
          $name,
          $score,
          $status,
          $details,
        ],
      ];
    }, $steps);

    $content[] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
    ];

    $current_route = \Drupal::routeMatch()->getRouteName();
    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['training-modules'],
      ],
      [
        '#type' => 'table',
        '#attributes' => [
          'class' => [
            (!empty($current_route) && $current_route == 'opigno_statistics.user.training_details') ? 'trainings-list' : '',
            'statistics-table',
            'training-modules-list',
            'mb-0',
          ],
        ],
        '#header' => [
          $this->t('Course / Module'),
          $this->t('Results'),
          $this->t('State'),
          '',
        ],
        '#rows' => $rows,
      ],
    ];

    return $content;
  }

  /**
   * Loads module panel with a AJAX.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training.
   * @param null|\Drupal\group\Entity\GroupInterface $course
   *   Course.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function ajaxLoadCourseModuleDetails(
    UserInterface $user,
    GroupInterface $training,
    GroupInterface $course,
    OpignoModule $module
  ) {
    $training_id = $training->id();
    $course_id = $course->id();
    $module_id = $module->id();
    $user_id = $user->id();
    $selector = "#module_panel_${user_id}_${training_id}_${course_id}_${module_id}";
    $content = $this->buildModuleDetails($user, $training, $course, $module);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Loads module panel with a AJAX.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function ajaxLoadTrainingModuleDetails(
    UserInterface $user,
    GroupInterface $training,
    OpignoModule $module
  ) {
    $training_id = $training->id();
    $module_id = $module->id();
    $user_id = $user->id();
    $selector = "#module_panel_${user_id}_${training_id}_${module_id}";
    $content = $this->buildModuleDetails($user, $training, NULL, $module);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Loads a user course details with the AJAX.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function ajaxLoadCourseDetails(
    UserInterface $user,
    GroupInterface $training,
    GroupInterface $course
  ) {
    $training_gid = $training->id();
    $course_gid = $course->id();
    $user_id = $user->id();
    $selector = ".training-modules-list tr.course[data-user=\"$user_id\"][data-training=\"$training_gid\"][data-id=\"$course_gid\"]";
    $content = [
      [
        '#type' => 'html_tag',
        '#tag' => 'tr',
        '#attributes' => [
          'data-training' => $training_gid,
          'data-id' => $course_gid,
          'class' => ['course-active'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'td',
          '#attributes' => [
            'colspan' => 3,
          ],
          '#value' => $course->label(),
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'td',
          'close' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'data-training' => $training_gid,
              'data-id' => $course_gid,
              'class' => ['course-close'],
            ],
          ],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'tr',
        '#attributes' => [
          'data-training' => $training_gid,
          'data-id' => $course_gid,
          'class' => ['course-details'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'td',
          '#attributes' => [
            'colspan' => 4,
          ],
          'details' => $this->buildCourseDetails($user, $training, $course),
        ],
      ],
    ];
    $content['#attached']['library'][] = 'opigno_statistics/user';
    $response = new AjaxResponse();
    $response->addCommand(new AfterCommand($selector, $content));
    return $response;
  }

  /**
   * Loads a user training details with the AJAX.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Training.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function ajaxLoadTrainingDetails(
    UserInterface $user,
    GroupInterface $group
  ) {
    $gid = $group->id();
    $uid = $user->id();
    $selector = ".trainings-list tr.training[data-training=\"$gid\"][data-user=\"$uid\"]";
    $content = [
      [
        '#type' => 'html_tag',
        '#tag' => 'tr',
        '#attributes' => [
          'data-training' => $gid,
          'data-user' => $uid,
          'class' => ['training-active'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'td',
          '#attributes' => [
            'colspan' => 4,
          ],
          '#value' => $group->label(),
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'td',
          'close' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'data-training' => $gid,
              'data-user' => $uid,
              'class' => ['training-close'],
            ],
          ],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'tr',
        '#attributes' => [
          'data-training' => $gid,
          'data-user' => $uid,
          'class' => ['training-details'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'td',
          '#attributes' => [
            'colspan' => 5,
          ],
          'details' => $this->buildTrainingDetails($user, $group),
        ],
      ],
    ];
    $content['#attached']['library'][] = 'opigno_statistics/user';
    $response = new AjaxResponse();
    $response->addCommand(new AfterCommand($selector, $content));
    return $response;
  }

  /**
   * Builds render array for a user trainings list.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   *
   * @return array
   *   Render array.
   */
  public function buildTrainingsList(UserInterface $user): array {
    $uid = (int) $user->id();
    $query = $this->database
      ->select('opigno_learning_path_achievements', 'a')
      ->fields('a', ['gid', 'name', 'progress', 'status'])
      ->condition('a.uid', $uid)
      ->groupBy('a.gid')
      ->groupBy('a.name')
      ->groupBy('a.progress')
      ->groupBy('a.status')
      ->orderBy('a.name');

    $rows = $query->execute()->fetchAll();
    $table_rows = [];

    // Build table rows.
    foreach ($rows as $row) {
      $gid = $row->gid;
      $status = $row->status ?? 'pending';
      $progress = $row->progress ?? 0;

      // Generate the details link.
      $options = [
        'attributes' => [
          'class' => ['btn', 'btn-rounded'],
          'data-user' => $uid,
          'data-training' => $gid,
        ],
      ];
      $params = ['user' => $uid, 'group' => $gid];
      $details = Link::createFromRoute($this->t('Details'), 'opigno_statistics.user.training_details', $params, $options)->toRenderable();

      $table_rows[] = [
        'class' => 'training',
        'data-training' => $gid,
        'data-user' => $uid,
        'data' => [
          ['data' => $row->name ?? '', 'class' => 'name'],
          ['data' => $progress . '%', 'class' => 'progress'],
          ['data' => $this->buildStatus($status), 'class' => 'status'],
          ['data' => $details, 'class' => 'details'],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table'],
      ],
      '#header' => [
        ['data' => $this->t('Training'), 'class' => 'name'],
        ['data' => $this->t('Progress'), 'class' => 'progress'],
        ['data' => $this->t('Passed'), 'class' => 'status'],
        ['data' => $this->t('Details'), 'class' => 'hidden'],
      ],
      '#rows' => $table_rows,
    ];
  }

  /**
   * Builds render array for a user course statistics page.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function courseModule(
    UserInterface $user,
    GroupInterface $training,
    GroupInterface $course,
    OpignoModule $module
  ) {
    $content = [];
    $content[] = $this->buildModuleDetails($user, $training, $course, $module);
    $content['#attached']['library'][] = 'opigno_statistics/user';
    return $content;
  }

  /**
   * Builds render array for a user course statistics page.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function trainingModule(
    UserInterface $user,
    GroupInterface $training,
    OpignoModule $module
  ) {
    $content = [];
    $content[] = $this->buildModuleDetails($user, $training, NULL, $module);
    $content['#attached']['library'][] = 'opigno_statistics/user';
    return $content;
  }

  /**
   * Builds render array for a user course statistics page.
   */
  public function course(
    UserInterface $user,
    GroupInterface $training,
    GroupInterface $course
  ) {
    $content = [];
    $content[] = $this->buildCourseDetails($user, $training, $course);
    $content['#attached']['library'][] = 'opigno_statistics/user';
    return $content;
  }

  /**
   * Builds render array for a user training statistics page.
   */
  public function training(UserInterface $user, GroupInterface $group) {
    $content = [];
    $content[] = $this->buildTrainingDetails($user, $group);
    $content['#attached']['library'][] = 'opigno_statistics/user';
    return $content;
  }

  /**
   * Builds render array for a user statistics index page.
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   *
   * @return array
   *   Render array.
   */
  public function index(UserInterface $user): array {
    $uid = (int) $user->id();
    $days = 7;
    // Check if the profile is private and if the current user has an access to
    // view it.
    $is_private = $user->hasField('field_private_profile') && (bool) $user->get('field_private_profile')->getString();
    $can_access_private = $this->userAccessManager->canViewPrivateProfile($user);

    // Check if the user is in network.
    $network = $this->connectionsManager->getUserNetwork();
    $is_in_network = in_array($uid, $network);

    // Charts and trends should be accessible only for friends of not-private
    // profiles or to users with the special permissions.
    $stats = [];
    $attached = [];
    if ((!$is_private && $is_in_network) || $can_access_private) {
      $stats = [
        'trends' => $this->statsManager->renderUserStatistics($days, $uid),
        'charts' => $this->statsManager->renderUserTrainingsCharts($uid),
      ];
      $attached = ['library' => ['opigno_statistics/opigno_charts']];
    }

    // Trainings list should be accessible only to the user and to those who
    // have the specific permissions.
    $trainings_visible = $this->userAccessManager->canAccessUserStatistics($user);

    return [
      '#theme' => 'opigno_user_statistics_page',
      '#user_info' => $this->buildUserInfo($user, $is_private, $can_access_private, $is_in_network),
      '#trainings' => $trainings_visible ? $this->statsManager->buildTrainingsList($uid) : [],
      '#stats' => $stats,
      '#attached' => $attached,
    ];
  }

  /**
   * Get user statistics block.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function getUserStatsBlock(Request $request): AjaxResponse {
    $days = (int) $request->get('days', 0);
    $uid = (int) $request->get('uid', 0);
    $stats = $this->statsManager->renderUserStatistics($days, $uid);

    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand('body', 'removeClass', ['charts-rendered']));
    $response->addCommand(new ReplaceCommand('.opigno-user-statistics', $stats));

    return $response;
  }

}
