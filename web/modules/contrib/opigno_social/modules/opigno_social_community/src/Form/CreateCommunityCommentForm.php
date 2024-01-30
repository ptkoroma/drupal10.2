<?php

namespace Drupal\opigno_social_community\Form;

use Drupal\opigno_social\Form\CreateCommentForm;

/**
 * Overrides CreateCommentForm to create a community comment.
 *
 * @package Drupal\opigno_social_community\Form
 */
class CreateCommunityCommentForm extends CreateCommentForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_create_community_comment_form';
  }

  /**
   * {@inheritdoc}
   */
  protected static function getCreateCommentRouteName(): string {
    return 'opigno_social_community.create_comment';
  }

}
