<?php

namespace Drupal\opigno_module\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_module_result_link")
 */
class OpignoModuleResultLink extends LinkBase {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    $parameters = [
      'opigno_module' => $row->opigno_module_field_data_user_module_status_id,
      'user_module_status' => $row->id,
    ];
    return Url::fromRoute('opigno_module.module_result', $parameters);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('View');
  }

}
