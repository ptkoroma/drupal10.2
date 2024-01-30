<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_learning_path\Entity\LPModuleAvailability;

/**
 * Form controller for Module edit forms.
 *
 * @ingroup opigno_module
 */
class OpignoModuleForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $module \Drupal\opigno_module\Entity\OpignoModule */
    $module = $this->entity;
    $moduleHandler = \Drupal::service('module_handler');
    $route_name = \Drupal::routeMatch()->getRouteName();

    $form = parent::buildForm($form, $form_state);

    $form['advanced'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 99,
    ];
    // Module Taking options.
    $form['opigno_module_taking_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Taking options'),
      '#attributes' => ['id' => 'taking-options-fieldset'],
      '#group' => 'advanced',
      '#weight' => 1,
    ];
    $form['allow_resume']['#group'] = 'opigno_module_taking_options';
    $form['backwards_navigation']['#group'] = 'opigno_module_taking_options';
    $form['hide_results']['#group'] = 'opigno_module_taking_options';

    $form['opigno_module_taking_options']['randomization'] = [
      '#type' => 'fieldset',
      '#weight' => 3,
    ];
    $form['randomization']['#group'] = 'randomization';
    $form['random_activities']['#group'] = 'randomization';
    $form['random_activities']['#states'] = [
      'visible' => [
        ':input[name=randomization]' => ['value' => '2'],
      ],
    ];

    $form['opigno_module_taking_options']['multiple_takes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Multiple takes'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#attributes' => ['id' => 'multiple-takes-fieldset'],
      '#description' => $this->t('Allow users to take this Module multiple times.'),
      '#weight' => 4,
    ];
    $form['takes']['#group'] = 'multiple_takes';
    $form['show_attempt_stats']['#group'] = 'multiple_takes';
    $form['keep_results']['#group'] = 'multiple_takes';
    // Module Availability options.
    $form['opigno_module_availability'] = [
      '#type' => 'details',
      '#title' => $this->t('Availability options'),
      '#attributes' => ['id' => 'availability-fieldset'],
      '#group' => 'advanced',
      '#weight' => 2,
    ];
    $form['open_date']['#group'] = 'opigno_module_availability';
    $form['close_date']['#group'] = 'opigno_module_availability';

    // Disallow module availability date validation.
    $form['open_date']['widget'][0]['value']['#validated'] = TRUE;
    $form['close_date']['widget'][0]['value']['#validated'] = TRUE;

    if ($route_name === 'opigno_group_manager.manager.get_item_form') {
      $route_params = \Drupal::routeMatch()->getParameters()->all();
      $module_id = $module->id();
      $group_id = $route_params['group']->id();
      if ($module_id && $group_id) {
        if ($route_params['group']->getGroupType()->id() === 'opigno_course') {
          $build_info = $form_state->getBuildInfo();
          if (!empty($build_info['args'][0]['opigno_group_info']['learning_path'])) {
            $group_id = $build_info['args'][0]['opigno_group_info']['learning_path'];
          }
        }
        // Load training module availability.
        $availability_entity = LPModuleAvailability::loadByProperties([
          'group_id' => $group_id,
          'entity_id' => $module_id,
        ]);
      }

      $open_date = [
        'year' => 'yyyy',
        'month' => 'mm',
        'day' => 'dd',
      ];
      $close_date = [
        'year' => 'yyyy',
        'month' => 'mm',
        'day' => 'dd',
      ];
      $availability = 0;
      if (!empty($availability_entity)) {
        // If training module availability exists prepare values for the form.
        $availability_entity = current($availability_entity);

        // Restriction flag value from db.
        $availability = $availability_entity->getAvailability();

        // Dates values from db.
        $open_timestamp = $availability_entity->getOpenDate();
        if ($open_timestamp) {
          $date = new DrupalDateTime();
          $open_date = $date->setTimestamp($open_timestamp);
        }

        $close_timestamp = $availability_entity->getCloseDate();
        if ($close_timestamp) {
          $date = new DrupalDateTime();
          $close_date = $date->setTimestamp($close_timestamp);
        }
      }

      $form['opigno_module_availability']['training_module_availability'] = [
        '#type' => 'fieldset',
        '#weight' => 2,
      ];

      $form['opigno_module_availability']['training_module_availability']['availability'] = [
        '#type' => 'radios',
        '#title' => $this->t('Module availability'),
        '#options' => [
          0 => $this->t('Always available'),
          1 => $this->t('Restrict to specific dates for that training'),
        ],
        '#default_value' => $availability,
      ];

      $form['opigno_module_availability']['training_module_availability']['training_module_availability_dates'] = [
        '#type' => 'fieldset',
        '#states' => [
          'visible' => [
            ':input[name="availability"]' => ['value' => 1],
          ],
        ],
      ];

      $form['opigno_module_availability']['training_module_availability']['training_module_availability_dates']['open_date_training_module'] = [
        '#title' => $this->t('Open date'),
        '#type' => 'datetime',
        '#default_value' => $open_date,
      ];
      $form['opigno_module_availability']['training_module_availability']['training_module_availability_dates']['close_date_training_module'] = [
        '#title' => $this->t('Close date'),
        '#type' => 'datetime',
        '#default_value' => $close_date,
      ];

      if (!empty($_POST['availability'])) {
        // Error classes for module availability dates fields
        // restricted to training.
        $open_date_empty = ((empty($_POST['open_date_training_module']['date']) || empty($_POST['open_date_training_module']['time'])) ||
          ($_POST['open_date_training_module']['date'] == '1970-01-01')) ? TRUE : FALSE;

        $close_date_empty = ((empty($_POST['close_date_training_module']['date']) || empty($_POST['close_date_training_module']['time'])) ||
          ($_POST['close_date_training_module']['date'] == '1970-01-01')) ? TRUE : FALSE;

        if ($open_date_empty) {
          $form['opigno_module_availability']['training_module_availability']['training_module_availability_dates']['open_date_training_module']['#attributes']['class'] = ['error'];
        }
        if ($close_date_empty) {
          $form['opigno_module_availability']['training_module_availability']['training_module_availability_dates']['close_date_training_module']['#attributes']['class'] = ['error'];
        }
      }
    }

    // Badges settings.
    $form['opigno_badges_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Badges settings'),
      '#attributes' => ['id' => 'badges-fieldset'],
      '#group' => 'advanced',
      '#weight' => 3,
    ];
    $form['badge_active']['#group'] = 'opigno_badges_settings';
    $form['opigno_badges_settings']['opigno_badges_settings_group'] = [
      '#type' => 'fieldset',
      '#weight' => 2,
    ];
    $form['badge_name']['#group'] = 'opigno_badges_settings_group';
    $form['badge_description']['#group'] = 'opigno_badges_settings_group';
    $form['badge_criteria']['#group'] = 'opigno_badges_settings_group';
    unset($form['badge_criteria']['widget']['#options']['_none']);
    $form['badge_media_image']['#group'] = 'opigno_badges_settings_group';

    // Module Skills settings.
    if ($moduleHandler->moduleExists('opigno_skills_system')) {
      $form['opigno_skills_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Skills settings'),
        '#attributes' => ['id' => 'skills-fieldset'],
        '#group' => 'advanced',
        '#weight' => 3,
      ];

      $form['skills_active']['#group'] = 'opigno_skills_settings';
      $form['skill_target']['#group'] = 'opigno_skills_settings';
      $form['module_global']['#group'] = 'opigno_skills_settings';

      $form['skill_target']['#states'] = [
        'visible' => [
          ':input[name="skills_active[value]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="skills_active[value]"]' => ['checked' => TRUE],
        ],
      ];

      $form['module_global']['#states'] = [
        'visible' => [
          ':input[name="skills_active[value]"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $form['skills_active']['#access'] = FALSE;
      $form['skill_target']['#access'] = FALSE;
      $form['module_global']['#access'] = FALSE;
    }

    // Module Availability options.
    $form['opigno_module_feedback'] = [
      '#type' => 'details',
      '#title' => $this->t('Result feedback'),
      '#attributes' => ['id' => 'feedback-fieldset'],
      '#group' => 'advanced',
      '#weight' => 4,
    ];
    // Module results feedback options.
    $results_options = $module->getResultsOptions();
    $form['opigno_module_feedback']['results_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Results feedback'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#attributes' => ['id' => 'multiple-takes-fieldset'],
      '#tree' => TRUE,
    ];
    for ($i = 0; $i < 5; $i++) {
      $form['opigno_module_feedback']['results_options'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Option %option_number', ['%option_number' => $i + 1]),
        '#collapsible' => TRUE,
        '#open' => FALSE,
        '#weight' => $i,
      ];
      // Open first option fields.
      if ($i == 0) {
        $form['opigno_module_feedback']['results_options'][$i]['#open'] = TRUE;
      }
      // Result option name.
      $form['opigno_module_feedback']['results_options'][$i]['option_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Range title'),
        '#default_value' => isset($results_options[$i]->{'option_name'}) ? $results_options[$i]->{'option_name'} : '',
        '#maxlength' => 40,
        '#size' => 40,
        '#description' => $this->t('e.g., "A" or "Passed"'),
      ];
      // Result option range (low and high).
      $form['opigno_module_feedback']['results_options'][$i]['option_start'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Percentage low'),
        '#description' => $this->t('Show this result for scored Module in this range (0-100).'),
        '#default_value' => isset($results_options[$i]->{'option_start'}) ? $results_options[$i]->{'option_start'} : '',
        '#size' => 5,
      ];
      $form['opigno_module_feedback']['results_options'][$i]['option_end'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Percentage high'),
        '#description' => $this->t('Show this result for scored Module in this range (0-100).'),
        '#default_value' => isset($results_options[$i]->{'option_end'}) ? $results_options[$i]->{'option_end'} : '',
        '#size' => 5,
      ];
      // Result option text.
      $form['opigno_module_feedback']['results_options'][$i]['option_summary'] = [
        '#type' => 'text_format',
        '#base_type' => 'textarea',
        '#title' => $this->t('Feedback'),
        '#default_value' => isset($results_options[$i]->{'option_summary'}) ? $results_options[$i]->{'option_summary'} : '',
        '#description' => $this->t("This is the text that will be displayed when the user's score falls in this range."),
        '#format' => isset($results_options[$i]->{'option_summary_format'}) ? $results_options[$i]->{'option_summary_format'} : NULL,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = &$this->entity;

    $status = parent::save($form, $form_state);

    $values = $form_state->getValues();
    $route_params = current(\Drupal::routeMatch()->getParameters());
    $module_id = $entity->id();
    $group_id = FALSE;
    $open_date = !empty($values['open_date_training_module']) ? $values['open_date_training_module']->getTimestamp() : 0;
    $close_date = !empty($values['close_date_training_module']) ? $values['close_date_training_module']->getTimestamp() : 0;

    if (isset($route_params['group'])) {
      if ($route_params['group']->getGroupType()->id() == 'opigno_course') {
        $build_info = $form_state->getBuildInfo();
        if (!empty($build_info['args'][0]['opigno_group_info']['learning_path'])) {
          $group_id = $build_info['args'][0]['opigno_group_info']['learning_path'];
        }
      }
      else {
        $group_id = $route_params['group']->id();
      }
    }

    if ($module_id && $group_id) {
      $availability_entity = LPModuleAvailability::loadByProperties([
        'group_id' => $group_id,
        'entity_id' => $module_id,
      ]);

      if (empty($availability_entity)) {
        try {
          $availability_entity = LPModuleAvailability::createWithValues(
            $group_id,
            $module_id,
            $values['availability'],
            $open_date,
            $close_date
          );
        }
        catch (\Exception $e) {
          \Drupal::logger('opigno_module')->error($e->getMessage());
          \Drupal::messenger()->addMessage($e->getMessage(), 'error');
        }
      }
      else {
        $availability_entity = current($availability_entity);

        $availability_entity->set('availability', $values['availability']);
        $availability_entity->set('open_date', $open_date);
        $availability_entity->set('close_date', $close_date);
      }

      if (!empty($availability_entity)) {
        try {
          $availability_entity->save();
        }
        catch (\Exception $e) {
          \Drupal::logger('opigno_module')->error($e->getMessage());
          \Drupal::messenger()->addMessage($e->getMessage(), 'error');
        }
      }
    }

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label Module.', [
          '%label' => $entity->label(),
        ]));
        /* @todo Find better way to save results options */
        // Save results options.
        $entity->insertResultsOptions($form_state);
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label Module.', [
          '%label' => $entity->label(),
        ]));
        /* @todo Find better way to save results options */
        // Save results options.
        $entity->updateResultsOptions($form_state);
    }
    $form_state->setRedirect('opigno_module.modules', ['opigno_module' => $entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();
    if (!empty($values['availability']) && $values['availability']) {
      // Validate module availability dates restricted to training.
      $open_date_empty = !is_object($values['open_date_training_module']) || empty($values['open_date_training_module']) || !$values['open_date_training_module']->getTimestamp();
      $close_date_empty = !is_object($values['close_date_training_module']) || empty($values['close_date_training_module']) || !$values['close_date_training_module']->getTimestamp();

      if ($open_date_empty) {
        $this->messenger()->addError($this->t('Open date of module availability should be set.'));
        $form_state->setError($form['open_date'], $this->t('The %field field is invalid. Please enter a date.', ['%field' => $form['open_date']['widget']['#title']]));
      }
      if ($close_date_empty) {
        $this->messenger()->addError($this->t('Open date of module availability should be set.'));
        $form_state->setError($form['close_date'], $this->t('The %field field is invalid. Please enter a date.', ['%field' => $form['close_date']['widget']['#title']]));
      }
    }

    if (isset($values['skills_active']) && $values['skills_active']['value'] == TRUE && empty($values['skill_target'])) {
      $form_state->setErrorByName('skill_target', $this->t('Target skill could not be empty.'));
    }

    if (!isset($values['uid'][0]['target_id'])) {
      // If the author doesn't exist.
      $values['uid'][0]['target_id'] = 0;
      $form_state->setValues($values);
    }
  }

}
