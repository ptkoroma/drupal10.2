<?php

namespace Drupal\opigno_social\Services;

use Drupal\opigno_social\Entity\OpignoPostInterface;

/**
 * Defines the general interface for the posts/comments manager services.
 *
 * @package Drupal\opigno_social\Services
 */
interface OpignoPostsManagerInterface {

  /**
   * Gets the link to hide/show the post comments.
   *
   * @param int $pid
   *   The post ID to get the comments link for.
   *
   * @return array
   *   The rendered link to hide/show post comments.
   */
  public function getCommentsLink(int $pid): array;

  /**
   * The the "get post comments" route name.
   *
   * @return string
   *   The "get post comments" route name.
   */
  public function getPostCommentsRouteName(): string;

  /**
   * Get the list of available post/comment action links.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post/comment entity to get actions for.
   *
   * @return array
   *   The render array of available action links.
   */
  public function getActionLinks(OpignoPostInterface $post): array;

  /**
   * Gets the comment form block ID.
   *
   * @return string
   *   The comment form block ID.
   */
  public function getCommentFormBlockId(): string;

  /**
   * Gets the AJAX link to generate the comment form.
   *
   * @param int $pid
   *   The post ID to get the comment form link for.
   *
   * @return array
   *   The link to generate the comment form.
   */
  public function getCommentFormLink(int $pid): array;

  /**
   * Gets the route name to display the comment form.
   *
   * @return string
   *   The comment form route name.
   */
  public function getCommentFormRouteName(): string;

  /**
   * Generates the load more comments link.
   *
   * @param int $pid
   *   The post ID to get comments for.
   * @param int $amount
   *   The number of comments to load.
   * @param int $from
   *   The index of the comment to load more.
   *
   * @return array
   *   The render array to display the load more comments link.
   */
  public function loadMoreCommentsLink(int $pid, int $amount, int $from = 0): array;

  /**
   * Gets the "Load more comments" route name.
   *
   * @return string
   *   The "Load more comments" route name.
   */
  public function getLoadMoreCommentsRouteName(): string;

  /**
   * Get the post ID that is the last viewed by the current user.
   *
   * @return int
   *   The ID of the last post viewed by the current user.
   */
  public function getLastViewedPostId(): int;

}
