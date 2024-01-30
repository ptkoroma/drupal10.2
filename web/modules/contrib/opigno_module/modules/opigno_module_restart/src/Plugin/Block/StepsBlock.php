<?php

namespace Drupal\opigno_module_restart\Plugin\Block;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\opigno_learning_path\Plugin\Block\StepsBlock as StepsBlockBase;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;
use Drupal\opigno_module_restart\Services\ModuleRestartManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overrides the LP steps block definition.
 *
 * @package Drupal\opigno_module_restart\Plugin\Block
 */
class StepsBlock extends StepsBlockBase {

  /**
   * The Opigno modules storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $moduleStorage;

  /**
   * Whether the free navigation enabled for the LP or not.
   *
   * @var bool
   */
  private bool $isFreeNavigationEnabled = FALSE;

  /**
   * The group ID.
   *
   * @var int|null
   */
  private ?int $gid = NULL;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ...$default) {
    parent::__construct(...$default);
    $this->moduleStorage = $entity_type_manager->getStorage('opigno_module');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
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
  public function processActivityList($elements) {
    $list = parent::processActivityList($elements);
    $step = $list['step'] ?? [];
    $typology = $step['typology'] ?? '';
    $locked = $elements['#locked'] ?? FALSE;
    $current = $elements['#current'] ?? FALSE;

    // If the "free navigation" option enabled for the LP, the links should
    // be displayed in the steps block for modules that are not locked and that
    // are already completed and for the current module in current LP attempt.
    if (!$this->isFreeNavigationEnabled || $typology !== 'Module' || $locked) {
      return $list;
    }

    $module_id = $step['id'];
    $module = $this->moduleStorage->load($module_id);
    if (!$module instanceof OpignoModuleInterface) {
      return $list;
    }

    $attempt = $module->getModuleActiveAttempt($this->currentUser(), NULL, $this->gid);
    $lp_attempt_id = $attempt instanceof UserModuleStatusInterface ? $attempt->getLearningPathAttempt() : NULL;
    $can_create_attempt = $module->canCreateNewAttempt($this->currentUser(), NULL, NULL, FALSE, $this->gid);

    foreach ($list['activities'] as $id => &$activity) {
      // Links shouldn't be displayed for modules where the max number of
      // attempts is already reached.
      $in_current_lp_attempt = $attempt instanceof UserModuleStatusInterface
        && $attempt->isInCurrentLpAttempt($this->currentUser()->id(), $this->gid);
      $is_linked = $lp_attempt_id ? $in_current_lp_attempt && $can_create_attempt : $current && $can_create_attempt;
      $activity['#link'] = $is_linked
        ? Url::fromRoute('opigno_module_restart.restart_activity', [
          'group' => $this->gid,
          'opigno_activity' => $id,
          'opigno_module' => $module_id,
        ])->toString()
        : [];
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function processModuleList($elements) {
    $build = parent::processModuleList($elements);
    $group = $this->getGroupByRouteOrContext();

    if (ModuleRestartManager::isGroupFreeNavigation($group)) {
      $this->isFreeNavigationEnabled = TRUE;
      $this->gid = (int) $group->id();
    }

    return $build;
  }

}
