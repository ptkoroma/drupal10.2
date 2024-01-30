<?php

namespace Drupal\opigno_social_community\Form;

use Drupal\opigno_social\Form\CreatePostForm;

/**
 * Overrides CreatePostForm to create a community post.
 *
 * @package Drupal\opigno_social_community\Form
 */
class CreateCommunityPostForm extends CreatePostForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_create_community_post_form';
  }

  /**
   * {@inheritdoc}
   */
  protected static function getCreatePostRouteName(): string {
    return 'opigno_social_community.create_post';
  }

}
