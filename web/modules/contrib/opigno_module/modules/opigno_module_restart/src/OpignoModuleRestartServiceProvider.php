<?php

namespace Drupal\opigno_module_restart;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\opigno_module_restart\Services\LearningPathContentService;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines the service provider class to override the LP content service.
 *
 * @package Drupal\opigno_module_restart
 */
class OpignoModuleRestartServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('opigno_learning_path.content_service')) {
      $definition = $container->getDefinition('opigno_learning_path.content_service');
      $args = $definition->getArguments();
      array_unshift($args, new Reference('opigno_module_restart.manager'));
      $definition->setClass(LearningPathContentService::class)->setArguments($args);
    }
  }

}
