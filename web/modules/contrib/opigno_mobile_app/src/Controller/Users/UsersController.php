<?php

namespace Drupal\opigno_mobile_app\Controller\Users;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\Group;
use Drupal\opigno_module\OpignoModuleBadges;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Drupal\opigno_learning_path\Progress;

/**
 * Class UsersController.
 */
class UsersController extends ControllerBase {

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
   * Progress bar service.
   *
   * @var \Drupal\opigno_learning_path\Progress
   */
  protected $progress;

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
   */
  public function __construct(
                              Serializer $serializer,
                              array $serializer_formats,
                              LoggerInterface $logger,
                              EntityTypeManagerInterface $entity_type_manager,
                              Connection $database,
                              Progress $progress) {
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->progress = $progress;
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
      $container->get('opigno_learning_path.progress')
    );
  }


  /**
   * Get list of users.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with users info.
   */
  public function getUsersList() {
    $response_data = [];

    // Load users.
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
    if (empty($users)) {
      return new JsonResponse($response_data, Response::HTTP_NO_CONTENT);
    }
    // Unset anonymous.
    if (isset($users[0])) {
      unset($users[0]);
    }
    /* @var \Drupal\user\Entity\User $user */
    foreach ($users as $user) {
      // Get user picture.
      $user_picture_url = opigno_mobile_app_get_user_picture($user);

      $response_data['items'][] = [
        'uid' => $user->id(),
        'name' => $user->getAccountName(),
        'user_picture' => $user_picture_url,
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);
  }


  /**
   * Get list of users with groups ids where each user is a member.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with users and groups info.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUsersGroupsList() {
    $response_data = [];

    $current_user = \Drupal::currentUser();
    $show_all = $current_user->hasPermission('message anyone regardless of groups');

    // Get the groups, this allows to filter the available users.
    $classes = array_keys(opigno_messaging_get_groups('opigno_class'));
    $learning_paths = array_keys(opigno_messaging_get_groups('learning_path'));

    $users = opigno_messaging_get_all_recipients($show_all);

    $group_types = ['opigno_class', 'learning_path'];
    /* @var \Drupal\group\GroupMembershipLoaderInterface $membership_service */
    $membership_service = \Drupal::service('group.membership_loader');
    foreach ($users as $user) {
      /* @var \Drupal\group\GroupMembership[] $memberships */
      $memberships = $membership_service->loadByUser($user);
      $user_trainings = $user_classes = [];

      foreach ($memberships as $membership) {
        $group = $membership->getGroup();
        $type = $group->getGroupType()->id();

        if (!in_array($type, $group_types)) {
          continue;
        }

        $group_id = $group->id();

        if ($type == 'opigno_class' && in_array($group_id, $classes)) {
          $user_classes[] = $group_id;
        }

        if ($type == 'learning_path' && in_array($group_id, $learning_paths)) {
          $user_trainings[] = $group_id;
        }
      }

      $response_data['items'][] = [
        'uid' => $user->id(),
        'name' => $user->getAccountName(),
        'trainings' => $user_trainings,
        'classes' => $user_classes,
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);

    // Legacy code.
    // Get groups info where current user is a member.
    $query = $this->database->select('groups_field_data', 'g');
    $query->addJoin(
      'left',
      'group_content_field_data',
      'gcfd',
      'g.id = gcfd.gid'
    );
    $query->condition('g.type', ['learning_path', 'opigno_class'], 'IN');
    $query->condition('gcfd.type', ['learning_path-group_membership', 'opigno_class-group_membership'], 'IN');
    $query->condition('gcfd.entity_id', $this->currentUser()->id());
    $query->orderBy('gcfd.created', 'DESC');
    $query->fields('g');
    $groups = $query->execute()->fetchAllAssoc('id');
    $gids = $groups ? array_keys($groups) : [];
    if (empty($groups)) {
      return new JsonResponse($response_data, Response::HTTP_NO_CONTENT);
    }

    // Get memberships info.
    $query = $this->database->select('group_content_field_data', 'gcfd');
    $query->condition('gcfd.type', ['learning_path-group_membership', 'opigno_class-group_membership'], 'IN');
    $query->condition('gcfd.gid', $gids, 'IN');
    $query->fields('gcfd');
    $results = $query->execute()->fetchAll();
    // Make memberships info associated by user id and group id.
    if ($results) {
      $memberships = [];
      foreach ($results as $result) {
        $memberships[$result->entity_id][$result->gid] = $result;
      }
    }
    if (!isset($memberships)) {
      return new JsonResponse($response_data, Response::HTTP_NO_CONTENT);
    }


    // Unset memberships for anonymous users.
    if (isset($memberships[0])) {
      unset($memberships[0]);
    }

    // Build response data.
    foreach ($memberships as $uid => $membership) {
      /* @var \Drupal\user\Entity\User $user */
      $user = User::load($uid);
      // If user was deleted.
      if (is_null($user)) {
        continue;
      }
      // Build array with trainings and classes where user is a member.
      $groups_ids = [];
      foreach ($membership as $item) {
        $group_type = $groups[$item->gid]->type;
        $groups_ids[$group_type][] = intval($item->gid);
      }

      $response_data['items'][] = [
        'uid' => $user->id(),
        'name' => $user->getAccountName(),
        'trainings' => isset($groups_ids['learning_path']) ? $groups_ids['learning_path'] : [],
        'classes' => isset($groups_ids['opigno_class']) ? $groups_ids['opigno_class'] : [],
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get list of users which belong to a group(s).
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with users info.
   */
  public function getUsersByGroupsMembership() {
    $response_data = [];

    // Get request parameters.
    $request = \Drupal::request();
    $group_ids = $request->query->get('group_ids');

    $current_user = \Drupal::currentUser();
    $show_all = $current_user->hasPermission('message anyone regardless of groups');

    // Load users from groups.
    $groups_members = [];
    if (!empty($group_ids) && is_array($group_ids)) {
      foreach ($group_ids as $gid) {
        if (!is_numeric($gid)) continue;

        // New logic.
        $members = opigno_messaging_get_user_for_group($gid, $show_all);

        $groups_members[$gid] = $members;
      }
    }

    // Leave only users who is a member for each group.
    $users = reset($groups_members);
    foreach ($groups_members as $members) {
      $users = array_intersect_key($users, $members);
    }

    foreach ($users as $user) {
      // Get user picture.
      $user_picture_url = opigno_mobile_app_get_user_picture($user);

      $response_data['items'][] = [
        'uid' => $user->id(),
        'name' => $user->getAccountName(),
        'user_picture' => $user_picture_url,
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);

    // Legacy code.
    // Leave only users who is a member for each group.
    $users = reset($groups_members);
    foreach ($groups_members as $members) {
      $users = array_intersect_key($users, $members);
    }

    if (empty($users)) {
      return new JsonResponse($response_data, Response::HTTP_NO_CONTENT);
    }
    // Unset current user.
    if (isset($users[$this->currentUser()->id()])) {
      unset($users[$this->currentUser()->id()]);
    }
    /* @var \Drupal\user\Entity\User $user */
    foreach ($users as $user) {
      // Get user picture.
      $user_picture_url = opigno_mobile_app_get_user_picture($user);

      $response_data['items'][] = [
        'uid' => $user->id(),
        'name' => $user->getAccountName(),
        'user_picture' => $user_picture_url,
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get user profile info (specific for mobile app).
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with user info.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUserProfileInfo() {
//    // User entity.
    $user = User::load($this->currentUser()->id());
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    // Response data.
    $response_data = [
      'user' => [
        'name' => $user->getAccountName(),
        'email' => $user->getEmail(),
        'registration' => $user->getCreatedTime(),
        'user_picture' => opigno_mobile_app_get_user_picture($user),
      ],
      'data' => [
        'progress' => 0,
        'time_spent' => 0,
        'completion' => 0,
        'certificates_count' => 0,
        'badges_count' => 0,
        'trainings_count' => 0,
      ],
    ];
    // Get training ids where user is a member.
    $training_ids = $this->getTrainingsIds();
    if (empty($training_ids)) {
      return new JsonResponse($response_data, Response::HTTP_OK);
    }
    // Load trainings.
    $trainings = $this->entityTypeManager->getStorage('group')
      ->loadMultiple($training_ids);

    $user_achievements = [
      'progress' => 0,
      'time_spent' => 0,
      'completion' => 0,
      'certificates_count' => 0,
      'badges_count' => 0,
    ];
    foreach ($trainings as $training) {
      /* @var \Drupal\group\Entity\Group $training */
      $user_achievements['progress'] += $this->progress->getProgressRound($training->id(), $user->id());
      $user_achievements['time_spent'] += opigno_learning_path_get_time_spent($training->id(), $user->id());
      $is_passed = opigno_learning_path_is_passed($training, $user->id());
      if ($is_passed) {
        $user_achievements['completion'] += 1;
        if (!$training->get('field_certificate')->isEmpty()) {
          $user_achievements['certificates_count'] += 1;
        }
      }
      $user_achievements['badges_count'] += $this->getBadgesCountByTraining($training, $user);
    }

    // Build response data.
    $response_data['data'] = [
      'progress' => round($user_achievements['progress'] / count($trainings)),
      'time_spent' => $date_formatter->formatInterval($user_achievements['time_spent']),
      'completion' => round( 100 * ($user_achievements['completion'] / count($trainings))),
      'certificates_count' => $user_achievements['certificates_count'],
      'badges_count' => $user_achievements['badges_count'],
      'trainings_count' => count($trainings),
    ];
    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get trainings ids where current user is a member.
   *
   * @return array
   *  An array with trainings ids.
   */
  private function getTrainingsIds() {
    // Get trainings ids.
    $query = $this->database->select('groups_field_data', 'g');
    $query->addJoin(
      'left',
      'group__field_learning_path_published',
      'gfp',
      'g.id = gfp.entity_id'
    );
    $query->addJoin(
      'left',
      'group_content_field_data',
      'gcfd',
      'g.id = gcfd.gid'
    );
    $query->condition('g.type', 'learning_path');
    $query->condition('gfp.field_learning_path_published_value', 1);
    $query->condition('gcfd.type', 'learning_path-group_membership');
    $query->condition('gcfd.entity_id', $this->currentUser->id());
    $query->orderBy('gcfd.created', 'DESC');
    $query->fields('g', ['id']);

    $result = $query->execute()->fetchAllAssoc('id');

    return $result ? array_keys($result) : [];
  }

  private function getBadgesCountByTraining(Group $training, User $user) {
    $steps = opigno_learning_path_get_steps($training->id(), $user->id());
    $badges = array_map(function($step) use ($user, $training) {
      if ($step['typology'] === 'Module' || $step['typology'] === 'Course') {
        if ($step['completed on']) {
          $result = OpignoModuleBadges::opignoModuleGetBadges($user->id(), $training->id(), $step['typology'], $step['id']);
          if ($result && is_numeric($result)) {
            return intval($result);
          }
        }
      }
    }, $steps);
    return array_sum($badges);
  }
}
