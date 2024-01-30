<?php

namespace Drupal\opigno_messaging\Plugin\views\filter;

use Drupal\opigno_messaging\Services\OpignoMessageThread;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter view handler for the available message thread IDs.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_available_thread_ids")
 */
class OpignoAvailableMessageThreadIdsFilter extends FilterPluginBase {

  /**
   * Opigno messages service.
   *
   * @var array|\Drupal\opigno_messaging\Services\OpignoMessageThread
   */
  protected $messageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(OpignoMessageThread $message_manager, ...$default) {
    parent::__construct(...$default);
    $this->messageManager = $message_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_messaging.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
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

    // Get the available user threads.
    $this->ensureMyTable();
    $threads = $this->messageManager->getUserThreads();
    $threads = $threads ?: [0];
    $this->query->addWhere(NULL, "$this->tableAlias.$this->realField", $threads, 'IN');
  }

}
