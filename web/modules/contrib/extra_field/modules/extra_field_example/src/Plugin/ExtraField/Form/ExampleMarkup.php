<?php

namespace Drupal\extra_field_example\Plugin\ExtraField\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\extra_field\Plugin\ExtraFieldFormBase;

/**
 * Example Extra field form display.
 *
 * @ExtraFieldForm(
 *   id = "example_markup",
 *   label = @Translation("Example markup"),
 *   description = @Translation("Some examples of elements with markup on a form."),
 *   bundles = {
 *     "node.*"
 *   },
 *   visible = true
 * )
 */
class ExampleMarkup extends ExtraFieldFormBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(array &$form, FormStateInterface $form_state) {

    $element['markup'] = ['#markup' => '<p>Hello world 1</p>'];

    $element['container'] = [
      '#type' => 'container',
      '#markup' => '<p>Hello world 2</p>',
    ];

    $element['item'] = [
      '#type' => 'item',
      '#title' => 'Greeting',
      '#markup' => '<p>Hello world 3</p>',
      '#description' => "A common line of example output.",
    ];

    return $element;
  }

}
