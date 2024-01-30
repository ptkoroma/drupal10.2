<?php

namespace Drupal\opigno_social\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the user connections block.
 *
 * @Block(
 *  id = "opigno_user_connections_block",
 *  admin_label = @Translation("User connections"),
 *  category = @Translation("Opigno Social"),
 * )
 */
class UserConnectionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The user connections manager service.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected $connectionsManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserConnectionManager $connections_manager, ...$default) {
    parent::__construct(...$default);
    $this->connectionsManager = $connections_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_user_connection.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'opigno_user_connections_block',
      '#connections' => $this->connectionsManager->renderUserConnectionsBlock(),
    ];
  }

}
