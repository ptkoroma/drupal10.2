<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource\Messaging;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\opigno_messaging\Services\OpignoMessageThread;
use Drupal\opigno_mobile_app\PrivateMessagesHandler;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RenderContext;

/**
 * Provides a resource to for PrivateMessageThread entity.
 *
 * @RestResource(
 *   id = "private_message_threads_resource",
 *   label = @Translation("Opigno: User messages threads"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/private_message_threads",
 *   }
 * )
 */
class PrivateMessageThreadsRestResource extends ResourceBase {

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
   * Get all dialogs for current User.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get() {

    // Wrap fully data to avoid to early rendering.
    $response_data = \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () {
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

      // Find out how many dialogs do we have.
      $threads = [];
      $pm_manager = \Drupal::service('opigno_messaging.manager');
      if ($pm_manager instanceof OpignoMessageThread) {
        $threads = $pm_manager->getUserThreads();
      }

      $threads_count = count($threads);
      $position = $limit * ($page + 1);
      if ($threads_count > $position) {
        $next_page_query = $request_query_array;
        $next_page_query['page'] = $page + 1;
        $response_data['next_page'] = Url::createFromRequest($request)
          ->setOption('query', $next_page_query)
          ->toString(TRUE)
          ->getGeneratedUrl();
      }

      // Find dialogs.
        $query = \Drupal::entityQuery('private_message_thread')
          ->condition('members', $this->currentUser->id())
          ->sort('updated', strtoupper($order_by))
          ->pager($limit);
        $result = $query->execute();
        $dialogs = \Drupal::entityTypeManager()
          ->getStorage('private_message_thread')
          ->loadMultiple($result);

      /** @var \Drupal\private_message\Entity\PrivateMessageThread $dialog */
      foreach ($dialogs as $dialog) {
        // Get last message.
        $messages = $dialog->getMessages();
        usort($messages, function ($a, $b) {
          /** @var \Drupal\private_message\Entity\PrivateMessage $a */
          /** @var \Drupal\private_message\Entity\PrivateMessage $b */
          return (int) ($a->getCreatedTime() < $b->getCreatedTime());
        });
        $last_message = reset($messages);
        // Get info about members.
        $members = $dialog->getMembers();
        $members_info = array_map(function ($member) {
          return [
            'uid' => $member->id(),
            'name' => $member->getAccountName(),
            'user_picture' => opigno_mobile_app_get_user_picture($member),
          ];
        }, $members);
        // Get unread messages.
        $unread_messages = PrivateMessagesHandler::getUnreadMessagesForThread($dialog, $this->currentUser);
        $response_data['items'][] = [
          'id' => $dialog->id(),
          'subject' => $dialog->field_pm_subject->value,
          'members' => $members_info,
          'updated' => $dialog->getUpdatedTime(),
          'last_access_time' => $dialog->getLastAccessTimestamp($this->currentUser),
          'messages' => count($dialog->getMessages()),
          'unread_messages' => count($unread_messages),
          'last_uid' => $last_message ? $last_message->getOwnerId() : '',
        ];
      }
      return $response_data;
    });

    $response = new ResourceResponse($response_data, 200);
    // Disable caching.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    return $response;
  }

}
