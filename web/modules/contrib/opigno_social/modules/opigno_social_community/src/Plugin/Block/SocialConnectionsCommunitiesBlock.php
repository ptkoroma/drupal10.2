<?php

namespace Drupal\opigno_social_community\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social\Plugin\Block\UserConnectionsBlock;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\opigno_social_community\Services\CommunityStatistics;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overrides the connections block, adds info about user's communities.
 *
 * @package Drupal\opigno_social_community\Plugin\Block
 */
class SocialConnectionsCommunitiesBlock extends UserConnectionsBlock {

  /**
   * The community statistics manager service.
   *
   * @var \Drupal\opigno_social_community\Services\CommunityStatistics
   */
  protected CommunityStatistics $statsManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * Whether the communities feature enabled on the site or not.
   *
   * @var bool
   */
  protected bool $communitiesEnabled;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    CommunityStatistics $stats,
    AccountInterface $account,
    ConfigFactoryInterface $config,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->statsManager = $stats;
    $this->account = $account;
    $this->communitiesEnabled = (bool) $config->get(Community::ADMIN_CONFIG_NAME)->get('enable_communities') ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_social_community.statistics'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('opigno_user_connection.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->communitiesEnabled) {
      return parent::build();
    }

    $uid = $this->account->id();
    $connections_number = count($this->connectionsManager->getUserNetwork($uid));
    $attributes = ['attributes' => ['class' => ['btn']]];

    // Get 3 communities with the recent activities (posts/comments).
    $recent = $this->statsManager->getLatestActiveUserCommunities(3);
    $communities = [];

    if ($recent) {
      foreach ($recent as $id => $title) {
        $communities[] = Link::createFromRoute($title,
          'entity.opigno_community.canonical',
          ['opigno_community' => $id],
          $attributes
        )->toRenderable();
      }
    }

    return [
      '#theme' => 'opigno_connections_communities_block',
      '#connections' => $connections_number,
      '#connections_link' => Link::createFromRoute($this->t('Manage your connections'),
        'opigno_social.manage_connections',
        [],
        $attributes
      )->toRenderable(),
      '#communities' => $communities,
      '#communities_feed_link' => Link::createFromRoute(
        $this->t('See your communities'),
        'opigno_social_community.latest_active_community'
      )->toRenderable(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      [
        'config:' . Community::ADMIN_CONFIG_NAME,
        UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $this->account->id(),
        'opigno_community_list',
        'opigno_community_post_list',
      ]
    );
  }

}
