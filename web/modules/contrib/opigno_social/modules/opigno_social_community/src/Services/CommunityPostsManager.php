<?php

namespace Drupal\opigno_social_community\Services;

use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Services\OpignoPostsManager;
use Drupal\opigno_social\Services\OpignoPostsManagerInterface;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Entity\CommunityPostInterface;

/**
 * Defines the community posts manager.
 *
 * Overrides the OpignoPostsManager service to work with the community posts.
 *
 * @package Drupal\opigno_social_community\Services
 */
class CommunityPostsManager extends OpignoPostsManager implements OpignoPostsManagerInterface {

  /**
   * {@inheritdoc}
   */
  protected static string $entityType = 'opigno_community_post';

  /**
   * {@inheritdoc}
   */
  public function getCommentFormBlockId(): string {
    return 'opigno_social_community_comment_form_block';
  }

  /**
   * {@inheritdoc}
   */
  public function getCommentFormRouteName(): string {
    return 'opigno_social_community.show_comment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getPostCommentsRouteName(): string {
    return 'opigno_social_community.get_post_comments';
  }

  /**
   * {@inheritdoc}
   */
  public function getLoadMoreCommentsRouteName(): string {
    return 'opigno_social_community.load_more_comments';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteLink(OpignoPostInterface $post): array {
    $delete_link = [];
    if ($post->access('delete')) {
      $delete_link = $this->generateActionLink(
        'opigno_social_community.delete_post',
        ['post' => $post->id()],
        $this->t('Delete'),
        'delete');
    }

    return $delete_link;
  }

  /**
   * {@inheritdoc}
   */
  protected function addExtraActionLinks(array &$build, OpignoPostInterface $post): void {
    if (!$post instanceof CommunityPostInterface) {
      parent::addExtraActionLinks($build, $post);
      return;
    }

    $community = $post->getCommunity();
    if ($community instanceof CommunityInterface && $community->access('pin_post')) {
      $pin_text = $post->isPinned()
        ? $this->t('Unpin', [], ['context' => 'Opigno post'])
        : $this->t('Pin', [], ['context' => 'Opigno post']);
      $build['#actions'][] = $this->generateActionLink(
        'opigno_social_community.pin_post',
        ['post' => $post->id()],
        $pin_text,
        'pin'
      );
    }

    $delete_link = $this->getDeleteLink($post);
    if ($delete_link) {
      $build['#actions'][] = $delete_link;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLastViewedPostId(): int {
    // There should be no mention about the last viewed community post in the
    // 1st phase.
    return 0;
  }

}
