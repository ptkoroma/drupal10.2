<?php

namespace Drupal\opigno_video\Plugin\ActivityAnswer;

use Drupal\opigno_module\ActivityAnswerPluginBase;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoAnswerInterface;

/**
 * Class VideoActivityAnswer.
 *
 * @ActivityAnswer(
 *   id="opigno_video",
 * )
 */
class VideoActivityAnswer extends ActivityAnswerPluginBase {

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
    // Set default max score.
    $score = 10;
    $activity = $answer->getActivity();

    // Get max score for activity.
    $db_connection = \Drupal::service('database');
    $score_query = $db_connection->select('opigno_module_relationship', 'omr')
      ->fields('omr', ['max_score'])
      ->condition('omr.parent_id', $answer->getModule()->id())
      ->condition('omr.parent_vid', $answer->getModule()->getRevisionId())
      ->condition('omr.child_id', $activity->id())
      ->condition('omr.child_vid', $activity->getRevisionId());
    $score_result = $score_query->execute()->fetchField();

    if ($score_result) {
      $score = $score_result;
    }

    return $score;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnswerResultItemHeaders(OpignoAnswerInterface $answer) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnswerResultItemData(OpignoAnswerInterface $answer) {
    return;
  }

}
