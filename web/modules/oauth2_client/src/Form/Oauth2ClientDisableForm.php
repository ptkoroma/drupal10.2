<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * A standard confirm form for disabling clients.
 */
class Oauth2ClientDisableForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disable the Oauth2 Client <em>%client</em>?', ['%client' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.oauth2_client.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Disabled clients will not return tokens from the Oauth2ClientService.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->disable()->save();
    $this->messenger()->addStatus($this->t('Disabled  Oauth2 Client <em>%client</em>?', ['%client' => $this->entity->label()]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
