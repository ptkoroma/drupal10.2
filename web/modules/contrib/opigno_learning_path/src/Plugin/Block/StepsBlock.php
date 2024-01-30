<?php

namespace Drupal\opigno_learning_path\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\OpignoGroupContentTypesManager;
use Drupal\opigno_learning_path\Controller\LearningPathStepsController;
use Drupal\opigno_learning_path\Traits\LearningPathAchievementTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides a 'article' block.
 *
 * @Block(
 *   id = "lp_steps_block",
 *   admin_label = @Translation("LP Steps block")
 * )
 */
class StepsBlock extends BlockBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  use LearningPathAchievementTrait;

  /**
   * Service "opigno_group_manager.content_types.manager" definition.
   *
   * @var \Drupal\opigno_group_manager\OpignoGroupContentTypesManager
   */
  protected $opignoGroupContentTypesManager;

  /**
   * The class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    OpignoGroupContentTypesManager $opigno_group_content_types_manager,
    ClassResolverInterface $class_resolver,
    EntityRepositoryInterface $entity_repository
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->opignoGroupContentTypesManager = $opigno_group_content_types_manager;
    $this->classResolver = $class_resolver;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('opigno_group_manager.content_types.manager'),
      $container->get('class_resolver'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'route',
      'opigno_current:group_id',
      'opigno_current:content_id',
      'opigno_current:activity_id',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupByRouteOrContext() {
    // By default a getCurrentGroupId gets a group by orute parameter.
    return Group::load($this->getCurrentGroupId());
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $group = $this->getGroupByRouteOrContext();
    if (!$group) {
      // If no group is found by context or route, we don't display the block.
      // It can happen on a module result page,
      // seems block render is before page permissions check.
      return [];
    }
    $group = $this->entityRepository->getTranslationFromContext($group);

    $steps = $group instanceof GroupInterface ? $this->getStepsByGroup($group) : [];

    // Get group context.
    $cid = $this->getCurrentGroupContentId();
    if (!$cid) {
      return [];
    }
    if (!$steps) {
      return [];
    }
    $steps = array_values($steps);
    if (!$steps) {
      return [];
    }
    foreach ($steps as $index => &$step) {
      $step['step_previous'] = $steps[$index - 1] ?? FALSE;
      $step['step_first'] = $index === 0;
      $step['step_last'] = count($steps) - 1 === ($index);
    }
    return [
      '#type' => 'container',
      [
        '#theme' => 'opigno_lp_step_activity',
        'title' => [
          '#markup' => $group->label(),
        ],
        'steps' => $steps,
        '#pre_render' => [
          [$this, 'processModuleList'],
        ],
      ],
    ];
  }

  /**
   * Process module list.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function processModuleList($elements) {
    foreach ($elements["steps"] as &$step) {
      $link = $this->getLinkToStart($step);
      $step = [
        // @todo seems it is a bad idea to generate a thme based on typology.
        '#theme' => sprintf('%s_%s', 'opigno_lp_step', strtolower($step["typology"])),
        'step' => $step,
        '#state' => $this->getState($step),
        '#current' => $this->isModuleCurrent($step),
        '#link' => FALSE,
        '#locked' => !$link,
        '#pre_render' => [
          [$this, 'processActivityList'],
        ],
      ];
    }
    return $elements;
  }

  /**
   * Converts a step array to a renderable array.
   */
  public function processActivityList($elements) {

    $meta = CacheableMetadata::createFromRenderArray($elements);

    if (in_array($elements["step"]['typology'], ['Module'])) {
      [
        $training,
        $course,
        $module,
      ] = $this->getTrainingAndCourse($elements["step"]);
      [
        $activities,
        $attempts,
      ] = $this->getActivities($training, $course, $module);
      // Check if activities can be navigated to via Free Navigation.
      $free_navigation = !OpignoGroupManagerController::getGuidedNavigation($training);
      $meta->addCacheableDependency($attempts);
      $activity_status = $this->getActivityStatus($activities, $attempts, $module);
      $activity_passed = array_filter($activity_status, static function ($status) {
        return 'passed' === $status;
      });
      $next = FALSE;
      $current_module = $this->isModuleCurrent($elements["step"]);
      foreach ($activities as &$activity) {
        $meta->addCacheableDependency($activity);
        $state = $activity_status[$activity->id()] ?? 'pending';
        $current = $this->getCurrentActivityId() === $activity->id();
        $is_link = in_array($state, ['passed']);
        $activity = $this->entityRepository->getTranslationFromContext($activity);
        $activity = [
          '#theme' => 'opigno_lp_step_module_activity',
          '#activity' => $activity,
          "#state" => $state,
          '#current' => $current,
          '#link' => $free_navigation && $current_module && ($current || $is_link || $next)
            ? Url::fromRoute('opigno_module.group.answer_form', [
              'group' => $training->id(),
              'opigno_activity' => $activity->id(),
              'opigno_module' => $module->id(),
            ])->toString()
            : FALSE,
          '#pre_render' => [
            [$this, 'processActivityStatus'],
          ],
        ];
        $next = $is_link && $current;
      }

      $elements['activity_counter'] = [
        '#markup' => $this->t('%passed/%total activities done', [
          '%passed' => count($activity_passed),
          '%total' => count($activity_status),
        ]),
      ];

      $elements['activities'] = $activities;
    }

    $meta->applyTo($elements);
    return $elements;
  }

  /**
   * Activity pre-processor.
   */
  public function processActivityStatus($elements) {
    $elements['title'] = [
      '#markup' => $elements['#activity']->label(),
    ];
    return $elements;
  }

  /**
   * Get the current active module.
   */
  protected function isModuleCurrent(array $step): bool {
    return $this->getCurrentGroupContentId() == $step['cid'];
  }

  /**
   * Get the state of the module.
   */
  protected function getState(array $step): ?string {
    return opigno_learning_path_get_step_status($step, $this->currentUser()
      ->id(), TRUE);
  }

  /**
   * Take a module link.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @todo Previously it can be take or next link, it should be researched.
   */
  protected function getLink($step): Url {
    $content_step = OpignoGroupManagedContent::load($step['cid']);
    /** @var \Drupal\opigno_group_manager\ContentTypeBase $content_type */
    $content_type = $this->opignoGroupContentTypesManager->createInstance($content_step->getGroupContentTypeId());
    $step_url = $content_type->getStartContentUrl($content_step->getEntityId(), $this->getCurrentGroupId());
    return Url::fromRoute($step_url->getRouteName(), $step_url->getRouteParameters());
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkToStart($step): ?Url {
    $link = NULL;
    /** @var \Drupal\opigno_learning_path\Controller\LearningPathStepsController $controller */
    $controller = $this->classResolver->getInstanceFromDefinition(LearningPathStepsController::class);
    if ($step['step_first']) {
      return $this->getLink($step);
    }

    $group_id = $this->getCurrentGroupId();
    $parent_content_id = $step["step_previous"]["cid"];
    $group = Group::load($group_id);
    $course_entity = OpignoGroupManagedContent::load($parent_content_id);
    $resp = $controller->getNextStep($group, $course_entity, FALSE);
    if (($resp["#type"] ?? FALSE) != 'html_tag') {
      // @todo an access to link should be checked here.
      //
      // if the response is a html type, that means the function returns
      // a redirect message, because we reuse the legacy code
      // that actually is not developed for the checking an access to route.
      return Url::fromRoute('opigno_learning_path.steps.next', [
        'group' => $group_id,
        'parent_content' => $parent_content_id,
      ]);
    }

    return $link;
  }

  /**
   * Loading a training/course and module entities by step array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getTrainingAndCourse($step): array {

    $step_training = $this->getStepTraining($step);
    if (!$step_training) {
      $step_training = [
        'cid' => $step['cid'],
        'id' => FALSE,
      ];
    }

    /** @var \Drupal\opigno_group_manager\OpignoGroupContent $content */
    $content = $this->entityTypeManager()
      ->getStorage('opigno_group_content')
      ->load($step_training["cid"]);
    $training = ($content instanceof OpignoGroupManagedContent) ? $content->getGroup() : NULL;
    $course = ($step_training["id"] ? $this->entityTypeManager()
      ->getStorage('group')
      ->load($step_training["id"]) : FALSE) ?: NULL;
    $module = $this->entityTypeManager()
      ->getStorage('opigno_module')
      ->load($step["id"]) ?: NULL;
    return [$training, $course, $module];
  }

  /**
   * If the module has a course as a parent object just return it.
   */
  protected function getStepTraining(array $elements) {
    return $elements["parent"] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'processModuleList',
      'processActivityList',
      'processActivityStatus',
    ];
  }

}
