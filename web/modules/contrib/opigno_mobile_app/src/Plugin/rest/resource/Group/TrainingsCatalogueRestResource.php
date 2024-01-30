<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource\Group;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\RenderContext;
use Drupal\group\Entity\Group;
use Drupal\opigno_mobile_app\GroupOMATrait;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\taxonomy\Entity\Term;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\opigno_learning_path\Progress;

/**
 * Provides a resource to get trainings catalogue.
 *
 * @RestResource(
 *   id = "trainings_catalogue_resource",
 *   label = @Translation("Trainings Catalogue"),
 *   description = @Translation("Get available trainings"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/trainings/catalogue",
 *   }
 * )
 */
class TrainingsCatalogueRestResource extends ResourceBase {

  use GroupOMATrait;

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
   * Progress bar service.
   *
   * @var \Drupal\opigno_learning_path\Progress
   */
  protected $progress;

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
    EntityTypeManagerInterface $entity_type_manager,
    Progress $progress) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->database = $database;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->progress = $progress;
    
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
      $container->get('entity_type.manager'),
      $container->get('opigno_learning_path.progress')
    );
  }


  /**
   *  Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get() {
    // Array with response data.
    // Wrap fully data to avaoid to early rendering.
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
      // Get sort parameter by title.
      $title_sort = $request_query->get('title_sort') && in_array(strtoupper($request_query->get('title_sort')), ['ASC', 'DESC'])
        ? $request_query->get('title_sort'): NULL;
      // Get sort parameter by date created.
      $created_sort = $request_query->get('created_sort') && in_array(strtoupper($request_query->get('created_sort')), ['ASC', 'DESC'])
        ? $request_query->get('created_sort') : 'DESC';
      // Get category.
      $category = is_numeric($request_query->get('category')) ? $request_query->get('category') : NULL;
      // Get filter by membership.
      $membership = $request_query->get('membership')
        ? filter_var($request_query->get('membership'), FILTER_VALIDATE_BOOLEAN) : FALSE;
  
      // Get training ids.
      $training_ids = $this->getFilteredTrainings($membership, $category, $this->currentUser);
  
      $trainings_count = count($training_ids);
      $position = $limit * ($page + 1);
      // Build link to next page.
      if ($trainings_count > $position) {
        $next_page_query = $request_query_array;
        $next_page_query['page'] = $page + 1;
        $response_data['next_page'] = Url::createFromRequest($request)
          ->setOption('query', $next_page_query)
          ->toString(TRUE)
          ->getGeneratedUrl();
      }
  
      // Find notifications.
      if (!empty($training_ids)) {
        $query = \Drupal::entityQuery('group')
          ->condition('id', $training_ids, 'IN')
          ->pager($limit);
        // Set sorting criteria.
        if ($title_sort) {
          $query->sort('label', strtoupper($title_sort));
        }
        else {
          // Default sorting.
          $query->sort('created', strtoupper($created_sort));
        }
        $result =  $query->execute();
        $trainings = $this->entityTypeManager
          ->getStorage('group')
          ->loadMultiple($result);
      }
  
      if (isset($training_ids) && empty($trainings)) {
        $response = new ResourceResponse($response_data, Response::HTTP_NO_CONTENT);
        // Disable caching.
        $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
        return $response;
      }
  
      // Get messages for current page.
      /* @var \Drupal\group\Entity\Group $training */
      foreach ($trainings as $training) {
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
        if ($member = $training->getMember($this->currentUser)) {
          $membership = $member->getGroupContent();
          $registration = $membership->getCreatedTime();
        }
        // Get start link.
        $start_link = $this->getTrainingStartLink($training, $this->currentUser);
  
        // Build response data.
        $response_data['items'][] = [
          'id' => $training->id(),
          'title' => $training->label(),
          'description' => $training->get('field_learning_path_description')->value,
          'category' => $training_category,
          'image' => $this->getTrainingImageInfo($training),
          'progress' => $this->getTrainingProgress($training),
          'subscription' => isset($registration) ? $registration : '',
          'start_link' => $start_link,
        ];
  
      }
      return $response_data;
    });

    $response = new ResourceResponse($response_data, 200);
    // Disable caching.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    return $response;
  }

  /**
   * Get training progress for current user.
   *
   * @param \Drupal\group\Entity\Group $training
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getTrainingProgress(Group $training) {
    return $this->progress->getProgressRound($training->id(), $this->currentUser->id());
  }

  /**
   * Get trainings ids filtered by membership and category.
   *
   * @param string $membership
   * @param string $category
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *
   * @return array
   *  An array with trainings ids.
   */
  private function getFilteredTrainings($membership, $category, AccountProxyInterface $account) {
    // Get trainings ids.
    $query = $this->database->select('groups_field_data', 'g');
    $query->condition('g.type', 'learning_path');
    $query->condition('gfp.field_learning_path_published_value', 1);
    $query->leftJoin(
      'group__field_learning_path_published',
      'gfp',
      'g.id = gfp.entity_id'
    );
    $query->leftJoin(
      'group__field_learning_path_visibility',
      'gfv',
      'g.id = gfv.entity_id'
    );
    // Set filtering by membership.
    if ($membership) {
      $query->addJoin(
        'left',
        'group_content_field_data',
        'gcfd',
        'g.id = gcfd.gid'
      );
      $query->condition('gcfd.type', 'learning_path-group_membership');
      $query->condition('gcfd.entity_id', $account->id());
    }
    // Set filtering by category.
    if ($category) {
      $query->addJoin(
        'left',
        'group__field_learning_path_category',
        'gfc',
        'g.id = gfc.entity_id'
      );
      $query->condition('gfc.field_learning_path_category_target_id', $category);
    }
    $query->fields('gfv', ['entity_id', 'field_learning_path_visibility_value']);
    $all_trainings = $query->execute()->fetchAllAssoc('entity_id');

    // Get private trainings where user is a member.
    $private_trainings = $this->getPrivateTrainingsIds($account);

    // Unset all private trainings where user is not a member.
    if (is_array($all_trainings) && is_array($private_trainings)) {
      $all_trainings = array_filter($all_trainings, function ($item) use ($private_trainings) {
        if ($item->field_learning_path_visibility_value == 'private') {
          return in_array($item->entity_id, $private_trainings);
        }
        else {
          return TRUE;
        }
      });
    }
    return $all_trainings ? array_keys($all_trainings) : [];

  }

}
