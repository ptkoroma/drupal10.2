<?php

namespace Drupal\opigno_social_community\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_social\Form\CreateSharePostForm;

/**
 * Defines the form to create a community post with the shared content.
 *
 * Overrides the CreateSharePostForm to create the community posts with the
 * shared content.
 *
 * @package Drupal\opigno_social_community\Form
 */
class CreateShareCommunityPostForm extends CreateSharePostForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_create_share_community_post_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $type = '', int $id = 0, string $entity_type = '', string $text = '') {
    $form = parent::buildForm($form, $form_state, $type, $id, $entity_type, $text);
    if (isset($form['hint_text'])) {
      unset($form['hint_text']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getCreatePostRouteName(): string {
    return 'opigno_social_community.create_post';
  }

}
