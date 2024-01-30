<?php

namespace Drupal\opigno_long_answer\Plugin\ActivityAnswer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\opigno_module\ActivityAnswerPluginBase;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoAnswerInterface;

/**
 * Class LongAnswerActivityAnswer.
 *
 * @ActivityAnswer(
 *   id="opigno_long_answer",
 * )
 */
class LongAnswerActivityAnswer extends ActivityAnswerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function evaluatedOnSave(OpignoActivityInterface $activity) {
    // Check evaluation method field.
    $method = $activity->get('opigno_evaluation_method')->value;
    if ($method == 0) {
      // Automatic evaluation.
      return TRUE;
    }
    else {
      // Manual evaluation.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getScore(OpignoAnswerInterface $answer) {
    // Set default max score.
    $score = 10;
    $activity = $answer->getActivity();
    $method = $activity->get('opigno_evaluation_method')->value;

    if ($method == 0) {
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
    }
    return $score;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnswerResultItemHeaders(OpignoAnswerInterface $answer) {
    $headings = [$this->t('Your answer')];
    if (!$answer->isEvaluated()) {
      $headings[] = $this->t('Result');
    }
    return $headings;
  }

  /**
   * Returns answer result data.
   */
  public function getAnswerResultItemData(OpignoAnswerInterface $answer) {
    $data = [];
    $text = $answer instanceof EntityInterface && $answer->hasField('opigno_body') ? $answer->get('opigno_body')->getValue() : [];
    $text = is_array($text) ? $text[0]['value'] : '';
    $data['item'][] = strip_tags($text);

    if (!$answer->isEvaluated()) {
      $data['item'][] = $this->t('This answer has not yet been scored.');
    }

    return $data;
  }

}
