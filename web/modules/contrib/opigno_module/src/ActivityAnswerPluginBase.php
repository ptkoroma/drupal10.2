<?php

namespace Drupal\opigno_module;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoAnswerInterface;

/**
 * Defines the base class for activity answer plugins.
 *
 * @package Drupal\opigno_module
 */
abstract class ActivityAnswerPluginBase extends PluginBase implements ActivityAnswerPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getActivityType() {
    return $this->pluginDefinition['activityTypeBundle'];
  }

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
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function answeringForm(array &$form) {}

  /**
   * {@inheritdoc}
   */
  public function answeringFormSubmit(array &$form, FormStateInterface $form_state, OpignoAnswerInterface $answer): void {}

}
