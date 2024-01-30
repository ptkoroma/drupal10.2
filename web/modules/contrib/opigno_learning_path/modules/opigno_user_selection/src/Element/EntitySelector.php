<?php

namespace Drupal\opigno_user_selection\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element\Select;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;

/**
 * Provides a form element for entity selection.
 *
 * `#option_group` - allows you not to specify a list of options explicitly
 *                 (in this case the #option array will be overridden) .
 *
 * @FormElement("entity_selector")
 */
class EntitySelector extends Select {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#sort_options' => FALSE,
      '#sort_start' => NULL,
      '#process' => [
        [$class, 'processSelect'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderSelect'],
      ],
      '#theme' => 'entity_selector',
      '#theme_wrappers' => ['form_element'],
      '#options' => [],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    if (
      !empty($element['#entity_selector_option']) &&
      !empty($element['#entity_selector_parameters']) &&
      ($group = $element['#entity_selector_parameters']['group']) instanceof Group
    ) {
      /** @var \Drupal\opigno_user_selection\UserSelectionHelper $controller */
      /** @var \Drupal\Core\Controller\ControllerResolver $controller_resolver */
      $controller_resolver = \Drupal::service('controller_resolver');
      $callable = $controller_resolver->getControllerFromDefinition($element['#entity_selector_option']);
      list($options, $default) = call_user_func_array($callable, [$group]);
      $element["#options"] = array_map(static function ($value) {
        return $value['value'];
      }, $options);
      if (empty($element['#show_exists'])) {
        $element["#options"] = array_filter($element["#options"], static function ($value, $key) use ($default) {
          return !in_array($key, $default, TRUE);
        }, ARRAY_FILTER_USE_BOTH);
        $element['#default_value'] = [];
      }
      elseif (!is_null($default) && !isset($element['#default_value'])) {
        $element['#default_value'] = $default;
      }
    }
    unset($element["#options"]["_none"]);
    return parent::valueCallback($element, $input, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderSelect($element) {
    $element = parent::preRenderSelect($element);
    $element['#attached']['library'] = ['opigno_user_selection/userselect'];
    $element['#attributes']['data-user-load'] = TRUE;
    if (empty($element['#entity_selector_route_name'])) {
      $element['#entity_selector_route_name'] = 'user_selection_list';
    }
    $url = Url::fromRoute($element['#entity_selector_route_name'], [])
      ->toString(TRUE);
    /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
    $access_manager = \Drupal::service('access_manager');
    $access = $access_manager->checkNamedRoute($element['#entity_selector_route_name'], [], \Drupal::currentUser(), TRUE);

    if ($access) {
      $metadata = BubbleableMetadata::createFromRenderArray($element);
      if ($access->isAllowed()) {
        $element['#attributes']['class'][] = 'form-autocomplete';
        $metadata->addAttachments(['library' => ['opigno_user_selection/userselect']]);
        // Provide a data attribute for the JavaScript behavior to bind to.
        $element['#attributes']['autocomplete-path'] = $url->getGeneratedUrl();
        $metadata = $metadata->merge($url);
      }
      $metadata
        ->merge(BubbleableMetadata::createFromObject($access))
        ->applyTo($element);
    }

    if (isset($element['#data_type'])) {
      $element['#attributes']['type'] = $element['#data_type'];
    }

    return $element;
  }

}
