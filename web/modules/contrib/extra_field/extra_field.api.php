<?php

/**
 * @file
 * Extra Field API documentation.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Defines Extra Field Display types.
 *
 * Extra Field API Displays and Forms allow developers to add display-only
 * fields and form elements to entities. The entities and entity bundle(s) for
 * which the plugin is available is determined with the the 'bundles' key.
 * Site builders can use extra fields as normal field on an entity form or
 * display view mode.
 *
 * ExtraFieldDisplay are plugins managed by the
 * \Drupal\extra_field\Plugin\ExtraFieldDisplayManager class.
 * An ExtraFieldDisplay is a plugin annotated with class
 * \Drupal\extra_field\Annotation\ExtraFieldDisplay and implements
 * \Drupal\extra_field\Plugin\ExtraFieldDisplayInterface (in most cases, by
 * extending one of the base classes). ExtraFieldDisplay plugins need to
 * be in the namespace \Drupal\{your_module}\Plugin\ExtraField\Display.
 *
 * ExtraFieldForm are plugins managed by the
 * \Drupal\extra_field\Plugin\ExtraFieldFormManager class.
 * An ExtraFieldForm is a plugin annotated with class
 * \Drupal\extra_field\Annotation\ExtraFieldForm and implements
 * \Drupal\extra_field\Plugin\ExtraFieldFormInterface (in most cases, by
 * extending ExtraFieldFormBase). ExtraFieldForm plugins need to
 * be in the namespace \Drupal\{your_module}\Plugin\ExtraField\Form.
 *
 * @see plugin_api
 */

/**
 * Performs alteration on Extra Field Display plugins.
 *
 * @param array $info
 *   An array of information of existing Extra Field Displays, as collected by
 *   the annotation discovery mechanism.
 */
function hook_extra_field_display_info_alter(array &$info) {

  // Let the all_nodes plugin also be used on all taxonomy terms.
  if (isset($info['all_nodes'])) {
    $info['all_nodes']['bundles'][] = 'taxonomy_term.*';
  }
}

/**
 * Performs alteration on Extra Field Form plugins.
 *
 * @param array $info
 *   An array of information of existing Extra Field Form, as collected by the
 *   annotation discovery mechanism.
 */
function hook_extra_field_form_info_alter(array &$info) {

  // Let the all_nodes plugin also be used on all taxonomy terms.
  if (isset($info['all_nodes'])) {
    $info['all_nodes']['bundles'][] = 'taxonomy_term.*';
  }
}

/**
 * @} End of "addtogroup hooks".
 */
