<?php

namespace Drupal\opigno_forum\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\forum\ForumManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a opigno_forum_last_topics_block block.
 *
 * @Block(
 *   id = "opigno_forum_last_topics_block",
 *   admin_label = @Translation("ForumLastTopicsBlock"),
 *   category = @Translation("Custom")
 * )
 */
class LastTopicBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Service forum_manager definition.
   *
   * @var \Drupal\forum\ForumManagerInterface
   */
  protected $forumManager;

  /**
   * Constructor of the instance plugin.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ForumManagerInterface $forum_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->forumManager = $forum_manager;
  }

  /**
   * Creates an instance of the plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('forum_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $tid = $this->configuration["taxonomy_term"];

    $build = $this->forumManager->getTopics($tid, $this->currentUser());
    $build['content'] = [
      '#theme' => 'opigno_forum_last_topics_block',
      'topics' => array_slice($build['topics'] ?: [], 0, 4),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  private function currentUser(): AccountInterface {
    return \Drupal::currentUser();
  }

}
