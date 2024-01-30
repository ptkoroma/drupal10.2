<?php

namespace Drupal\opigno_mobile_app\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\GroupMembershipLoader;
use Drupal\opigno_ilt\Entity\ILT;
use Drupal\opigno_moxtra\Entity\Meeting;
use Drupal\opigno_moxtra\MoxtraServiceInterface;
use Drupal\opigno_moxtra\MoxtraConnector;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Class OpignoMoxtraController.
 */
class OpignoMoxtraController extends ControllerBase {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The group membership loader service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $groupMembershipLoader;

  /**
   * Opigno service.
   *
   * @var \Drupal\opigno_moxtra\MoxtraConnector
   */
  protected $moxtraConnector;

  /**
   * Moxtra service.
   *
   * @var \Drupal\opigno_moxtra\MoxtraServiceInterface
   */
  protected $moxtraService;

  /**
   * Constructs a new UsersController object.
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
   *   The entity type manager service.
   * @param \Drupal\group\GroupMembershipLoader $groupMembershipLoader
   *   The group membership loader.
   * @param \Drupal\opigno_moxtra\MoxtraConnector $moxtra_connector
   *   Opigno API service.
   * @param \Drupal\opigno_moxtra\MoxtraServiceInterface $moxtra_service
   *   Moxtra API service.
   */
  public function __construct(
    Serializer $serializer,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    GroupMembershipLoader $groupMembershipLoader,
    MoxtraConnector $moxtra_connector,
    MoxtraServiceInterface $moxtra_service) {
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->groupMembershipLoader = $groupMembershipLoader;
    $this->moxtraConnector = $moxtra_connector;
    $this->moxtraService = $moxtra_service;
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
      $container->get('group.membership_loader'),
      $container->get('opigno_moxtra.opigno_api'),
      $container->get('opigno_moxtra.moxtra_api')
    );
  }

  /**
   * Get Moxtra meetings for current user.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMeetings() {
    $response_data = [
      'items' => [],
      'next_page' => FALSE,
    ];

    // Check if Moxtra is enabled.
    if (!$this->checkMoxtraEnabled()) {
      $response_data['message'] = 'Moxtra is disabled';
      return new JsonResponse($response_data, Response::HTTP_OK);
    }

    $user = $this->currentUser();

    // Get request parameters.
    $request = \Drupal::request();
    $request_query = $request->query;
    $request_query_array = $request_query->all();

    // Get "limit" parameter from request.
    $limit = $request_query->get('limit') ?: 10;
    // Get "page" parameter from request.
    $page = $request_query->get('page') ?: 0;

    // Get Moxtra Meetings.
    $mids = $this->getMeetingIdsByTrainingsMembership($user);
    if (empty($mids)) {
      return new JsonResponse($response_data, Response::HTTP_OK);
    }

    // Load Moxtra Meetings.
    $meetings = $this->entityTypeManager->getStorage('opigno_moxtra_meeting')->loadMultiple($mids);
    // Check user access to a meeting.
    $meetings = array_filter($meetings, function ($meeting) use ($user) {
      $access = $this->entityTypeManager
        ->getAccessControlHandler($meeting->getEntityTypeId())
        ->access($meeting, 'view', $user, TRUE);
      return $access->isAllowed();
    });

    if (!empty($meetings)) {
      $count = count($meetings);
      $position = $limit * ($page + 1);
      $offset = $position - $limit;
      // Get number of items per page.
      $meetings = array_slice($meetings, $offset, $limit);

      // Build link to next page.
      if ($count > $position) {
        $next_page_query = $request_query_array;
        $next_page_query['page'] = $page + 1;
        $response_data['next_page'] = Url::createFromRequest($request)
          ->setOption('query', $next_page_query)
          ->toString(TRUE)
          ->getGeneratedUrl();
      }
    }

    foreach ($meetings as $meeting) {
      $storage_format = DrupalDateTime::FORMAT;
      // Get start date.
      $start_date = DrupalDateTime::createFromFormat($storage_format, $meeting->getStartDate());
      // Get end date.
      $end_date = DrupalDateTime::createFromFormat($storage_format, $meeting->getStartDate());
      // Get link to a meeting.
      $url = Url::fromRoute('opigno_moxtra.meeting', ['opigno_moxtra_meeting' => $meeting->id()]);
      $link = $request->getSchemeAndHttpHost() . '/' . $url->getInternalPath();

      $response_data['items'][] = [
        'id' => $meeting->id(),
        'title' => $meeting->get('title')->value,
        'owner' => [
          'uid' => $meeting->getOwnerId(),
          'name' => $meeting->getOwner()->getAccountName(),
          'user_picture' => opigno_mobile_app_get_user_picture($meeting->getOwner()),
        ],
        'start_date' => $start_date->getTimestamp(),
        'end_date' => $end_date->getTimestamp(),
        'link' => $link,
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Helper function to get meetings ids by trainings where user is a member.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *
   * @return array $meetings
   *  Array with meetings ids.
   */
  private function getMeetingIdsByTrainingsMembership(AccountProxyInterface $account) {
    $current_time = DrupalDateTime::createFromTimestamp(\Drupal::time()->getCurrentTime())
      ->format(DrupalDateTime::FORMAT);

    $database = \Drupal::database();
    $query = $database->select('groups_field_data', 'g');
    $query->condition('g.type', 'learning_path');
    $query->condition('gcfd.type', 'learning_path-group_membership');
    $query->condition('gcfd.entity_id', $account->id());
    // Get only future meetings.
    $query->condition('omm.date__end_value', $current_time, '>=');
    $query->leftJoin(
      'group_content_field_data',
      'gcfd',
      'g.id = gcfd.gid'
    );
    $query->leftJoin(
      'opigno_moxtra_meeting',
      'omm',
      'g.id = omm.training'
    );
    $query->orderBy('omm.date__value');
    $query->fields('omm', ['id']);
    $meetings = $query->execute()->fetchAllAssoc('id');

    return $meetings ? array_keys($meetings) : [];
  }

  /**
   * Testing Moxtra on mobile app.
   * @todo: remove this method.
   */
  public function getMoxtraCredentials() {
    $config = $this->config('opigno_moxtra.settings');
    $client_id = $config->get('client_id');
    $org_id = $config->get('org_id');

    $user = $this->currentUser();
    $access_token = $this->moxtraConnector->getToken($user->id());
    $responce_data = [
      'cliend_id' => $client_id,
      'org_id' => $org_id,
      'access_token' => $access_token
    ];
    return new JsonResponse($responce_data, Response::HTTP_OK);
  }

  /**
   * Helper function to check if Moxtra is available.
   * @return boolean
   */
  private function checkMoxtraEnabled() {
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('opigno_moxtra')){
      return _opigno_moxtra_is_active();
    }

    return FALSE;
  }
}
