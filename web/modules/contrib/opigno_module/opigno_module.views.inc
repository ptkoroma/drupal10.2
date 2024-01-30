<?php

/**
 * @file
 * Opigno module views custom implementation.
 */

/**
 * Implements hook_views_data_alter().
 */
function opigno_module_views_data_alter(array &$data) {
  $data['user_module_status']['opigno_module_evaluation'] = [
    'title' => t('Modules required manual evaluation'),
    'filter' => [
      'title' => t('Modules required manual evaluation'),
      'help' => t('Modules which require manual evaluation filter'),
      'field' => 'module',
      'id' => 'opigno_module_manual_evaluation',
    ],
  ];

  $data['user_module_status']['finished'] = [
    'title' => t('Module finished date'),
    'field' => [
      'title' => t('Module finished date'),
      'help' => t('Provides a custom field for modules by finished date.'),
      'id' => 'opigno_module_finished',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['user_module_status']['opigno_module_result_link'] = [
    'title' => t('Opigno module result link'),
    'help' => t('Provides link to the module result.'),
    'field' => [
      'id' => 'opigno_module_result_link',
    ],
  ];

  $data['user_module_status']['opigno_module_score_link'] = [
    'title' => t('Opigno module score link'),
    'help' => t('Provides link to the module score.'),
    'field' => [
      'id' => 'opigno_module_score_link',
    ],
  ];

  $data['user_module_status']['opigno_module_result_actions'] = [
    'title' => t('Opigno module result actions'),
    'help' => t('Provides custom action for Opigno module result.'),
    'field' => [
      'id' => 'opigno_module_result_actions',
    ],
  ];
}
