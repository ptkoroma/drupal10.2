<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\opigno_learning_path\Progress;
use Drupal\Core\Render\RenderContext;

/**
 * Provides a resource to get achievements info.
 *
 * @RestResource(
 *   id = "achievements_resource",
 *   label = @Translation("Achievements"),
 *   description = @Translation("Achievements for current user"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/achievements",
 *   }
 * )
 */
class AchievementsRestResource extends ResourceBase {

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
   *  Get achievements for current user.
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
  
      // Get training ids.
      $training_ids = $this->getTrainingsIds();
  
      $trainings_count = count($training_ids);
      $position = $limit * ($page + 1);
      $offset = $position - $limit;
      // Get training per page.
      $items_per_page = array_slice($training_ids, $offset, $limit);
      // Build link to next page.
      if (($trainings_count > $position) && $items_per_page) {
        $next_page_query = $request_query_array;
        $next_page_query['page'] = $page + 1;
        $response_data['next_page'] = Url::createFromRequest($request)
          ->setOption('query', $next_page_query)
          ->toString(TRUE)
          ->getGeneratedUrl();
      }
  
      // Load trainings.
      $trainings = $this->entityTypeManager
        ->getStorage('group')
        ->loadMultiple($items_per_page);
  
      if (empty($trainings)) {
        $response =  new ResourceResponse($response_data, Response::HTTP_NO_CONTENT);
        // Disable caching.
        $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
        return $response;
      }
  
      // Build response data for each training.
      /* @var \Drupal\group\Entity\Group $training */
      foreach ($trainings as $training) {
        // Get time when user subscribe to a training.
        /** @var \Drupal\group\Entity\GroupContent $member */
        $member = $training->getMember($this->currentUser)->getGroupContent();
        $registration = $member->getCreatedTime();
        // Get time when user finished the training.
        $validation = opigno_learning_path_completed_on($training->id(), $this->currentUser->id(), TRUE);
        // Get time spent.
        /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
        $date_formatter = \Drupal::service('date.formatter');
        $time_spent = opigno_learning_path_get_time_spent($training->id(), $this->currentUser->id());
        // Get progress.
        $progress = $this->progress->getProgressRound($training->id(), $this->currentUser->id());
        // Get progress status.
        $progress_status = $this->getTrainingProgressStatus($training, $this->currentUser);
        // Get score.
        $score = round(opigno_learning_path_get_score($training->id(), $this->currentUser->id()));
        // Get certificate link.
        $cert_url = !$training->get('field_certificate')->isEmpty() && $progress_status == 'passed'
          ? '/certificate/group/' . $training->id() . '/pdf' : '';
  
        // Build response data.
        $response_data['items'][] = [
          'id' => $training->id(),
          'title' => $training->label(),
          'progress' => $progress,
          'progress_status' => $progress_status,
          'score' => $score,
          'subscription' => $registration,
          'validation_date' => $validation > 0 ? $validation : '',
          'time_spent' => $date_formatter->formatInterval($time_spent),
          'certificate' => $cert_url,
  
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
   * Get training progress status for current user.
   *
   * @param \Drupal\group\Entity\Group $training
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getTrainingProgressStatus(Group $training, AccountProxyInterface $account) {
    $progress = $this->progress->getProgressRound($training->id(), $account->id());
    $result = $this->database
      ->select('opigno_learning_path_achievements', 'a')
      ->fields('a', ['status'])
      ->condition('uid', $account->id())
      ->condition('gid', $training->id())
      ->execute()
      ->fetchField();

    if ($result !== FALSE) {
      // Use cached result.
      $is_passed = $result === 'completed';
    }
    else {
      // Check the actual data.
      $is_passed = opigno_learning_path_is_passed($training, $account->id());
    }

    if ($is_passed) {
      $progress_status = t('passed');
    }
    elseif ($progress == 100 && !$is_passed) {
      $progress_status = t('failed');
    }
    else {
      $progress_status = t('pending');
    }
    return $progress_status;
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

}
