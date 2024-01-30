<?php

namespace Drupal\opigno_ilt\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\opigno_ilt\Entity\ILT;
use Drupal\Core\Form\ConfirmFormHelper;

/**
 * Provides a form for deleting a opigno_ilt entity.
 */
class ILTDeleteForm extends ContentEntityConfirmFormBase {

  private $hasResults;

  private $hasTraining;

  /**
   * Checks if ILT has results.
   */
  private function _hasResults() {
    $ilt = ILT::load($this->entity->id());

    if ($ilt->getResultsIds()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if ILT has parent training.
   */
  private function _hasTraining() {
      $training = $this->entity->getTrainingId();
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
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the %ilt Instructor-Led Training?', [
      '%ilt' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->hasResults) {
      return $this->t('There are some results for this ILT, and it cannot consequently be deleted in order to keep the users achievements');
    }

    if ($this->hasTraining) {
      return $this->t('This ILT is being used and it needs to be removed from the training(s) using it before being able to delete it.');
    }

    return $this->t('This action cannot be undone.');
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
    return Url::fromRoute('entity.opigno_ilt.canonical', [
      'opigno_ilt' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('The Instructor-Led Training %ilt has been deleted.', [
      '%ilt' => $this->entity->label(),
    ]));
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

}
