<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Plugin\Block\CommunityFeedBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the extra field to show community posts feed.
 *
 * @ExtraFieldDisplay(
 *   id = "community_feed",
 *   label = @Translation("Community feed"),
 *   bundles = {
 *     "opigno_community.*"
 *   },
 * )
 */
class CommunityFeed extends CommunityExtraFieldBase implements ContainerFactoryPluginInterface {

  /**
   * The block plugin manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(BlockManagerInterface $block_manager, ...$default) {
    parent::__construct(...$default);
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('plugin.manager.block'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    if (!$entity instanceof CommunityInterface) {
      return $this->emptyField();
    }

    $block = $this->blockManager->createInstance('opigno_social_community_feed_block', ['community' => $entity]);

    return $block instanceof CommunityFeedBlock ? $block->build() : $this->emptyField();
  }

}
