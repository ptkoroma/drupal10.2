<?php

namespace Drupal\extra_field_test\Plugin\ExtraField\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\extra_field\Plugin\ExtraFieldFormBase;

/**
 * Single item extra field form plugin.
 *
 * @ExtraFieldForm(
 *   id = "single_item_test",
 *   label = @Translation("Single item test"),
 *   description = @Translation("An extra field with one item."),
 *   bundles = {
 *     "node.*"
 *   },
 *   weight = -30,
 *   visible = true
 * )
 */
class SingleItemTest extends ExtraFieldFormBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(array &$form, FormStateInterface $form_state) {
    return ['#markup' => 'Example markup provided by extra field form plugin.'];
  }

}
