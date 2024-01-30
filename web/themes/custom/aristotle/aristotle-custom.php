<?php

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteProvider;

/**
 * @file
 * Aristotle custom theme settings.
 */

/**
 * Implements hook_theme_suggestions_form_element().
 */
function aristotle_theme_suggestions_form_element_alter(array &$suggestions, array $variables) {
  
  if (isset($variables['element']['#type']) && $variables['element']['#type'] == "managed_file") {
    //dump($suggestions);
    //dump($variables);
    $suggestions[] = 'form_element__' . $variables['element']['#type'];
  }

  return $suggestions;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function aristotle_preprocess_views_view__opigno_training_catalog(&$variables) {
  // Add the "Create new training" link.
  $new_training_url = Url::fromRoute('entity.group.add_form', ['group_type' => 'learning_path']);
  if ($new_training_url->access()) {
    $options = [
      'attributes' => [
        'class' => ['btn', 'btn-bg', 'btn-rounded'],
      ],
    ];
    $new_training_url->setOptions($options);
    $variables['new_training_link'] = Link::fromTextAndUrl(t('Create new Training'), $new_training_url);
  }

  // Add the "Create new non-catalogued training" link.
  // node.add will be replaced with entity.node.add_form in Drupal 9.5.x and 10.x
  $new_non_cat_training_url = Url::fromRoute('node.add', ['node_type' => 'non_catalogued_activity']);
  if ($new_non_cat_training_url->access()) {
    $options = [
      'attributes' => [
        'class' => ['btn', 'btn-bg', 'btn-rounded'],
      ],
    ];
    $new_non_cat_training_url->setOptions($options);
    $variables['new_non_cat_training_link'] = Link::fromTextAndUrl(t('Create Non-Catalogued training'), $new_non_cat_training_url);
  }
}
