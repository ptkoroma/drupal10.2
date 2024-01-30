<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Defines the base implementation of the community extra field.
 *
 * @package Drupal\opigno_social_community\Plugin\ExtraField\Display
 */
abstract class CommunityExtraFieldBase extends ExtraFieldDisplayFormattedBase {

  /**
   * Marks the field as empty.
   *
   * @return array
   *   The empty field data.
   */
  protected function emptyField(): array {
    $this->isEmpty = TRUE;
    return ['#cache' => ['max-age' => 0]];
  }

}
