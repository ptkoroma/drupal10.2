<?php

namespace Drupal\opigno_social_community\Plugin\Block;

use Drupal\opigno_social\Plugin\Block\CommentFormBlock;
use Drupal\opigno_social_community\Form\CreateCommunityCommentForm;

/**
 * Provides the "Create a community comment" block (overrides CommentFormBlock).
 *
 * @Block(
 *  id = "opigno_social_community_comment_form_block",
 *  admin_label = @Translation("Opigno Community create comment block"),
 *  category = @Translation("Opigno Social Community"),
 * )
 */
class CommunityCommentFormBlock extends CommentFormBlock {

  /**
   * {@inheritdoc}
   */
  protected static function getCommentFormName(): string {
    return CreateCommunityCommentForm::class;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getHideCommentsRoute(): string {
    return 'opigno_social_community.hide_post_comments';
  }

}
