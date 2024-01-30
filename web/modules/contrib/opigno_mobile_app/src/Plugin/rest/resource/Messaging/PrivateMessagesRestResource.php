<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource\Messaging;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "private_messages_resource",
 *   label = @Translation("Dashboard: Private Messages"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/private_messages",
 *   }
 * )
 */
class PrivateMessagesRestResource extends ResourceBase {

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
   *
   * @var int
   */
  protected $limit = 10;

  /**
   * Default ordering per request.
   *
   * @var string
   */
  protected $orderBy = 'DESC';

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
   *   Rest response interface.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
    $order_by = $request_query->get('order_by') && in_array(strtoupper($request_query->get('order_by')), ['ASC', 'DESC'])
      ? $request_query->get('order_by') : $this->orderBy;
    // Before date creation.
    $before = $request_query->get('before') ? intval($request_query->get('before')) : 0;
    // After date creation.
    $after = $request_query->get('after') ? intval($request_query->get('after')) : 0;

    // Find out how many messages do we have.
    $messages_and_threads = $this->getPrivateMessagesAndThreadIds($this->currentUser, $before, $after);
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

    // Find messages.
    if (!empty($messages_and_threads)) {
      $messages_ids = array_keys($messages_and_threads);
      $query = \Drupal::entityQuery('private_message')
        ->condition('id', $messages_ids, 'IN')
        ->sort('created', strtoupper($order_by))
        ->pager($limit)
        ->accessCheck(false);

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
      $owner = $message->getOwner();
      if (!($owner instanceof EntityInterface)) {
        $owner = User::create([
          'name' => 'inactive user',
        ]);
      }
      $response_data['items'][] = [
        'thread_id' => $messages_and_threads[$message->id()]->entity_id,
        'id' => $message->id(),
        'owner' => [
          'uid' => $message->getOwnerId(),
          'name' => $owner->getAccountName(),
          'user_picture' => opigno_mobile_app_get_user_picture($owner),
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
   *   User account interface.
   * @param int $before
   *   Unix time value less a created date.
   * @param int $after
   *   Unix time value above a created date.
   *
   * @return array
   *   Array with messages and thread ids (keyed by messages ids)
   */
  private function getPrivateMessagesAndThreadIds(AccountProxyInterface $account, $before, $after) {
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
    $query->condition('pm.owner', $account->id(), '!=');
    if ($before) {
      $query->condition('pm.created', $before, '<');
    }
    if ($after) {
      $query->condition('pm.created', $after, '>');
    }
    // Messages ids.
    $query->fields('pm', ['id']);
    // Thread ids.
    $query->fields('m', ['entity_id']);

    $result = $query->execute()->fetchAllAssoc('id');

    return $result ?: [];
  }

}
