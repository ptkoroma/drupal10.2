<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource\Messaging;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides a resource to get last Private message per Threads for current User.
 *
 * @RestResource(
 *   id = "private_message_last_for_threads_resource",
 *   label = @Translation("Last Private Message for Threads"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/private_messages/last-message",
 *   }
 * )
 */
class PrivateMessageLastForThreadsRestResource extends ResourceBase {

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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    Connection $database,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->database = $database;
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
      $container->get('database'),
      $container->get('current_user')
    );
  }

  /**
   * Get Private messages for current User.
   *
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

    // Get only last messages.
    $all_messages = $this->getPrivateMessagesAndThreadIds($this->currentUser);
    $messages_and_threads = array_unique($all_messages);

    // Find out how many messages do we have.
    $messages_count = count($messages_and_threads);
    $position = $limit * ($page + 1);
    if ($messages_count > $position) {
      $next_page_query = $request_query_array;
      $next_page_query['page'] = $page + 1;
      $response_data['next_page'] = Url::createFromRequest($request)
        ->setOption('query', $next_page_query)
        ->toString(TRUE)
        ->getGeneratedUrl();
    }

    // Load messages entities.
    if (!empty($messages_and_threads)) {
      $messages_ids = array_keys($messages_and_threads);
      $query = \Drupal::entityQuery('private_message')
        ->condition('id', $messages_ids, 'IN')
        ->sort('created', strtoupper($this->order_by))
        ->pager($limit);
      $result = $query->execute();
      try {
        $messages = \Drupal::entityTypeManager()
          ->getStorage('private_message')
          ->loadMultiple($result);
      }
      catch (\Exception $e) {
        throw new HttpException(500, 'Internal Server Error', $e);
      }

    }
    /* @var  \Drupal\private_message\Entity\PrivateMessage $message */
    foreach ($messages as $message) {
      $response_data['items'][] = [
        'thread_id' => $messages_and_threads[$message->id()],
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

    $response = new ResourceResponse($response_data, 200);
    // Disable caching.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    return $response;
  }

  /**
   * Get private messages and tread ids for current user.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *
   * @return array $result
   *   Array with messages and thread ids (keyed by messages ids)
   */
  private function getPrivateMessagesAndThreadIds(AccountProxyInterface $account) {
    // @todo: filter deleted messages.
    $query = $this->database->select('private_message_thread__members', 'm');
    $query->condition('m.members_target_id', $account->id(), '=');
    $query->addJoin(
      'left',
      'private_message_thread__private_messages',
      'pmt',
      'm.entity_id = pmt.entity_id'
    );
    $query->addJoin(
      'left',
      'private_messages',
      'pm',
      'pmt.private_messages_target_id = pm.id'
    );
    $query->orderBy('pm.created', 'DESC');
    // Message ids.
    $query->fields('pm', ['id']);
    // Thread ids.
    $query->fields('m', ['entity_id']);

    $result = $query->execute()->fetchAllKeyed(0, 1);

    return $result ?: [];
  }

}
