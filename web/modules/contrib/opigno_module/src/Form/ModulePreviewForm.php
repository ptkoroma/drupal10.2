<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_module\Entity\OpignoActivity;

/**
 * Add External package form.
 */
class ModulePreviewForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'module_preview_form';
  }

  /**
   * {@inheritdoc}
   */
  protected $step = 1;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $route_params = \Drupal::routeMatch()->getParameters();
    $opigno_module = $route_params->get('opigno_module');
    $activities = array_values($opigno_module->getModuleActivities());
    $count = count($activities);

    if ($activities) {
      // Add wrapper for ajax.
      $form['#prefix'] = '<div id="activity-wrapper">';
      $form['#suffix'] = '</div>';

      $activity = OpignoActivity::load($activities[$this->step - 1]->id);

      $form['activity'] = [
        '#type' => 'inline_template',
        '#template' => '<iframe style="{{ style }}" src="{{ url }}"></iframe>',
        '#context' => [
          'url' => '/admin/structure/opigno-activity/preview/' . $activity->id(),
          'style' => "width: 100%; height: 100%; border: 0;",
        ],
      ];
    }

    $form['navigation'] = [
      '#prefix' => '<div class="activities-navigation">',
      '#suffix' => '</div>',
    ];

    if ($this->step != 1) {
      $form['navigation']['previous_button'] = array(
        '#type' => 'submit',
        '#name' => 'previous_button',
        '#value' => t('Back'),
        '#ajax' => [
          'callback' => '::ajaxFormRefreshCallback',
          'event' => 'click',
          'wrapper' => 'activity-wrapper',
        ],
      );
    }

    if ($count > 1) {
      $form['navigation']['step'] = [
        '#markup' => $this->step . '/' . $count,
        '#prefix' => '<div class="activities-count">',
        '#suffix' => '</div>',
      ];
    }

    if ($this->step < $count) {
      $form['navigation']['next_button'] = array(
        '#type' => 'submit',
        '#name' => 'next_button',
        '#value' => t('Next'),
        '#ajax' => [
          'callback' => '::ajaxFormRefreshCallback',
          'event' => 'click',
          'wrapper' => 'activity-wrapper',
        ],
      );
    }

    $form['#attached']['library'][] = 'opigno_module/opigno_module_preview';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $inputs = $form_state->getUserInput();

    if (isset($inputs['_triggering_element_name']) && $inputs['_triggering_element_name'] == 'next_button') {
      $this->step++;
    }
    elseif (isset($inputs['_triggering_element_name']) && $inputs['_triggering_element_name'] == 'previous_button') {
      $this->step--;
    }

    $form_state->setRebuild();
  }

  public function ajaxFormRefreshCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
