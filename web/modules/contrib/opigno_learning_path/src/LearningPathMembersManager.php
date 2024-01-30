<?php

namespace Drupal\opigno_learning_path;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Class LearningPathContentTypesManager.
 */
class LearningPathMembersManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/LearningPathMembers',
      $namespaces,
      $module_handler,
      'Drupal\opigno_learning_path\LearningPathMembersPluginInterface',
      'Drupal\opigno_learning_path\Annotation\LearningPathMembers'
    );

    $this->alterInfo('learning_path_members');
  }

  /**
   * Creates instance.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    return parent::createInstance($plugin_id, $configuration);
  }

}
