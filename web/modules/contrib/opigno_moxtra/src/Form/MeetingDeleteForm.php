<?php

namespace Drupal\opigno_moxtra\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\ConfirmFormHelper;

/**
 * Provides a form for deleting a opigno_moxtra_meeting entity.
 */
class MeetingDeleteForm extends ContentEntityConfirmFormBase {

  private $hasTraining;
  private $hasResults;

  /**
   * Checks if Live Meeting has parent training.
   */
  private function _hasTraining() {
    $training = $this->entity->getTrainingId();
    return (bool) $training;
  }

  /**
   * Checks if Live Meeting has parent training.
   */
  private function _hasResults() {
    $training = $this->entity->getResultsIds();
    return (bool) $training;
  }

  /**
    * {@inheritdoc}
    */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->hasResults = $this->_hasResults();
    $this->hasTraining = $this->_hasTraining();
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->hasResults) {
      return $this->t('There are some results for this Live Meeting, and it cannot consequently be deleted in order to keep the users achievements');
    }

    if ($this->hasTraining) {
      return $this->t('This Live Meeting is being used and it needs to be removed from the training(s) using it before being able to delete it.');
    }

    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $attributes = [];

    if ($this->hasResults || $this->hasTraining) {
      $attributes = ['disabled' => 'disabled'];
    }

    return [
      'submit' => [
        '#type' => 'submit',
        '#attributes' => $attributes,
        '#value' => $this->getConfirmText(),
        '#submit' => [
          [$this, 'submitForm'],
        ],
      ],
      'cancel' => ConfirmFormHelper::buildCancelLink($this, $this->getRequest()),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the %meeting Live Meeting?', [
      '%meeting' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.opigno_moxtra_meeting.canonical', [
      'opigno_moxtra_meeting' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('The Live Meeting %meeting has been deleted.', [
      '%meeting' => $this->entity->label(),
    ]));
  }

}
