<?php

namespace Drupal\opigno_statistics\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\opigno_learning_path\LearningPathAccess;
use Drupal\opigno_statistics\StatisticsPageTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the statistics dashboard.
 */
class DashboardForm extends FormBase {

  use StatisticsPageTrait;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $termStorage = NULL;

  /**
   * DashboardForm constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connecion service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    Connection $database,
    TimeInterface $time,
    DateFormatterInterface $date_formatter,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->database = $database;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;

    try {
      $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_dashboard_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_statistics_dashboard_form';
  }

  /**
   * Builds active users per day graph.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $datetime
   *   Date.
   * @param array $lp_ids
   *   LP IDs.
   *
   * @return array
   *   Render array.
   */
  protected function buildUsersPerDay(DrupalDateTime $datetime, array $lp_ids = []): array {
    $max_time = $datetime->format(DrupalDateTime::FORMAT);
    // Last month.
    $min_datetime = $datetime->sub(new \DateInterval('P1M'));
    $min_time = $min_datetime->format(DrupalDateTime::FORMAT);

    $query = $this->database
      ->select('opigno_statistics_user_login', 'u');
    $query->addExpression('DAY(u.date)', 'hour');
    $query->addExpression('COUNT(DISTINCT u.uid)', 'count');

    if ($lp_ids) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.gid', $lp_ids, 'IN');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
    }

    $query->condition('u.uid', 0, '<>');

    $data = $query
      ->condition('u.date', [$min_time, $max_time], 'BETWEEN')
      ->groupBy('hour')
      ->execute()
      ->fetchAllAssoc('hour');

    // Get the number of days in the month.
    $days = $min_datetime->format('t');

    for ($i = 1; $i <= $days; ++$i) {
      if (isset($data[$i])) {
        $data[$i] = $data[$i]->count;
      }
      else {
        $data[$i] = 0;
      }
    }

    // Sort the array.
    ksort($data);

    // Get color palette.
    $theme = \Drupal::theme()->getActiveTheme()->getName();
    $theme_decorator = \Drupal::hasService('color.theme_decorator');
    if ($theme_decorator) {
      $color_palette = \Drupal::service('color.theme_decorator')
        ->getPalette($theme);
    }
    else {
      $color_palette = color_get_palette($theme);
    }

    $color = $color_palette['desktop_link'] ?? '#4AD3B0';

    // Prepare the data for the Line chart to display active users.
    return [
      'id' => '#active-users-chart',
      'type' => 'line',
      'labels' => array_keys($data),
      'datasets' => [
        [
          'data' => array_values($data),
          'fill' => FALSE,
          'borderColor' => $color,
          'tension' => 0,
          'pointRadius' => 0,
        ],
      ],
      'options' => [
        'maintainAspectRatio' => FALSE,
        'scales' => [
          'xAxes' => [
            'ticks' => [
              'font' => [
                'size' => 8,
              ],
              'color' => '#A3A3A3',
              'max' => count($data),
            ],
          ],
          'yAxes' => [
            'ticks' => [
              'beginAtZero' => TRUE,
              'font' => [
                'size' => 8,
              ],
              'color' => '#A3A3A3',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Builds trainings progress.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $datetime
   *   Date.
   * @param array $lp_ids
   *   LP IDs.
   *
   * @return array
   *   Render array.
   *
   * @throws \Exception
   */
  protected function buildTrainingsProgress(DrupalDateTime $datetime, array $lp_ids = []): array {
    $progress = $completion = 0;
    $time_str = $datetime->format(DrupalDateTime::FORMAT);

    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('SUM(a.progress) / COUNT(a.progress) / 100', 'progress');
    $query->addExpression('COUNT(a.completed) / COUNT(a.registered)', 'completion');
    $query->fields('a', ['name'])
      ->groupBy('a.name')
      ->orderBy('a.name')
      ->condition('a.registered', $time_str, '<');

    if ($lp_ids) {
      $query->condition('a.gid', $lp_ids, 'IN');
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'a.uid = g_c_f_d.entity_id AND g_c_f_d.gid = a.gid');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
    }

    $query->condition('a.uid', 0, '<>');
    $or_group = $query->orConditionGroup();
    $or_group->condition('a.completed', $time_str, '<');
    $or_group->isNull('a.completed');

    $data = $query
      ->execute()
      ->fetchAll();

    $count = count($data);
    if ($count > 0) {
      foreach ($data as $row) {
        $progress += $row->progress;
        $completion += $row->completion;
      }

      $progress /= $count;
      $completion /= $count;
    }

    return [
      'drupalSettings' => [
        'opignoCharts' => [
          'trainingsProgress' => $this->buildDonutChart((float) $progress, '#trainings-progress-chart'),
          'trainingsCompletion' => $this->buildDonutChart((float) $completion, '#trainings-completion-chart'),
          'usersPerDay' => $this->buildUsersPerDay($datetime, $lp_ids),
        ],
      ],
    ];
  }

  /**
   * Builds one block for the user metrics.
   *
   * @param string $label
   *   Label.
   * @param string $value
   *   Value.
   *
   * @return array
   *   Render array.
   */
  protected function buildUserMetric($label, $value): array {
    return [
      '#theme' => 'opigno_statistics_user_metric',
      '#label' => $label,
      '#value' => $value,
    ];
  }

  /**
   * Builds user metrics.
   *
   * @param array $lp_ids
   *   Learning path IDs.
   *
   * @return array
   *   Render array.
   */
  protected function buildUserMetrics(array $lp_ids = []): array {
    $query = $this->database->select('users', 'u');
    if ($lp_ids) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
      $query->condition('g_c_f_d.gid', $lp_ids, 'IN');
    }
    $query->condition('u.uid', 0, '<>');
    $query->groupBy('u.uid');
    $users = $query->countQuery()->execute()->fetchField();

    $now = $this->time->getRequestTime();
    // Last 7 days.
    $period = 60 * 60 * 24 * 7;

    $query = $this->database->select('users_field_data', 'u');
    if ($lp_ids) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
      $query->condition('g_c_f_d.gid', $lp_ids, 'IN');
    }
    $query->condition('u.uid', 0, '<>');
    $query->condition('u.created', $now - $period, '>');
    $query->groupBy('u.uid');
    $new_users = $query->countQuery()->execute()->fetchField();

    $query = $this->database->select('users_field_data', 'u');
    if ($lp_ids) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
      $query->condition('g_c_f_d.gid', $lp_ids, 'IN');
    }
    $query->condition('u.uid', 0, '<>');
    $query->condition('u.access', $now - $period, '>');
    $query->groupBy('u.uid');
    $active_users = $query->countQuery()->execute()->fetchField();

    return [
      '#theme' => 'opigno_statistics_user_metrics',
      'users' => $this->buildUserMetric($this->t('Users'), $users),
      'new_users' => $this->buildUserMetric($this->t('New users'), $new_users),
      'active_users' => $this->buildUserMetric($this->t('Recently active users'), $active_users),
    ];
  }

  /**
   * Builds trainings listing.
   *
   * @param array $lp_ids
   *   Learning path IDs.
   *
   * @return array
   *   Render array.
   */
  protected function buildTrainingsList(array $lp_ids): array {
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('COUNT(a.completed)', 'users_completed');
    $query->addExpression('AVG(a.time)', 'time');
    $query->fields('a', ['gid', 'name']);

    if ($lp_ids) {
      $query->condition('a.gid', $lp_ids, 'IN');
    }

    $data = $query
      ->groupBy('a.gid')
      ->groupBy('a.name')
      ->orderBy('a.name')
      ->distinct()
      ->execute()
      ->fetchAll();

    $query = $this->database->select('opigno_learning_path_group_user_status', 's');
    $query->addField('s', 'gid');
    $query->condition('s.uid', 0, '<>');
    $query->addExpression('COUNT(*)', 'count');
    $query->groupBy('s.gid');
    $groups = $query->execute()->fetchAllAssoc('gid');

    $table = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'table-striped'],
      ],
      '#prefix' => '<div class="trainings-list-wrapper">',
      '#suffix' => '</div>',
      '#header' => [
        ['data' => $this->t('Training'), 'class' => 'training'],
        ['data' => $this->t('Nb of users'), 'class' => 'users'],
        ['data' => $this->t('Nb completed'), 'class' => 'completed'],
        ['data' => $this->t('Avg time spent'), 'class' => 'time'],
        ['data' => $this->t('Details'), 'class' => 'hidden'],
      ],
      '#rows' => [],
    ];

    // Groups ids of existing groups.
    $gids = $this->database->select('groups', 'g')
      ->fields('g', ['id'])
      ->execute()->fetchCol();

    $options = [
      'attributes' => [
        'class' => ['btn', 'btn-rounded'],
      ],
    ];
    foreach ($data as $row) {
      $row_time = max(0, round($row->time));
      $time_str = $row_time > 0
        ? $this->dateFormatter->formatInterval($row_time)
        : '-';

      $gid = $row->gid;
      // Set links only for existing trainings, empty link otherwise.
      if (in_array($gid, $gids)) {
        $details_link = Link::createFromRoute(
          $this->t('Details'),
          'opigno_statistics.training',
          ['group' => $gid],
          $options
        )->toRenderable();
      }
      else {
        $details_link = [];
      }

      $nb_users = isset($groups[$gid]) ? $groups[$gid]->count : '';
      $table['#rows'][] = [
        ['data' => $row->name, 'class' => 'training'],
        ['data' => $nb_users, 'class' => 'users'],
        ['data' => $row->users_completed, 'class' => 'completed'],
        ['data' => $time_str, 'class' => 'time'],
        ['data' => $details_link, 'class' => 'details'],
      ];
    }

    return $table;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Prepare the list of the available years.
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('YEAR(a.registered)', 'year');
    $data = $query
      ->groupBy('year')
      ->orderBy('year', 'DESC')
      ->execute()
      ->fetchAll();

    $year_select = $this->createYearSelect($data, $form_state);
    $year_selected = (int) $year_select['#default_value'];

    // Prepare the list of months.
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('MONTH(a.registered)', 'month');
    $query->addExpression('YEAR(a.registered)', 'year');
    $data = $query
      ->groupBy('month')
      ->groupBy('year')
      ->orderBy('month')
      ->execute()
      ->fetchAll();

    $month_select = $this->createMonthSelect($data, $year_selected, $form_state);
    $month = (int) $month_select['#default_value'];

    $timestamp = mktime(0, 0, 0, $month, 1, $year_selected);
    $datetime = DrupalDateTime::createFromTimestamp($timestamp);
    $datetime->add(new \DateInterval('P1M'));

    // Check if user has limited permissions for global statistic.
    $account = $this->currentUser();
    $lp_ids = [];
    if (!($account->hasPermission('view global statistics')
      || $account->hasPermission('view any user statistics')
      || $account->id() == 1)) {
      $lp_ids = $this->checkLimitPermissions($account);
    }

    $form['trainings_progress'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'statistics-trainings-progress',
      ],
      // H2 Need for correct structure.
      [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Statistics dashboard'),
        '#attributes' => [
          'class' => ['sr-only'],
        ],
      ],
      '#attached' => $this->buildTrainingsProgress($datetime, $lp_ids),
      'year' => $year_select,
    ];

    if ($year_selected) {
      $form['trainings_progress']['month'] = $month_select;
    }

    $form['trainings_progress']['content_statistics'] = [
      '#type' => 'container',
      'users' => $this->buildUserMetrics($lp_ids),
    ];

    if ($this->moduleHandler->moduleExists('opigno_skills_system')) {
      $form['skills_list'] = $this->buildSkillsTable();
    }

    $form['trainings_list'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['content-box trainings-list'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['trainings-content-title'],
        ],
        '#value' => $this->t('Trainings'),
      ],
      'table' => $this->buildTrainingsList($lp_ids),
    ];

    $form['#attached'] = [
      'library' => ['opigno_statistics/opigno_charts'],
    ];

    return $form;
  }

  /**
   * Builds skills listing.
   *
   * @return array
   *   Render array.
   */
  protected function buildSkillsTable(): array {
    $query = $this->database
      ->select('opigno_skills_statistic', 'a')
      ->fields('a', ['tid']);
    $query->addExpression('AVG(a.score)', 'score');
    $query->addExpression('AVG(a.progress)', 'progress');
    $query->groupBy('tid');

    $rows = $query->execute()->fetchAllAssoc('tid');
    $table_rows = [];
    foreach ($rows as $row) {
      if (!$this->termStorage instanceof TermStorageInterface) {
        continue;
      }

      $tid = $row->tid ?? 0;
      $term = $this->termStorage->load($tid);

      if ($term instanceof TermInterface) {
        $table_rows[] = [
          'data-training' => $row->tid,
          'data' => [
            ['data' => $term->getName(), 'class' => 'skill'],
            [
              'data' => $this->buildScore(round($row->score)),
              'class' => 'score',
            ],
            [
              'data' => $this->buildScore(round($row->progress)),
              'class' => 'progress',
            ],
          ],
        ];
      }
    }

    $rows = array_filter($table_rows);

    if (empty($rows)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['content-box', 'skills-list'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['skills-content-title'],
        ],
        '#value' => $this->t('Skills'),
      ],
      'table' => [
        '#type' => 'table',
        '#prefix' => '<div class="skills-list-wrapper">',
        '#suffix' => '</div>',
        '#attributes' => [
          'class' => ['statistics-table', 'table-striped'],
        ],
        '#header' => [
          ['data' => $this->t('Skill'), 'class' => 'skill'],
          ['data' => $this->t('Score'), 'class' => 'score'],
          ['data' => $this->t('Progress'), 'class' => 'progress'],
        ],
        '#rows' => $rows,
      ],
    ];
  }

  /**
   * Get array of learning paths ID's where user have role 'student manager'.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   The list of Learning path IDs.
   */
  public function checkLimitPermissions(AccountInterface $account): array {
    $query = $this->database->select('group_content_field_data', 'g_c_f_d')
      ->fields('g_c_f_d', ['gid']);
    $query->leftJoin('group_content__group_roles', 'g_c_g_r', 'g_c_f_d.id = g_c_g_r.entity_id');
    $query->condition('g_c_g_r.group_roles_target_id', 'learning_path-user_manager');
    $query->condition('g_c_f_d.entity_id', $account->id());
    $query->condition('g_c_f_d.type', 'learning_path-group_membership');
    $result = $query->execute()->fetchAllAssoc('gid');

    $lp_ids = [];
    foreach ($result as $row) {
      $lp_ids[] = $row->gid;
    }

    return $lp_ids;
  }

  /**
   * Access callback to check that the user can access to view global statistic.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResult {
    $uid = $account->id();

    if ($account->hasPermission('view global statistics')
      || $account->hasPermission('view any user statistics')
      || $uid == 1) {
      return AccessResult::allowed();
    }
    else {
      // Check if user has role 'student manager' in any of trainings.
      $is_user_manager = LearningPathAccess::memberHasRole('user_manager', $account);

      if ($is_user_manager) {
        return AccessResultAllowed::allowed()->mergeCacheMaxAge(0);
      }
      else {
        return AccessResultAllowed::forbidden()->mergeCacheMaxAge(0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The form is submitted with AJAX.
  }

}
