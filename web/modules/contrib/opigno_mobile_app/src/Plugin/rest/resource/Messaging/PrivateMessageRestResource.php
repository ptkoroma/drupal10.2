<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource\Messaging;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageThread;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get messages from one Tread.
 *
 * @RestResource(
 *   id = "private_message_resource",
 *   label = @Translation("Opigno: Thread messages"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/private_message/{private_message_thread}",
 *   }
 * )
 */
class PrivateMessageRestResource extends ResourceBase {

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
   * Constructs a new PrivateMessageRestResource object.
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
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->database = $database;
    $this->currentUser = $current_user;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get all messages for one Tread.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThread $private_message_thread
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function get($private_message_thread) {
    $response_data = [
      'items' => [],
      'next_page' => FALSE,
    ];
    // Check if user has access to a thread.
    $private_message_thread = PrivateMessageThread::load($private_message_thread);
    if (!$private_message_thread->isMember($this->currentUser->id())) {
      throw new AccessDeniedHttpException();
    }

    // Get request parameters.
    $request = \Drupal::request();
    $request_query = $request->query;
    $request_query_array = $request_query->all();
    // How many items should be returned.
    $limit = $request_query->get('limit') ?: $this->limit;
    // Part of items.
    $page = $request_query->get('page') ?: 0;
    // How to order items.
    $order_by = $request_query->get('order_by') && in_array(strtoupper($request_query->get('order_by')), ['ASC', 'DESC'])
      ? $request_query->get('order_by') : $this->order_by;
    // Get 'before' filter.
    $before = $request_query->get('before') ?: 0;
    // Get 'after' filter.
    $after = $request_query->get('after') ?: 0;

    // Find out how many messages do we have.
    $mids = $this->getPrivateMessagesIdsByThread($private_message_thread, $order_by, $before, $after);
    // Load messages.
    $messages = $this->entityTypeManager->getStorage('private_message')->loadMultiple($mids);

    $messages_count = count($messages);
    $position = $limit * ($page + 1);
    $offset = $position - $limit;
    // Get messages for current page.
    $items_per_page = array_slice($messages, $offset, $limit);
    // Build link to next page.
    if (($messages_count > $position) && $items_per_page) {
      $next_page_query = $request_query_array;
      $next_page_query['page'] = $page + 1;
      $response_data['next_page'] = Url::createFromRequest($request)
        ->setOption('query', $next_page_query)
        ->toString(TRUE)
        ->getGeneratedUrl();
    }

    if (empty($items_per_page)) {
      $response = new ResourceResponse($response_data, Response::HTTP_NO_CONTENT);
      // Disable caching.
      $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
      return $response;
    }

    /* @var \Drupal\private_message\Entity\PrivateMessage $message */
    foreach ($items_per_page as $message) {
      if ($message->getOwner()) {
        $response_data['items'][] = [
          'thread_id' => $private_message_thread->id(),
          'id' => $message->id(),
          'owner' => [
            'uid' => $message->getOwnerId(),
            'name' => $message->getOwner()->getAccountName(),
            'user_picture' => opigno_mobile_app_get_user_picture($message->getOwner()),
          ],
          'message' => $message->getMessage(),
          'created' => $message->getCreatedTime(),
        ];
      }
    }

    $response = new ResourceResponse($response_data, Response::HTTP_OK);
    // Disable caching.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    return $response;
  }

  /**
   * Get messages ids from one Tread.
   * Sorted and filtered by added criteria.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThread $private_message_thread
   *   Tread entity.
   * @param string $order_by
   *   Order criteria.
   * @param int $before
   *   Timestamp to filter items by date creation.
   * @param int $after
   *   Timestamp to filter items by date creation.
   *
   * @return array
   */
  private function getPrivateMessagesIdsByThread(PrivateMessageThread $private_message_thread, $order_by='DESC', $before=0, $after=0) {
    $query = $this->database->select('private_message_threads', 'pmt');
    $query->addJoin(
      'left',
      'private_message_thread__private_messages',
      'pmtpm',
      'pmtpm.entity_id = pmt.id'
    );
    $query->addJoin(
      'left',
      'private_messages',
      'pm',
      'pmtpm.private_messages_target_id = pm.id'
    );
    $query->condition('pmt.id', $private_message_thread->id(), '=');
    if ($before) {
      $query->condition('pm.created', $before, '<=');
    }
    if ($after) {
      $query->condition('pm.created', $after, '>=');
    }
    $query->orderBy('pm.created', $order_by);
    $query->fields('pm', ['id']);
    $result = $query->execute()->fetchAllAssoc('id');
    $result = array_keys($result);

    return $result ?: [];
  }
}
