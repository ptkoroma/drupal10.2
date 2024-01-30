<?php

namespace Drupal\opigno_social\Plugin\views\sort;

use Drupal\opigno_social\Services\OpignoPostsManager;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default posts sorting handler: display pinned posts first, then others.
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort ("pinned_first")
 */
class OpignoPostsSorting extends SortPluginBase {

  /**
   * Posts manager service.
   *
   * @var \Drupal\opigno_social\Services\OpignoPostsManager
   */
  protected $postsManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(OpignoPostsManager $posts_manager, ...$default) {
    parent::__construct(...$default);
    $this->postsManager = $posts_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_posts.manager'),
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
    $this->ensureMyTable();

    $order = $this->options['order'];
    $pinned = $this->postsManager->getPinnedHiddenPosts();

    if ($pinned) {
      // Check if the post is pinned.
      $pinned = implode(',', $pinned);
      $this->query->addOrderBy(NULL, "FIELD($this->tableAlias.id, $pinned)", $order, 'pinned');
    }

    // Default sort by date.
    $this->query->addOrderBy(NULL, "$this->tableAlias.$this->realField", $order, 'by_date');
  }

}
