<?php

namespace Drupal\opigno_module\Plugin\views\field;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_module_result_actions")
 */
class OpignoModuleResultActions extends FieldPluginBase {

  use EntityTranslationRenderTrait;
  use RedirectDestinationTrait;
  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = ['entityManager' => 'entity.manager'];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new EntityOperations object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, EntityRepositoryInterface $entity_repository = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;

    if (!$entity_repository) {
      @trigger_error('Calling EntityOperations::__construct() with the $entity_repository argument is supported in drupal:8.7.0 and will be required before drupal:9.0.0. See https://www.drupal.org/node/2549139.', E_USER_DEPRECATED);
      $entity_repository = \Drupal::service('entity.repository');
    }
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['destination'] = [
      'default' => FALSE,
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntityTranslation($this->getEntity($values), $values);
    $operations = $this->entityTypeManager->getListBuilder($entity->getEntityTypeId())->getOperations($entity);
    unset($operations["edit"], $operations["devel"]);

    /** @var \Drupal\opigno_module\Entity\OpignoActivity $activities */
    $activities = $values->_relationship_entities["module"]->getModuleActivities();
    $activities = array_column((array) $activities, 'type');
    $manually_scored_activities = [
      'opigno_file_upload',
      'opigno_long_answer',
    ];
    $correct_activity = !empty(array_intersect($manually_scored_activities, $activities));
    $module_score_parameters = [
      'opigno_module' => $values->opigno_module_field_data_user_module_status_id,
      'user_module_status' => $values->id,
    ];

    if ($correct_activity) {
      $operations['score'] = [
        'title' => $this->t('Score'),
        'weight' => 20,
        'url' => Url::fromRoute('opigno_module.module_result_form', $module_score_parameters)
      ];
    }

    $build = [
      '#type' => 'operations',
      '#links' => $operations,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We purposefully do not call parent::query() because we do not want the
    // default query behavior for Views fields. Instead, let the entity
    // translation renderer provide the correct query behavior.
    if ($this->languageManager->isMultilingual()) {
      $this->getEntityTranslationRenderer()->query($this->query, $this->relationship);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->getEntityType();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityManager() {
    // This relies on DeprecatedServicePropertyTrait to trigger a deprecation
    // message in case it is accessed.
    return $this->entityManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityRepository() {
    return $this->entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager() {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView() {
    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

}
