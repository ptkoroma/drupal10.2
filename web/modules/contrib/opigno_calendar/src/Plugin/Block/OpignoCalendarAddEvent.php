<?php

namespace Drupal\opigno_calendar\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Block with add calendar link.
 *
 * @Block(
 *  id = "opigno_calendar_add_event",
 *  admin_label = @Translation("Opigno calendar add event link"),
 *  category = @Translation("Opigno calendar"),
 * )
 *
 * @package Drupal\opigno_dashboard\Plugin\Block
 */
class OpignoCalendarAddEvent extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => t('Hello, World!'),
    ];
  }
}
