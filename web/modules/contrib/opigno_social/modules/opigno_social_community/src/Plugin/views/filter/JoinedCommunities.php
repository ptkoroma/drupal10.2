<?php

namespace Drupal\opigno_social_community\Plugin\views\filter;

use Drupal\opigno_social_community\Services\CommunityManagerService;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the view filter handler to display the user's joined communities.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_joined_communities")
 *
 * @package Drupal\opigno_social_community\Plugin\views\filter
 */
class JoinedCommunities extends FilterPluginBase {

  /**
   * The Opigno community manager service.
   *
   * @var \Drupal\opigno_social_community\Services\CommunityManagerService
   */
  protected CommunityManagerService $communityManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(CommunityManagerService $community_manager, ...$default) {
    parent::__construct(...$default);
    $this->communityManager = $community_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_social_community.manager'),
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
  public function operatorOptions() {
    return [
      'IN' => $this->t('Member of'),
      'NOT IN' => $this->t('Not member of'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->query instanceof Sql) {
      return;
    }

    $communities = $this->communityManager->getJoinedCommunities();
    if ($communities) {
      // Prepare query.
      $this->ensureMyTable();
      $this->query->addWhere($this->options['group'], "{$this->tableAlias}.{$this->realField}", $communities, $this->operator);
    }

  }

}
