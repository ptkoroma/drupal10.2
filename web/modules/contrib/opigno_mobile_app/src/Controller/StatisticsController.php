<?php

namespace Drupal\opigno_mobile_app\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Class StatisticsController.
 */
class StatisticsController extends ControllerBase {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $serializerFormats = [];

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;
  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new StatisticsController object.
   *
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   A date formatter.
   */
  public function __construct(
                              Serializer $serializer,
                              array $serializer_formats,
                              LoggerInterface $logger,
                              EntityTypeManagerInterface $entity_type_manager,
                              Connection $database,
                              DateFormatterInterface $date_formatter) {
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    if ($container->hasParameter('serializer.formats') && $container->has('serializer')) {
      $serializer = $container->get('serializer');
      $formats = $container->getParameter('serializer.formats');
    }
    else {
      $formats = ['json'];
      $encoders = [new JsonEncoder()];
      $serializer = new Serializer([], $encoders);
    }

    return new static(
      $serializer,
      $formats,
      $container->get('logger.factory')->get('opigno_mobile_app'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }


  /**
   *  Get statistics info from the first created training to a specific date.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with data.
   * @throws \Exception
   */
  public function getStatistics() {
    // Array with response data.
    $response_data = [];
    // Get request parameters.
    $request = \Drupal::request();
    $request_query = $request->query;
    // Get year filter from request.
    $default_year =  $this->dateFormatter->format(\Drupal::time()->getCurrentTime(), 'custom', 'Y');
    $year = $request_query->get('year') && is_numeric($request_query->get('year'))
      ? intval($request_query->get('year')) : $default_year;
    // Get month filter from request.
    $default_month = $this->dateFormatter->format(\Drupal::time()->getCurrentTime(), 'custom', 'n');
    $month = $request_query->get('month') && is_numeric($request_query->get('month'))
      ? intval($request_query->get('month')) : $default_month;
    // Get training id filter from request.
    $tid = $request_query->get('training') && is_numeric($request_query->get('training'))
      ? $request_query->get('training') : NULL;

    // Build datetime range.
    $timestamp = mktime(0, 0, 0, $month, 1, $year);
    $datetime = DrupalDateTime::createFromTimestamp($timestamp);
    $datetime->add(new \DateInterval('P1M'));

    // Get progress and completion.
    $progress = $this->getTrainingsProgress($datetime, $tid);
    // Get users number per day.
    $users_per_day = $this->getUsersPerDay($datetime, $tid);
    // Get users per training.
    $members_count = array_map(function($item) {
      $training = Group::load($item->id);
      return [
        'training_id' => intval($item->id),
        'label' => $training->label(),
        'users_count' => intval($item->count),
      ];
    }, $this->getUsersPerTrainings($datetime, $tid));
    // Sort members_count by numbers of members.
    usort($members_count, function ($a, $b) {
      $a_users = $a['users_count'];
      $b_users = $b['users_count'];
      if ($a_users == $b_users) {
        return $a['training_id'] - $b['training_id'];
      }
      return $b_users - $a_users;
    });
    // Get users metrics.
    $users_metrics = $this->getUsersMetrics();
    // Build response data.
    $response_data['data'] = [
      'date' => [
        'year' => $year,
        'month' => $month,
      ],
      'training_progress' => $progress['progress'],
      'training_completion' => $progress['completion'],
      'users_metrics' => $users_metrics,
      'users_per_day' => $users_per_day,
      'members_by_training' => $members_count ?: [],
    ];

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   *  Get years for which we can see statistics.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with data.
   * @throws \Exception
   */
  public function getYears() {
    $query = $this->database
      ->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('YEAR(a.registered)', 'year');
    $data = $query
      ->groupBy('year')
      ->orderBy('year', 'DESC')
      ->execute()
      ->fetchAll();
    $years = [];
    foreach ($data as $row) {
      $year = $row->year;
      if (!isset($years[$year])) {
        $years[$year] = $year;
      }
    }

    // Build response data.
    if (empty($years)) {
      $years[] = $this->dateFormatter->format(\Drupal::time()
        ->getCurrentTime(), 'custom', 'Y');
    }
    else {
      $years = array_keys($years);
    }
    $response_data['data'] = [
      'years' => $years,
    ];

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get trainings progress and completion.
   *
   * @see \Drupal\opigno_statistics\Form\DashboardForm::buildTrainingsProgress()
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $datetime
   *   Date.
   * @param mixed $lp_id
   *
   * @return array
   *   Array with Training progress and completion in percents.
   */
  private function getTrainingsProgress(DrupalDateTime $datetime, $lp_id = NULL) {
    $progress = 0;
    $completion = 0;

    $time_str = $datetime->format(DrupalDateTime::FORMAT);

    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('SUM(a.progress) / COUNT(a.progress) / 100', 'progress');
    $query->addExpression('COUNT(a.completed) / COUNT(a.registered)', 'completion');
    $query->fields('a', ['name'])
      ->groupBy('a.name')
      ->orderBy('a.name')
      ->condition('a.registered', $time_str, '<');

    if ($lp_id) {
      $query->condition('a.gid', $lp_id, '=');
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
      'progress' => round(100 * $progress),
      'completion' => round(100 * $completion),
    ];
  }

  /**
   * Get active users per day.
   *
   * @see \Drupal\opigno_statistics\Form\DashboardForm::buildUsersPerDay()
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $datetime
   *   Date.
   * @param mixed $lp_id
   *   LP ID.
   *
   * @return array
   *   Array with number of users associated by day.
   *
   * @throws \Exception
   */
  private function getUsersPerDay(DrupalDateTime $datetime, $lp_id = NULL) {
    $max_time = $datetime->format(DrupalDateTime::FORMAT);
    $max_timestamp = $datetime->getTimestamp();
    // Last month.
    $min_datetime = $datetime->sub(new \DateInterval('P1M'));
    $min_time = $min_datetime->format(DrupalDateTime::FORMAT);

    $query = $this->database
      ->select('opigno_statistics_user_login', 'u');
    $query->addExpression('DAY(u.date)', 'hour');
    $query->addExpression('COUNT(DISTINCT u.uid)', 'count');

    if ($lp_id) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.gid', $lp_id, '=');
      $query->condition('g_c_f_d.created', $max_timestamp, '<=');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
    }

    $query->condition('u.uid', 0, '<>');

    $data = $query
      ->condition('u.date', [$min_time, $max_time], 'BETWEEN')
      ->groupBy('hour')
      ->execute()
      ->fetchAllAssoc('hour');

    if (function_exists('cal_days_in_month')) {
      $month = $datetime->format('n');
      $year = $datetime->format('Y');
      $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }
    else {
      $days_in_month = 31;
    }

    $users_count = [];
    for ($i = 0; $i < $days_in_month; ++$i) {
      if (isset($data[$i])) {
        $users_count[$i] = intval($data[$i]->count);
      }
      else {
        $users_count[$i] = 0;
      }
    }

    return $users_count;
  }

  /**
   * Get trainings members.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $datetime
   *   Date.
   * @param mixed $lp_id
   *   LP ID.
   *
   * @return array
   *   Array with number of members associated by training.
   *
   * @throws \Exception
   */
  private function getUsersPerTrainings(DrupalDateTime $datetime, $lp_id = NULL) {
    $max_time = $datetime->getTimestamp();

    $query = $this->database
      ->select('group_content_field_data', 'g_c_f_d');

    if ($lp_id) {
      $query->condition('g.id', $lp_id);
    }

    $query->leftJoin('groups_field_data', 'g', 'g_c_f_d.gid = g.id');
    $query->addExpression('COUNT(DISTINCT g_c_f_d.entity_id)', 'count');
    $query->condition('g_c_f_d.type', 'learning_path-group_membership');

    $query->condition('g_c_f_d.created', $max_time, '<=');
    $query->groupBy('g.id');
    $query->fields('g', ['id']);
    $data = $query->execute()
      ->fetchAllAssoc('id');

    return $data;

  }

  /**
   * Builds user metrics.
   * @see \Drupal\opigno_statistics\Form\DashboardForm::buildUserMetrics()
   *
   * @return array
   *   Array with users metrics info.
   */
  protected function getUsersMetrics($lp_ids = NULL) {
    $query = $this->database
      ->select('users', 'u');
    if (is_array($lp_ids)) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
      $query->condition('g_c_f_d.gid', $lp_ids, 'IN');
    }
    $query->condition('u.uid', 0, '<>');
    $query->groupBy('u.uid');
    $users = $query->countQuery()->execute()->fetchField();

    $now = \Drupal::time()->getRequestTime();
    // Last 7 days.
    $period = 60 * 60 * 24 * 7;

    $query = $this->database
      ->select('users_field_data', 'u');
    if (is_array($lp_ids)) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
      $query->condition('g_c_f_d.gid', $lp_ids, 'IN');
    }
    $query->condition('u.uid', 0, '<>');
    $query->condition('u.created', $now - $period, '>');
    $query->groupBy('u.uid');
    $new_users = $query->countQuery()->execute()->fetchField();

    $query = $this->database
      ->select('users_field_data', 'u');
    if (is_array($lp_ids)) {
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'u.uid = g_c_f_d.entity_id');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
      $query->condition('g_c_f_d.gid', $lp_ids, 'IN');
    }
    $query->condition('u.uid', 0, '<>');
    $query->condition('u.access', $now - $period, '>');
    $query->groupBy('u.uid');
    $active_users = $query->countQuery()->execute()->fetchField();

    return [
      'all' => intval($users),
      'new' => intval($new_users),
      'active' => intval($active_users),
    ];
  }

  /**
   * Gets the format of the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return string
   *   The format of the request.
   */
  protected function getRequestFormat(Request $request) {
    $format = $request->getRequestFormat();
    if (!in_array($format, $this->serializerFormats)) {
      throw new BadRequestHttpException("Unrecognized format: $format.");
    }
    return $format;
  }

}
