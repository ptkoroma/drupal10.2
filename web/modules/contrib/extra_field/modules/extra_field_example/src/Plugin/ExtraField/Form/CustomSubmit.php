<?php

namespace Drupal\extra_field_example\Plugin\ExtraField\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\extra_field\Plugin\ExtraFieldFormBase;

/**
 * Example Extra field form display.
 *
 * @ExtraFieldForm(
 *   id = "example_custom_submit",
 *   label = @Translation("Custom submit for all nodes"),
 *   description = @Translation("An extra form button."),
 *   bundles = {
 *     "node.*"
 *   },
 *   weight = 100,
 *   visible = true
 * )
 */
class CustomSubmit extends ExtraFieldFormBase {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(array &$form, FormStateInterface $form_state) {

    // Here you can use elements of different types.
    $element = [
      '#type' => 'submit',
      '#value' => $this->t('Custom submit'),
      '#submit' => [
        '::submitForm',
        [$this, 'addCustomMessage'],
      ],
    ];

    return $element;
  }

  /**
   * Handle custom submit.
   *
   * @param array $form
   *   The entity form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function addCustomMessage(array $form, FormStateInterface $form_state) {

    $this->messenger()->addMessage(t('Custom submit was triggered.'));
  }

}
