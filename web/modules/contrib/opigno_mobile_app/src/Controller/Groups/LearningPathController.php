<?php

namespace Drupal\opigno_mobile_app\Controller\Groups;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\opigno_mobile_app\GroupOMATrait;
use Drupal\taxonomy\Entity\Term;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Drupal\opigno_learning_path\Progress;

/**
 * Class LearningPathController.
 */
class LearningPathController extends ControllerBase {

  use GroupOMATrait;

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
   * Constructs a new UserAuthenticationController object.
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
  public function __construct(Serializer $serializer,
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
   * Add new message to Private Message Thread for current user.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with created message info.
   */
  public function getCategories() {
    $response_data = [];

    $vocabulary = 'learning_path_category';
    // Get terms ids.
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocabulary)
      ->condition('status', TRUE)
      ->sort('weight');
    $tids = $query->execute();

    if (empty($tids)) {
      return new JsonResponse($response_data, Response::HTTP_NO_CONTENT);
    }

    // Load terms.
    $terms = Term::loadMultiple($tids);
    /* @var \Drupal\taxonomy\Entity\Term $term */
    foreach ($terms as $term) {
      $response_data['items'][] = [
        'id' => $term->id(),
        'name' => $term->getName(),
        'description' => $term->getDescription(),
        'weight' => $term->getWeight(),
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get latest active trainings for current user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with trainings info.
   */
  public function getLatestActiveTrainings() {
    $response_data = [];
    $account = \Drupal::currentUser();
    // Default number of response items.
    $default_limit = 3;
    // Get "limit" parameter from request.
    $request = \Drupal::request();
    $limit = $request->query->get('limit') ?: $default_limit;
    // Get trainings ids.
    $query = $this->database->select('opigno_latest_group_activity', 'olga');
    $query->condition('olga.uid', $account->id());
    $query->orderBy('olga.timestamp', 'DESC');
    $query->fields('olga', ['training']);
    $result = $query->execute()->fetchAllAssoc('training');

    if (empty($result)) {
      return new JsonResponse($response_data, Response::HTTP_NO_CONTENT);
    }
    $result = array_keys($result);
    $result = array_slice($result, 0, $limit);

    // Load trainings.
    $latest_trainings = $this->entityTypeManager
      ->getStorage('group')
      ->loadMultiple($result);

    // Build response data.
    foreach ($latest_trainings as $training) {
      // Get info about training category.
      $training_category = [];
      $tid = $training->get('field_learning_path_category')->target_id;
      if ($tid && $term = Term::load($tid)) {
        $training_category = [
          'id' => $term->id(),
          'title' => $term->getName(),
        ];
      }
      // Get membership.
      /** @var \Drupal\group\Entity\GroupContent $membership */
      if ($member = $training->getMember($account)) {
        $membership = $member->getGroupContent();
        $registration = $membership->getCreatedTime();
      }
      // Get training progress.
      $group_progress = $this->progress->getProgressRound($training->id(), $account->id());
      $response_data['items'][] = [
        'id' => $training->id(),
        'title' => $training->label(),
        'description' => $training->get('field_learning_path_description')->value,
        'category' => $training_category,
        'image' => $this->getTrainingImageInfo($training),
        'progress' => $group_progress,
        'subscription' => isset($registration) ? $registration : '',
        'start_link' => $this->getTrainingStartLink($training, $this->currentUser()),
      ];
    }

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get groups info by User membership.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with trainings info.
   */
  public function getGroupsInfoByUserMembership() {
    $response_data = [];
    // Get "group_type" parameter from request.
    $request = \Drupal::request();
    $group_type = $request->query->get('group_type');
    // If group_type parameter is missing.
    if (!$group_type) {
      // @todo: change usage HTTP_BAD_REQUEST
      $response_data['message'] = "Request parameter 'group_type' is missing";
      return new JsonResponse($response_data, Response::HTTP_BAD_REQUEST);
    }
    // If group_type parameter is not valid.
    if (!in_array($group_type, ['learning_path', 'opigno_class'])) {
      $response_data['message'] = "Request parameter 'group_type' is wrong. Group type {$group_type} does not exist";
      return new JsonResponse($response_data, Response::HTTP_BAD_REQUEST);
    }

    // Find groups ids by user membership.
    $query = $this->database->select('groups_field_data', 'g');
    $query->leftJoin(
      'group_content_field_data',
      'gcfd',
      'gcfd.gid = g.id'
    );
    $query->condition('g.type', $group_type);
    $query->condition('gcfd.type', $group_type . '-group_membership');
    $query->condition('gcfd.entity_id', $this->currentUser()->id());
    $query->fields('g', ['id', 'type', 'label']);
    $results = $query->execute()->fetchAll();
    // Build response data.
    foreach ($results as $result) {
      $response_data['items'][] = [
        'id' => $result->id,
        'type' => $result->type,
        'label' => $result->label,
      ];
    }
    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get all trainings for current user.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object with trainings info.
   */
  public function getAllTrainings() {
    $response_data = [];
    $user_roles = $this->currentUser()->getRoles();
    $allowed_roles = ['administrator', 'content_manager', 'user_manager'];
    // Get trainings for users with global roles without any restrictions.
    if ((array_diff($allowed_roles, $user_roles)) != $allowed_roles) {
      $trainings = $this->entityTypeManager->getStorage('group')->loadByProperties([
        'type' => 'learning_path'
      ]);
    }
    else {
      // Get trainings where current user is a member.
      $query = $this->database->select('groups_field_data', 'g');
      $query->condition('g.type', 'learning_path');
      $query->condition('gfp.field_learning_path_published_value', 1);
      $query->leftJoin(
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
      $query->condition('gcfd.type', 'learning_path-group_membership');
      $query->condition('gcfd.entity_id', $this->currentUser()->id());
      $query->fields('g', ['id']);
      $result = $query->execute()->fetchAllAssoc('id');

      $tids = $result ? array_keys($result) : [];
      $trainings = $this->entityTypeManager->getStorage('group')->loadMultiple($tids);
    }

    if (empty($trainings)) {
      return new JsonResponse($response_data, Response::HTTP_NO_CONTENT);
    }
    // Sort trainings alphabetically.
    usort($trainings, function ($a, $b) {
      return (int) ( $a->label() > $b->label());
    });
    // Build response data.
    foreach ($trainings as $training) {
      $response_data['items'][] = [
        'id' => $training->id(),
        'type' => $training->getGroupType()->id(),
        'label' => $training->label(),
      ];
    }
    return new JsonResponse($response_data, Response::HTTP_OK);
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
