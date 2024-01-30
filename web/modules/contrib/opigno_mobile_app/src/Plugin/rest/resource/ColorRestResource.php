<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource to get a Theme colors.
 *
 * @RestResource(
 *   id = "color_rest_resource",
 *   label = @Translation("Color rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/color",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/color"
 *   }
 * )
 */
class ColorRestResource extends ResourceBase {

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    $theme = \Drupal::theme()->getActiveTheme()->getName();
    $theme_decorator = \Drupal::hasService('color.theme_decorator');
    if ($theme_decorator) {
      $color_palette = \Drupal::service('color.theme_decorator')
        ->getPalette($theme);
    }
    else {
      $color_palette = color_get_palette($theme);
    }
    $current_scheme = \Drupal::configFactory()->getEditable('color.theme.aristotle')->get('palette');
    $colors_set = $color_palette;
    if (!empty($current_scheme)) {
      $colors_set = $current_scheme;
    }
    return new ResourceResponse($colors_set);
  }

}
