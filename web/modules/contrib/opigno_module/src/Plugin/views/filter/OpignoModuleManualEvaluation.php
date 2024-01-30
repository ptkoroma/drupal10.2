<?php

namespace Drupal\opigno_module\Plugin\views\filter;

use Drupal\Core\Database\Database;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter handler to show modules required manual evaluation filter.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsFilter("opigno_module_manual_evaluation")
 */
class OpignoModuleManualEvaluation extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $query = Database::getConnection()->select('opigno_answer_field_data', 'anf');
    $query->join('opigno_activity__opigno_evaluation_method', 'em', 'em.entity_id = anf.activity');
    $query->join('user_module_status', 'ums', 'ums.module = anf.module AND ums.user_id = anf.user_id AND ums.id = anf.user_module_status');

    $query->fields('ums', ['id']);

    $query->condition('anf.type', ['opigno_file_upload', 'opigno_long_answer'], 'IN');
    $query->condition('em.opigno_evaluation_method_value', '1', '=');
    $query->condition('ums.evaluated', '0', '=');

    $query->distinct();

    $group_and = $this->query->setWhereGroup('AND');
    $this->query->addWhere($group_and, 'user_module_status.id', $query, 'IN');
  }

}
