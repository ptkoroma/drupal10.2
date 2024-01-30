<?php

namespace Drupal\opigno_file_upload\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'opigno_evaluation_method_widget' widget.
 *
 * @FieldWidget(
 *   id = "opigno_evaluation_method_widget",
 *   label = @Translation("Evaluation method widget"),
 *   field_types = {
 *     "opigno_evaluation_method"
 *   }
 * )
 */
class OpignoEvaluationMethodWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = [
      0 => $this->t('Automatic'),
      1 => $this->t('Manual'),
    ];
    $options_description = [
      0 => [
        '#description' => $this->t('Users will get the max score for that activity as soon as they have loaded the file'),
      ],
      1 => [
        '#description' => $this->t('The activity will have to be manually graded by a teacher'),
      ],
    ];
    $element['value'] = $element + [
      '#type' => 'radios',
      '#options' => $options,
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#title' => 'Evaluation method',
      '#weight' => 1,
    ];
    $element['value'] += $options_description;

    return $element;
  }

}
