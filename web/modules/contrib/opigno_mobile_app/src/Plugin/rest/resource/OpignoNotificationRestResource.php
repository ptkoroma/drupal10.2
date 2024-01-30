<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RenderContext;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "opigno_notification_resource",
 *   label = @Translation("Dashboard: Opigno Notifications"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/opigno_notifications",
 *   }
 * )
 */
class OpignoNotificationRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Default limit entities per request.
   */
  protected $limit = 10;

  /**
   * Default ordering per request.
   */
  protected $order_by = 'DESC';

  /**
   * Default value for filtering read or unread notification per request.
   */
  protected $active = TRUE;

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
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
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
      $container->get('current_user')
    );
  }

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {

    // Wrap fully data to avaoid to early rendering.
    $response = \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () {
      $response = [
        'items' => [],
        'next_page' => FALSE,
      ];
      $request = \Drupal::request();
      $request_query = $request->query;
      $request_query_array = $request_query->all();
      $limit = $request_query->get('limit') ?: $this->limit;
      $page = $request_query->get('page') ?: 0;
      $order_by = $request_query->get('order_by') && in_array(strtoupper($request_query->get('order_by')), ['ASC', 'DESC'])
        ? $request_query->get('order_by') : $this->order_by;
      $active = $request_query->get('active') ?
        filter_var($request_query->get('active'), FILTER_VALIDATE_BOOLEAN) : $this->active;
  
      // Find out how many notification do we have.
      $query = \Drupal::entityQuery('opigno_notification')->condition('uid', $this->currentUser->id());
      if ($active) {
        $query->condition('has_read', 0, '=');
      }
      $notifications_count = $query->count()->execute();
      $position = $limit * ($page + 1);
      if ($notifications_count > $position) {
        $next_page_query = $request_query_array;
        $next_page_query['page'] = $page + 1;
        $response['next_page'] = Url::createFromRequest($request)
          ->setOption('query', $next_page_query)
          ->toString(TRUE)
          ->getGeneratedUrl();
      }
  
      // Load notifications.
      $query = \Drupal::entityQuery('opigno_notification')
        ->condition('uid', $this->currentUser->id())
        ->sort('created', strtoupper($order_by))
        ->pager($limit);
      // Filter only unread notification otherwise get all.
      if ($active) {
        $query->condition('has_read', 0, '=');
      }
      $result = $query->execute();
      $notifications = \Drupal::entityTypeManager()
        ->getStorage('opigno_notification')
        ->loadMultiple($result);
  
      /* @var  \Drupal\opigno_notification\Entity\OpignoNotification $notification */
      foreach ($notifications as $notification) {
        $response['items'][] = [
          'id' => $notification->id(),
          'created' => $notification->getCreatedTime(),
          'uid' => $notification->getUser(),
          'message' => $notification->getMessage(),
          'has_read' => $notification->getHasRead(),
        ];
      }

      return $response;
    });

    $response = new ResourceResponse($response, 200);
    // Disable caching.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    return $response;
  }

  /**
   * @param array $data
   */
  public function patch($data) {
    if (!isset($data['body']['ids']) || empty($data['body']['ids'])) {
      return new ResourceResponse("Field 'ids' is missing or empty. Nothing to update", 200);
    }

    $ids = $data['body']['ids'];
    try {
      // Load all notifications.
      $notifications = \Drupal::entityTypeManager()
        ->getStorage('opigno_notification')
        ->loadMultiple($ids);
      // Update all notifications.
      $notifications = array_map(function ($notification) {
        $notification->set('has_read', 1);
        $notification->save();
        return $notification;
      }, $notifications);

      return new ResourceResponse($notifications, 200);
    } catch (\Exception $e) {
      return new ResourceResponse('Notifications can not be updated.', 400);
    }

  }

}
