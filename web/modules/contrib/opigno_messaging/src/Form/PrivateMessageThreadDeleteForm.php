<?php

namespace Drupal\opigno_messaging\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\private_message\Form\PrivateMessageThreadDeleteForm as DeleteFormBase;

/**
 * Override the private message thread delete form.
 */
class PrivateMessageThreadDeleteForm extends DeleteFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['description']['#markup'] = $this->t('Do you really want to delete this discussion?');

    // Update buttons order, add extra classes.
    $form['actions']['submit']['#attributes'] = [
      'class' => ['btn', 'btn-rounded', 'btn-border-red'],
    ];
    $form['actions']['submit']['#weight'] = -50;

    $form['actions']['cancel']['#attributes'] = [
      'class' => ['btn', 'btn-rounded', 'use-ajax'],
    ];
    $form['actions']['submit']['#weight'] = 50;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you really want to delete this discussion?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('opigno_learning_path.close_modal');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

}
