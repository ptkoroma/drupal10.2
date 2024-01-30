<?php

namespace Drupal\opigno_module\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_module_score_link")
 */
class OpignoModuleScoreLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\opigno_module\Entity\OpignoActivity $activities */
    $activities = $values->_relationship_entities["module"]->getModuleActivities();
    $activities = array_column((array) $activities, 'type');
    $manually_scored_activities = [
      'opigno_file_upload',
      'opigno_long_answer',
    ];
    $correct_activity = !empty(array_intersect($manually_scored_activities, $activities));
    $parameters = [
      'opigno_module' => $values->opigno_module_field_data_user_module_status_id,
      'user_module_status' => $values->id,
    ];
    return $correct_activity ? Link::fromTextAndUrl($this->t('Score'), Url::fromRoute('opigno_module.module_result_form', $parameters))->toString() : '';
  }

}
