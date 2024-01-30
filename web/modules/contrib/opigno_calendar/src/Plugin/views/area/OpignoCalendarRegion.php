<?php

namespace Drupal\opigno_calendar\Plugin\views\area;

use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a views area plugin.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("opigno_calendar_region")
 */
class OpignoCalendarRegion extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    $add_url = URL::fromRoute(
      'entity.opigno_calendar_event.add_form', ['opigno_calendar_event_type' => 'opigno_calendar_event']
    );

    $link = Link::fromTextAndUrl(t('Add'), $add_url)->toString();

    return [
      '#theme' => 'opigno_calendar_add_event',
      '#add_event_link' => $link,
    ];
  }
}
