<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "opigno_calendar_events_resource",
 *   label = @Translation("Dashboard: Opigno Calendar Events"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/calendar_events",
 *   }
 * )
 */
class OpignoCalendarEventsRestResource extends ResourceBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Default limit entities per request.
   */
  protected $limit = 10;

  /**
   * Default ordering per request.
   */
  protected $order_by = 'DESC';

  /**
   * Constructs a new DefaultRestResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   A date formatter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
                              array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              array $serializer_formats,
                              LoggerInterface $logger,
                              Connection $database,
                              AccountProxyInterface $current_user,
                              DateFormatterInterface $date_formatter,
                              EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->database = $database;
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('opigno_mobile_app'),
      $container->get('database'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
   $response_data = [
      'items' => [],
      'next_page' => FALSE,
    ];

    // Get request parameters.
    $request = \Drupal::request();
    $request_query = $request->query;
    $request_query_array = $request_query->all();
    // How many items should be returned.
    $limit = $request_query->get('limit') ?: $this->limit;
    // Part of items.
    $page = $request_query->get('page') ?: 0;
    // How to order items.
    $order_by = $this->getOrderBy($request_query);
    // Before start date.
    $before = $request_query->get('before') ? intval($request_query->get('before')) : 0;
    // After start date.
    $after = $request_query->get('after') ? intval($request_query->get('after')) : 0;

    // Get all events.
    $events_ids = $this->getEventsIds($this->currentUser, $order_by, $before, $after);
    $events_count = count($events_ids);
    $position = $limit * ($page + 1);
    $offset = $position - $limit;

    // Find events per page.
    $items_per_page = array_slice($events_ids, $offset, $limit);

    // Build link to next page.
    if (($events_count > $position) && $items_per_page) {
      $next_page_query = $request_query_array;
      $next_page_query['page'] = $page + 1;
      $response_data['next_page'] = Url::createFromRequest($request)
        ->setOption('query', $next_page_query)
        ->toString(TRUE)
        ->getGeneratedUrl();
    }


    $events = \Drupal::entityTypeManager()
      ->getStorage('opigno_calendar_event')
      ->loadMultiple($items_per_page);

    /* @var \Drupal\opigno_calendar_event\Entity\CalendarEvent $event */
    foreach ($events as $event) {
      $storage_format = 'Y-m-d\TH:i:s';
      // Get start date in UTC.
      $row_start = $event->getDateItems()->value;
      $start_date = DrupalDateTime::createFromFormat($storage_format, $row_start, 'UTC');
      // Get end date in UTC.
      $row_end = $event->getDateItems()->end_value;
      $end_date = DrupalDateTime::createFromFormat($storage_format, $row_end, 'UTC');

     $response_data['items'][] = [
        'id' => $event->id(),
        'title' => $event->label(),
        'description' => $event->get('description')->value,
        'start_date' => $start_date->getTimestamp(),
        'end_date' => $end_date->getTimestamp(),
      ];
    }

   $response = new ResourceResponse($response_data, 200);
    // Disable caching.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
//    $response->getCacheableMetadata()->addCacheContexts(['url.query_args', 'url.path']);
    return $response;
  }

  /**
   * Simple function to get 'order_by' parameter from request.
   * @param $request
   *
   * @return string
   */
  protected function getOrderBy($request) {
    $data = strtoupper($request->get('order_by'));
    $order_by = !empty($data) && in_array($data, ['ASC', 'DESC']) ? $data : $this->order_by;
    return $order_by;
  }

  protected function getEventsIds($user, $order_by, $before=0, $after=0) {
    // Users with allowed roles can see all events.
    $allowed_roles = ['administrator', 'content_manager', 'user_manager'];
    $roles = $user->getRoles();
    $diff = array_diff($allowed_roles, $roles);
    $allowed = $diff != $allowed_roles;

    $query = $this->database->select('opigno_calendar_event_field_data', 'o_c_e_f_d');

    if (!$allowed) {
      // Get table name for field "field_calendar_event_members".
      $table_mapping = $this->entityTypeManager->getStorage('opigno_calendar_event')->getTableMapping();
      $field_table = $table_mapping->getFieldTableName('field_calendar_event_members');

      $query->leftJoin(
        $field_table,
        'o_c_e_members',
        'o_c_e_members.entity_id = o_c_e_f_d.id'
      );
      $query->condition('o_c_e_members.field_calendar_event_members_target_id', $user->id());
    }

    if ($before || $after) {
      $query->leftJoin(
        'opigno_calendar_event__date_daterange',
        'o_c_e_d_d',
        'o_c_e_d_d.entity_id = o_c_e_f_d.id'
        );
    }

    if ($before) {
      $before_str = DrupalDateTime::createFromTimestamp($before)
        ->format('Y-m-d\TH:i:s');
      $query->condition('o_c_e_d_d.date_daterange_value', $before_str, '<=');
      $query->orderBy('o_c_e_d_d.date_daterange_value', $order_by);
    }
    if ($after) {
      $after_str = DrupalDateTime::createFromTimestamp($after)
        ->format('Y-m-d\TH:i:s');
      $query->condition('o_c_e_d_d.date_daterange_end_value', $after_str, '>=');
      $query->orderBy('o_c_e_d_d.date_daterange_end_value', $order_by);
    }
    $query->condition('o_c_e_f_d.status', 1);
    $query->fields('o_c_e_f_d', ['id']);
    $results = $query->execute()->fetchAllKeyed();

    return $results ? array_keys($results): [];
  }

}
