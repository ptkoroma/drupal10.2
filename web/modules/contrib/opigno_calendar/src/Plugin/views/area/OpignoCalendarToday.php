<?php

namespace Drupal\opigno_calendar\Plugin\views\area;

use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Defines a views area plugin.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("opigno_calendar_today")
 */
class OpignoCalendarToday extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    return [
      '#theme' => 'opigno_calendar_today',
    ];
  }
}
