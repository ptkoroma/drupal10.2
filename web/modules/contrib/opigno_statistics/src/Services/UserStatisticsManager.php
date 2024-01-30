<?php

namespace Drupal\opigno_statistics\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;
use Drupal\opigno_statistics\StatisticsPageTrait;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\user\UserInterface;

/**
 * User statistics manager service definition.
 *
 * @package Drupal\opigno_statistics\Services
 */
class UserStatisticsManager {

  use StringTranslationTrait;
  use StatisticsPageTrait;

  /**
   * The current user ID.
   *
   * @var int
   */
  protected $currentUid;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The User module status entities storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $userModuleStatusStorage = NULL;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * Whether the social features enabled or not.
   *
   * @var bool
   */
  public $isSocialsEnabled;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  public $termStorage = NULL;

  /**
   * UserStatisticsManager constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    AccountInterface $account,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    CsrfTokenGenerator $csrf_token,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler
  ) {
    $this->database = $database;
    $this->account = $account;
    $this->currentUid = (int) $account->id();
    $this->dateFormatter = $date_formatter;
    $this->csrfToken = $csrf_token;
    $config_set = (bool) $config_factory->get('opigno_class.socialsettings')->get('enable_social_features') ?? FALSE;
    $this->isSocialsEnabled = $module_handler->moduleExists('opigno_social') && $config_set;

    try {
      $this->userModuleStatusStorage = $entity_type_manager->getStorage('user_module_status');
      $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_statistics_exception', $e);
    }
  }

  /**
   * Get the number of user trainings by status in the given amount of days.
   *
   * @param int $days
   *   The amount of days to get statistics for. If 0 given, statistics will be
   *   calculated without time limitation.
   * @param int $uid
   *   The ID of user to get statistics for. By default will be calculated for
   *   the current user.
   * @param string $status
   *   The status to get trainings with.
   *
   * @return int
   *   The number of user trainings by status in the given amount of days.
   */
  public function getUserTrainingsNumberByStatus(int $days = 0, int $uid = 0, string $status = 'completed'): int {
    $uid = $uid ?: $this->currentUid;
    if (!$uid) {
      return 0;
    }

    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->join('groups', 'g', 'g.id = a.gid');
    $query->fields('a', ['id'])
      ->condition('a.uid', $uid)
      ->condition('a.status', $status);
    if ($days) {
      $field = $status === 'completed' ? 'completed' : 'registered';
      $timestamp = strtotime("tomorrow -$days days");
      $query->where("UNIX_TIMESTAMP(a.$field) >= :timestamp", [':timestamp' => $timestamp]);
    }

    $result = $query
      ->countQuery()
      ->execute()
      ->fetchField();

    return (int) $result;
  }

  /**
   * Get the number of user trainings with status grouped by the period of time.
   *
   * @param int $uid
   *   The ID of user to get statistics for. By default will be calculated for
   *   the current user.
   * @param string $status
   *   The status to get trainings with.
   *
   * @return array
   *   The array of data for the chart rendering.
   */
  public function getGroupedUserTrainingsNumber(int $uid = 0, string $status = 'completed'): array {
    $uid = $uid ?: $this->currentUid;
    if (!$uid) {
      return [];
    }

    // Get the amount of completed trainings grouped by week.
    $field = $status === 'completed' ? 'completed' : 'registered';
    // Need to display the current day in the chart.
    $timestamp = strtotime("tomorrow -30 days");

    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->join('groups', 'g', 'g.id = a.gid');
    $query->addExpression("WEEK(a.$field)", 'period');
    $query->addExpression('COUNT(DISTINCT a.gid)', 'count');
    $query->condition('a.uid', $uid)
      ->condition('a.status', $status);
    $query->where("UNIX_TIMESTAMP(a.$field) >= :timestamp", [':timestamp' => $timestamp]);

    $data = $query->groupBy('period')
      ->execute()
      ->fetchAllAssoc('period');

    return $this->prepareChartData($data);
  }

  /**
   * Prepare the data to build the bar chart by user trainings.
   *
   * @param array $data
   *   The data for the chart.
   *
   * @return array
   *   The data to build the bar chart by user trainings.
   */
  private function prepareChartData(array $data): array {
    $result = [];
    $timestamp = strtotime('today');
    // Get the first week number. We'll display 5 weeks for each month.
    $i = 5;
    do {
      $week = (int) $this->dateFormatter->format($timestamp, 'custom', 'W');
      $key = $this->t('Week') . $i;
      $result[$key] = isset($data[$week]) ? $data[$week]->count : 0;
      // Subtract 1 week from the timestamp.
      $timestamp -= 604800;
      $i--;
    } while ($i > 0);

    ksort($result);

    return $result;
  }

  /**
   * Get the certificates earned by user in the given amount of days.
   *
   * @param int $days
   *   The amount of days to get statistics for. If 0 given, statistics will be
   *   calculated without time limitation.
   * @param int $uid
   *   The ID of user to get statistics for. By default will be calculated for
   *   the current user.
   * @param bool $count
   *   If results should be counted or not. If FALSE, the array of results will
   *   be returned.
   *
   * @return array|int
   *   The number of certificates earned by user in the given amount of days OR
   *   the list of certificates.
   */
  public function getUserCertificates(int $days = 0, int $uid = 0, bool $count = TRUE) {
    $uid = $uid ?: $this->currentUid;
    if (!$uid) {
      return [];
    }

    $query = $this->database->select('group__field_certificate', 'gc');
    $query->join('opigno_learning_path_achievements', 'a', 'gc.entity_id = a.gid');
    $query->fields('a', ['gid', 'completed', 'name'])
      ->condition('a.uid', $uid)
      ->condition('a.status', 'completed');
    if ($days) {
      $timestamp = strtotime("-$days days");
      $query->where('UNIX_TIMESTAMP(a.completed) >= :timestamp', [':timestamp' => $timestamp]);
    }

    if ($count) {
      $result = $query->countQuery()->execute()->fetchField();

      return (int) $result;
    }

    return $query->orderBy('a.name')->execute()->fetchAll();
  }

  /**
   * Get the time that user spent on trainings in the given amount of days.
   *
   * @param int $days
   *   The amount of days to get statistics for. If 0 given, statistics will be
   *   calculated without time limitation.
   * @param int $uid
   *   The ID of user to get statistics for. By default will be calculated for
   *   the current user.
   *
   * @return int
   *   The time (in seconds) that user spent on trainings in the given amount of
   *   days.
   */
  public function getUserTrainingsTime(int $days = 0, int $uid = 0): int {
    $time = 0;
    $uid = $uid ?: $this->currentUid;

    if (!$uid || !$this->userModuleStatusStorage instanceof EntityStorageInterface) {
      return $time;
    }

    // Get IDs of user module status entities.
    $query = $this->userModuleStatusStorage->getQuery()
      ->condition('user_id', $uid)
      ->condition('finished', 0, '>');
    // Add extra condition if time limit is set.
    if ($days) {
      $timestamp = strtotime("tomorrow -$days days");
      $query->condition('started', $timestamp, '>=');
    }

    $ids = $query->execute();
    if (!$ids) {
      return $time;
    }

    // Load entities and calculate the general time that user spent on modules
    // completion.
    $entities = $this->userModuleStatusStorage->loadMultiple($ids);
    if (!$entities) {
      return $time;
    }

    $time = 0;
    foreach ($entities as $entity) {
      if ($entity instanceof UserModuleStatusInterface) {
        $time += $entity->getCompletionTime();
      }
    }

    return $time;
  }

  /**
   * Get the list of skills acquired by the user.
   *
   * @param int $uid
   *   The user ID. The current user will be taken by default.
   *
   * @return array
   *   The list of skills acquired by the user.
   */
  public function getSkillsAcquired(int $uid = 0): array {
    if (!$uid) {
      $uid = $this->currentUid;
    }

    $query = $this->database->select('opigno_skills_statistic', 'a')
      ->fields('a', ['tid', 'stage'])
      ->condition('a.uid', $uid);

    return $query->execute()->fetchAll();
  }

  /**
   * Get the amount of badges earned by the user.
   *
   * @param int $uid
   *   The user ID. The current user will be taken by default.
   * @param bool $count
   *   If results should be counted or not. If FALSE, the array of results will
   *   be returned.
   *
   * @return int|array
   *   The amount of badges earned by the user OR the list of badges.
   */
  public function getUserBadges(int $uid = 0, bool $count = TRUE) {
    if (!$uid) {
      $uid = $this->currentUid;
    }

    // Badges for courses and modules are stored in different tables and have
    // the different structure.
    $query = $this->database->select('opigno_module_badges', 'omb');
    $query->leftJoin('group__badge_name', 'gbn', 'gbn.entity_id = omb.entity_id AND omb.typology = :course', [':course' => 'Course']);
    $query->leftJoin('opigno_module_field_data', 'omf', 'omf.id = omb.entity_id AND omb.typology = :module', [':module' => 'Module']);
    $query->fields('gbn', ['badge_name_value'])
      ->fields('omf', ['badge_name'])
      ->fields('omb', ['typology', 'entity_id'])
      ->condition('omb.uid', $uid);

    if ($count) {
      $result = $query->countQuery()->execute()->fetchField();

      return (int) $result;
    }

    return $query->execute()->fetchAll();
  }

  /**
   * Prepare the render array to display the user statistics.
   *
   * @param int $days
   *   The amount of days to get stats for.
   * @param int $uid
   *   The user ID to get stats for.
   *
   * @return array
   *   The render array to display the user statistics.
   */
  public function renderUserStatistics(int $days, int $uid = 0): array {
    $uid = $uid ?: $this->currentUid;
    // Generate the url to update the user statistics section.
    $url = Url::fromRoute('opigno_statistics.get_user_stats_block');
    $internal = $url->getInternalPath();
    $url->setOption('query', ['token' => $this->csrfToken->get($internal)]);
    $url = $url->toString();

    // Gather stats.
    $completed = $this->getUserTrainingsNumberByStatus($days, $uid);
    $current = $this->getUserTrainingsNumberByStatus($days, $uid, 'pending');
    $certificates = $this->getUserCertificates($days, $uid);
    $time = $this->getUserTrainingsTime($days, $uid);
    $time_progress = 2 * $time - $this->getUserTrainingsTime(2 * $days, $uid);
    $formatted_time_progress = $this->dateFormatter->formatInterval(abs($time_progress));

    // Calculate the progress comparing to the previous the same period.
    return [
      '#theme' => 'opigno_user_stats_block',
      '#stats' => [
        'trainings_completed' => [
          'title' => $this->t('Training(s) completed'),
          'amount' => $completed,
          'progress' => 2 * $completed - $this->getUserTrainingsNumberByStatus($days * 2, $uid),
        ],
        'current_trainings' => [
          'title' => $this->t('Current training(s)'),
          'amount' => $current,
          'progress' => 2 * $current - $this->getUserTrainingsNumberByStatus($days * 2, $uid, 'pending'),
        ],
        'certificates' => [
          'title' => $this->t('Certificate(s) received'),
          'amount' => $certificates,
          'progress' => 2 * $certificates - $this->getUserCertificates(2 * $days, $uid),
        ],
        'time' => [
          'title' => $this->t('Time spent on Training'),
          'amount' => $this->dateFormatter->formatInterval($time),
          'progress' => $time_progress < 0 ? "-$formatted_time_progress" : $formatted_time_progress,
        ],
      ],
      '#attached' => [
        'library' => ['opigno_statistics/user_statistics'],
        'drupalSettings' => [
          'dashboard' => [
            'userStatsBlockUrl' => $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url,
            'userId' => $uid,
          ],
        ],
      ],
      // Don't cache data, it depends on days amount and trainings completion.
      '#cache' => [
        '#max-age' => 0,
      ],
    ];
  }

  /**
   * Prepare the render array to display the user training charts.
   *
   * @param int $uid
   *   The user ID to get stats for.
   *
   * @return array
   *   The render array to display the user training charts.
   */
  public function renderUserTrainingsCharts(int $uid = 0): array {
    $uid = $uid ?: $this->currentUid;
    $completed = $this->getGroupedUserTrainingsNumber($uid);
    $current = $this->getGroupedUserTrainingsNumber($uid, 'pending');

    return [
      '#theme' => 'opigno_user_training_charts',
      '#attached' => [
        'library' => ['opigno_statistics/opigno_charts'],
        'drupalSettings' => [
          'opignoCharts' => [
            'completedTrainings' => $this->buildBarChart($completed, "#user-completed-trainings-chart"),
            'currentTrainings' => $this->buildBarChart($current, "#user-current-trainings-chart"),
          ],
        ],
      ],
    ];
  }

  /**
   * Prepare the array of settings to build the bar chart.
   *
   * @param array $data
   *   The chart data.
   * @param string $canvas_id
   *   The ID of the html canvas element to render the chart in.
   *
   * @return array
   *   The array of settings to build the bar chart.
   */
  public function buildBarChart(array $data, string $canvas_id) {
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

    $bar_color = $color_palette['desktop_link'] ?? '#4AD3B0';

    return [
      'id' => $canvas_id,
      'type' => 'bar',
      'labels' => array_keys($data),
      'datasets' => [
        [
          'data' => array_values($data),
          'barThickness' => 15,
          'backgroundColor' => $bar_color,
          'hoverBackgroundColor' => $bar_color,
          'borderColor' => $bar_color,
          'borderWidth' => 2,
          'borderRadius' => 20,
          'borderSkipped' => FALSE,
        ],
      ],
      'options' => [
        'scales' => [
          'yAxes' => [
            'beginAtZero' => TRUE,
            'ticks' => [
              'precision' => 0,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Get the user role name.
   *
   * @param \Drupal\user\UserInterface|\Drupal\Core\Session\AccountInterface|null $user
   *   The user to get the role of.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable user role name.
   */
  public function getUserRole(?AccountInterface $user = NULL): TranslatableMarkup {
    // Add user role.
    if (!$user) {
      $user = $this->account;
    }

    $roles = $user->getRoles(TRUE);
    if (!$roles) {
      $role = $this->t('Student');
    }
    elseif (in_array('administrator', $roles)) {
      $role = $this->t('Administrator');
    }
    else {
      $role = $this->t('Manager');
    }

    return $role;
  }

  /**
   * Get the default user picture.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to get the name.
   * @param string $image_style
   *   The image style to render the user picture.
   *
   * @return array
   *   The render array to display the default user picture.
   */
  public static function getUserPicture(UserInterface $user, string $image_style = 'user_compact_image'): array {
    // Display the user image if it isn't empty.
    if (!$user->get('user_picture')->isEmpty()) {
      return $user->get('user_picture')->view([
        'label' => 'hidden',
        'type' => 'image',
        'settings' => [
          'image_style' => $image_style,
        ],
      ]);
    }

    // Display the default profile picture.
    return static::getDefaultUserPicture($user);
  }

  /**
   * Get the default user image.
   *
   * @param \Drupal\user\UserInterface|NULL $user
   *   The user to display the image for.
   *
   * @return array
   *   Render array to display the default user image.
   */
  public static function getDefaultUserPicture(?UserInterface $user = NULL): array {
    $path = \Drupal::service('extension.list.theme')->getPath('aristotle') . '/src/images/content/profil.svg';
    $name = $user instanceof UserInterface ? $user->getDisplayName() : t('User');

    return [
      '#theme' => 'image',
      '#uri' => file_exists($path) ? \Drupal::service('file_url_generator')->transformRelative(base_path() . $path) : '',
      '#alt' => $name,
      '#title' => $name,
    ];
  }

  /**
   * Prepare the render array for the user's trainings list.
   *
   * @param int $uid
   *   The user ID to build the trainings list for.
   * @param bool $completed
   *   If TRUE, the list of completed trainings will be returned. Otherwise all
   *   trainings will be returned.
   *
   * @return array
   *   Render array to display user's trainings list.
   */
  public function buildTrainingsList(int $uid, bool $completed = FALSE): array {
    $fields = ['gid', 'name', 'progress', 'status'];
    // Add an extra column with the completion date.
    if ($completed) {
      array_splice($fields, 3, 0, 'completed');
    }

    // Join the groups table to be sure that the training wasn't deleted.
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->join('groups', 'g', 'g.id = a.gid');
    $query->fields('a', $fields)
      ->condition('a.uid', $uid);

    if ($completed) {
      $query->condition('a.status', 'completed');
    }

    $rows = $query->orderBy('a.name')
      ->execute()
      ->fetchAll();

    // Prepare the table header.
    $header = [
      ['data' => $this->t('Name'), 'class' => 'name'],
      ['data' => $this->t('Progress'), 'class' => 'progress'],
      ['data' => $this->t('Status'), 'class' => 'status'],
      ['data' => $this->t('Details'), 'class' => 'hidden'],
    ];
    if ($completed) {
      $date = [['data' => $this->t('Date'), 'class' => 'date']];
      array_splice($header, 2, 0, $date);
    }

    $table_rows = [];
    $build = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'trainings-list'],
      ],
      '#header' => $header,
    ];

    if (!$rows) {
      return $build + ['#rows' => $table_rows];
    }

    // Details link options.
    $options = [
      'attributes' => [
        'class' => ['btn', 'btn-rounded'],
      ],
    ];

    // Build table rows.
    foreach ($rows as $row) {
      $gid = $row->gid;
      $status = $row->status ?? 'pending';
      $progress = $row->progress ?? 0;

      // Generate the details link.
      $params = ['account' => $uid, 'group' => $gid];
      $details = Link::createFromRoute($this->t('Details'), 'opigno_learning_path.training_by_user', $params, $options)->toRenderable();

      // Prepare the table row.
      $table_row = [
        ['data' => $row->name ?? '', 'class' => 'name'],
        ['data' => $progress . '%', 'class' => 'progress'],
        ['data' => $this->buildStatus($status), 'class' => 'status'],
        ['data' => $details, 'class' => 'details'],
      ];
      if ($completed) {
        $timestamp = strtotime($row->completed ?: 'now');
        $date = $this->dateFormatter->format($timestamp, 'day_month_year');
        array_splice($table_row, 2, 0, $date);
      }

      $table_rows[] = $table_row;
    }

    return $build + ['#rows' => $table_rows];
  }

  /**
   * Build the user certificates list table.
   *
   * @param int $uid
   *   The user ID to build the certificates list for.
   *
   * @return array
   *   The render array to build the user certificates list table.
   */
  public function buildCertificatesList(int $uid): array {
    $rows = $this->getUserCertificates(0, $uid, FALSE);
    // Add the share link if social settings enabled and the user is viewing the
    // own profile.
    $sharing_enabled = $this->isSocialsEnabled && $this->currentUid === $uid;

    $table_rows = [];
    $build = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'certificates-list'],
      ],
      '#header' => [
        ['data' => $this->t('Name'), 'class' => 'name'],
        ['data' => $this->t('Date'), 'class' => 'date'],
        ['data' => $this->t('Download'), 'class' => 'hidden'],
      ],
    ];

    if (!$rows) {
      return $build + ['#rows' => $table_rows];
    }

    // Add the column to the table if sharing is enabled.
    if ($sharing_enabled) {
      $share = [['data' => $this->t('Share'), 'class' => 'hidden']];
      array_splice($build['#header'], 2, 0, $share);
    }

    // Link options.
    $download_options = [
      'attributes' => [
        'class' => ['btn', 'btn-rounded'],
      ],
    ];
    $download_title = Markup::create('<i class="fi fi-rr-download"></i>' . $this->t('download'));

    if ($sharing_enabled) {
      $share_options = $download_options;
      $share_options['attributes']['data-opigno-attachment-type'] = 'certificate';
      $share_options['attributes']['data-opigno-attachment-entity-type'] = 'group';
      $share_title = Markup::create('<i class="fi fi-rr-redo"></i>' . $this->t('share'));
    }

    // Build table rows.
    foreach ($rows as $row) {
      $gid = $row->gid ?? 0;
      $name = $this->t('Certificate for @training', ['@training' => $row->name ?? '']);
      $timestamp = strtotime($row->completed ?? 'now');
      $date = $this->dateFormatter->format($timestamp, 'day_month_year');

      // Generate the certificate download link.
      $params = ['entity_type' => 'group', 'entity_id' => $gid];
      $download = Url::fromRoute('certificate.entity.pdf', $params, $download_options);
      $download = $download->access() ? Link::fromTextAndUrl($download_title, $download)->toRenderable() : [];

      $table_row = [
        ['data' => $name, 'class' => 'name'],
        ['data' => $date, 'class' => 'date'],
        ['data' => $download, 'class' => 'download'],
      ];

      if (!$sharing_enabled) {
        $table_rows[] = $table_row;
        continue;
      }

      // Add the share link if the sharing enabled.
      $share_options['attributes']['data-opigno-post-attachment-id'] = $gid ?? '';
      $share = Link::createFromRoute($share_title, '<current>', [], $share_options)->toRenderable();
      $share = [['data' => $share, 'class' => 'share']];
      array_splice($table_row, 2, 0, $share);

      // Prepare the table row.
      $table_rows[] = $table_row;
    }

    return $build + ['#rows' => $table_rows];
  }

  /**
   * Build the user badges list table.
   *
   * @param int $uid
   *   The user ID to build the badges list for.
   *
   * @return array
   *   The render array to build the user badges list table.
   */
  public function buildBadgesList(int $uid): array {
    $rows = $this->getUserBadges($uid, FALSE);
    $table_rows = [];
    // Add the share link if social settings enabled and the user is viewing the
    // own profile.
    $sharing_enabled = $this->isSocialsEnabled && $this->currentUid === $uid;

    $build = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'badges-list'],
      ],
      '#header' => [
        ['data' => $this->t('Name'), 'class' => 'name'],
      ],
    ];

    // Add the share link if social settings enabled.
    if ($sharing_enabled) {
      $build['#header'][] = [
        'data' => $this->t('Share'),
        'class' => 'hidden',
      ];
    }

    if (!$rows) {
      return $build + ['#rows' => $table_rows];
    }

    // Share link options.
    $options = [
      'attributes' => [
        'class' => ['btn', 'btn-rounded'],
        'data-opigno-attachment-type' => 'badge',
      ],
    ];
    $title = Markup::create('<i class="fi fi-rr-redo"></i>' . $this->t('share'));

    // Build table rows.
    foreach ($rows as $row) {
      $name = $row->typology === 'Course' ? $row->badge_name_value : $row->badge_name;
      $table_row = [
        ['data' => $name ?: $this->t('Badge'), 'class' => 'name'],
      ];

      if (!$sharing_enabled) {
        $table_rows[] = $table_row;
        continue;
      }

      // Add the share button.
      $options['attributes']['data-opigno-attachment-entity-type'] = $row->typology === 'Course' ? 'group' : 'opigno_module';
      $options['attributes']['data-opigno-post-attachment-id'] = $row->entity_id ?? '';
      $share = Link::createFromRoute($title, '<current>', [], $options)->toRenderable();

      // Prepare the table row.
      $table_row[] = ['data' => $share, 'class' => 'share'];
      $table_rows[] = $table_row;
    }

    return $build + ['#rows' => $table_rows];
  }

  /**
   * Build the user skills list table.
   *
   * @param int $uid
   *   The user ID to build the skills list for.
   *
   * @return array
   *   The render array to build the user skills list table.
   */
  public function buildSkillsList(int $uid): array {
    $rows = $this->getSkillsAcquired($uid);
    $table_rows = [];
    $build = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'skills-list'],
      ],
      '#header' => [
        ['data' => $this->t('Name'), 'class' => 'name'],
        ['data' => $this->t('Level'), 'class' => 'level'],
      ],
    ];

    if (!$rows || !$this->termStorage instanceof TermStorageInterface) {
      return $build + ['#rows' => $table_rows];
    }

    // Build the list of user skills.
    foreach ($rows as $row) {
      $tid = $row->tid ?? NULL;
      if (!$tid) {
        continue;
      }

      $term = $this->termStorage->load($tid);
      if (!$term instanceof TermInterface || !$term->hasField('field_level_names')) {
        continue;
      }

      // Get the total amount of the skill levels.
      $levels = $term->get('field_level_names')->getValue();
      $levels_amount = count($levels);
      $not_completed_amount = $row->stage ?? 0;
      $result = [];

      // Generate the skill level markup.
      for ($i = $levels_amount; $i > 0; $i--) {
        $result[] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => [
              'skill-stage',
              $i > $not_completed_amount ? 'skill-stage__completed' : 'skill-stage__not-completed',
            ],
          ],
        ];
      }

      $table_rows[] = [
        ['data' => $term->label(), 'class' => 'name'],
        ['data' => $result, 'class' => 'level'],
      ];
    }

    return $build + ['#rows' => $table_rows];
  }

}
