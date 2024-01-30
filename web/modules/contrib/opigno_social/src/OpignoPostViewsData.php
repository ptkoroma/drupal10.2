<?php

namespace Drupal\opigno_social;

use Drupal\views\EntityViewsData;

/**
 * Opigno post views data handler.
 *
 * @package Drupal\opigno_social
 */
class OpignoPostViewsData extends EntityViewsData {

  /**
   * The entity base table name.
   *
   * @var string
   */
  protected string $baseTable = 'opigno_post';

  /**
   * The ID of available posts view filter.
   *
   * @var string
   */
  protected string $availablePostsFilterId = 'opigno_available_posts';

  /**
   * The ID of the "Pinned first" sorting.
   *
   * @var string
   */
  protected string $pinnedSortId = 'pinned_first';

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // View filter for the user social posts.
    $data[$this->baseTable][$this->availablePostsFilterId] = [
      'help' => $this->t("Get the list of posts available for the current user."),
      'real field' => 'uid',
      'filter' => [
        'title' => $this->t("Opigno available posts"),
        'id' => $this->availablePostsFilterId,
      ],
    ];

    // View sorting: pinned first, then newest.
    $data[$this->baseTable][$this->pinnedSortId] = [
      'title' => $this->t('Opigno Pinned posts first'),
      'group' => $this->t('Opigno post/comment'),
      'help' => $this->t('Display pinned posts first, then others.'),
      'sort' => [
        'field' => 'created',
        'id' => $this->pinnedSortId,
      ],
    ];

    return $data;
  }

}
