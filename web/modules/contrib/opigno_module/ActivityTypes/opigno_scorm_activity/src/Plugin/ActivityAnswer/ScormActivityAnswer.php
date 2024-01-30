<?php

namespace Drupal\opigno_scorm_activity\Plugin\ActivityAnswer;

use Drupal\opigno_module\ActivityAnswerPluginBase;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoAnswerInterface;

/**
 * Defines the SCORM activity answer plugin.
 *
 * @ActivityAnswer(
 *   id="opigno_scorm",
 * )
 */
class ScormActivityAnswer extends ActivityAnswerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function evaluatedOnSave(OpignoActivityInterface $activity) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore(OpignoAnswerInterface $answer) {
    $db_connection = \Drupal::service('database');
    $score = 0;
    $user = \Drupal::currentUser();
    $activity = $answer->getActivity();
    $scorm_controller = \Drupal::service('opigno_scorm.scorm');
    $scorm_file = $activity->get('opigno_scorm_package')->entity;
    $scorm = $scorm_controller->scormLoadByFileEntity($scorm_file);

    if (!$scorm) {
      return $score;
    }

    // Get SCORM API version.
    $metadata = unserialize($scorm->metadata, ['allowed_classes' => FALSE]);
    if (str_contains($metadata['schemaversion'], '1.2')) {
      $scorm_version = '1.2';
      $completion_key = 'cmi.core.lesson_status';
      $raw_key = 'cmi.core.score.raw';
      $max_key = 'cmi.core.score.max';
      $min_key = 'cmi.core.score.min';
    }
    else {
      $scorm_version = '2004';
      $completion_key = 'cmi.completion_status';
      $raw_key = 'cmi.score.raw';
      $max_key = 'cmi.score.max';
      $min_key = 'cmi.score.min';
    }

    // We get the latest result.
    // The way the SCORM API works always overwrites attempts
    // for the global CMI storage.
    // The result stored is always the latest.
    // Get it, and presist it again in the user results table
    // so we can track results through time.
    $scaled = opigno_scorm_scorm_cmi_get($user->id(), $scorm->id, 'cmi.score.scaled', '');
    $raw = opigno_scorm_scorm_cmi_get($user->id(), $scorm->id, $raw_key, '');
    $max = opigno_scorm_scorm_cmi_get($user->id(), $scorm->id, $max_key, '');
    $min = opigno_scorm_scorm_cmi_get($user->id(), $scorm->id, $min_key, '');
    $min = !empty($min) ? $min : 0;
    $completion = opigno_scorm_scorm_cmi_get($user->id(), $scorm->id, $completion_key, '');

    if ((isset($raw) && is_numeric($raw)) && ($scorm_version == '1.2' || ($scorm_version == '2004' && (!isset($scaled) || !is_numeric($scaled))))) {
      if (empty($max)) {
        $scaled = $raw / 100;
      }
      elseif ($max > $min) {
        $scaled = ($raw - $min) / ($max - $min);
      }
    }

    if (empty($scaled) || !is_numeric($scaled)) {
      if (!empty($completion) && in_array($completion, [
        "completed",
        "passed",
      ])) {
        $scaled = 1;
      }
      else {
        $scaled = 0;
      }
    }

    $score_query = $db_connection->select('opigno_module_relationship', 'omr')
      ->fields('omr', ['max_score'])
      ->condition('omr.parent_id', $answer->getModule()->id())
      ->condition('omr.parent_vid', $answer->getModule()->getRevisionId())
      ->condition('omr.child_id', $activity->id())
      ->condition('omr.child_vid', $activity->getRevisionId());
    $score_result = $score_query->execute()->fetchObject();
    if ($score_result) {
      $score = $score_result->max_score * $scaled;
    }
    return $score;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnswerResultItemHeaders() {
    return [
      $this->t('Your answer'),
      $this->t('Choice'),
      $this->t('Correct?') . '  ',
      $this->t('Score'),
      $this->t('Correct answer'),
    ];
  }

  /**
   * Returns answer result data.
   */
  public function getAnswerResultItemData(OpignoAnswerInterface $answer) {
    $db_connection = \Drupal::service('database');
    $interactions = $db_connection->select('opigno_scorm_user_answer_results', 'osur')
      ->fields('osur')
      ->condition('answer_id', $answer->id())
      ->condition('answer_vid', $answer->getLoadedRevisionId())
      ->orderBy('id', 'ASC')
      ->execute()->fetchAll();
    if ($interactions) {
      return $interactions;
    }
    return FALSE;
  }

}
