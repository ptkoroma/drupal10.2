<?php

namespace Drupal\opigno_social\Plugin\views\filter;

use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter view handler for the user's network connections.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_network_connections")
 */
class UserNetworkConnections extends FilterPluginBase {

  /**
   * User connection manager service.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected $connectionManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserConnectionManager $connection_manager, ...$default) {
    parent::__construct(...$default);
    $this->connectionManager = $connection_manager;
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
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->query instanceof Sql) {
      return;
    }

    $network = $this->connectionManager->getUserNetwork();
    $network = $network ?: [0];

    // Prepare query.
    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $network, 'IN');
  }

}
