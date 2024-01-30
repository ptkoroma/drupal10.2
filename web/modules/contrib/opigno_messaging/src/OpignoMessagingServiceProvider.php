<?php

namespace Drupal\opigno_messaging;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\opigno_messaging\Services\OpignoPrivateMessageService;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Override PrivateMessageService with the custom one.
 *
 * @package Drupal\opigno_messaging\Services
 */
class OpignoMessagingServiceProvider extends ServiceProviderBase implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Override PrivateMessageService, update the way to get the 1st user
    // message thread.
    if (!$container->hasDefinition('private_message.service')) {
      return;
    }

    $definition = $container->getDefinition('private_message.service');
    $arguments = $definition->getArguments();
    array_unshift($arguments, new Reference('opigno_messaging.manager'));
    $definition->setClass(OpignoPrivateMessageService::class)
      ->setArguments($arguments);
  }

}
