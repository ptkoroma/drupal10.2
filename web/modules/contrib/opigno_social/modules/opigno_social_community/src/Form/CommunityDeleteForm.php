<?php

namespace Drupal\opigno_social_community\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the Opigno community delete form.
 *
 * @package Drupal\opigno_social_community\Form
 */
class CommunityDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want delete the community?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('You are going to delete the community and all the messages linked to it. <strong>This action is not reversible.</strong>');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Yes, delete the community');
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $actions = parent::actionsElement($form, $form_state);
    $actions['cancel']['#weight'] = 0;

    // Override links if the form has been rendered with ajax.
    if (!$form_state->get('ajax_modal')) {
      return $actions;
    }

    $actions['cancel']['#url'] = Url::fromRoute('<none>');
    $actions['cancel']['#attributes']['class'] += [
      'close',
      'btn',
      'btn-rounded',
    ];
    $actions['cancel']['#attributes']['data-dismiss'] = 'modal';

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Redirect to the different pages depending on the AJAX.
    $redirect_url = $form_state->get('ajax_modal')
      ? Url::fromRoute('opigno_social_community.join_communities')
      : Url::fromRoute('view.communities.collection');
    $form_state->setRedirectUrl($redirect_url);
  }

}
