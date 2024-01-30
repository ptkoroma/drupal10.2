<?php

namespace Drupal\aristotle;

use Drupal\Core\Render\Element\RenderCallbackInterface;

/**
 * Use pre-render to alter color renderable array.
 */
class ThemeSettingsPreRender implements RenderCallbackInterface {

  /**
   * Prerender callback.
   */
  public static function preRender($build) {
    unset(
      $build["color"]["palette"]["base"],
      $build["color"]["palette"]["text"],
      $build["color"]["palette"]["link"]
    );

    $build["color"]["palette"]['primary_button_color']['#prefix'] = '<h4>' . t('Mobile app') . '</h4>';
    $build["color"]["palette"]['navigation_background_color']['#suffix'] = '<h4>' . t('Web app') . '</h4>';
    return $build;
  }

}
