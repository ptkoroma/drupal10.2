<?php

namespace Drupal\opigno_module;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoAnswerInterface;

/**
 * Defines the base interface for the activity answer plugin.
 *
 * @package Drupal\opigno_module
 */
interface ActivityAnswerPluginInterface extends PluginInspectionInterface {

  /**
   * Get plugin id.
   */
  public function getId();

  /**
   * Get plugin activity type.
   */
  public function getActivityType();

  /**
   * Indicates if answer should me evaluated on save or not.
   */
  public function evaluatedOnSave(OpignoActivityInterface $activity);

  /**
   * Score logic for specified activity.
   */
  public function getScore(OpignoAnswerInterface $answer);

  /**
   * Modify answering form.
   */
  public function answeringForm(array &$form);

  /**
   * The extra submit action for the plugin.
   *
   * @param array $form
   *   The answer form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\opigno_module\Entity\OpignoAnswerInterface $answer
   *   The Opigno answer entity to be updated.
   */
  public function answeringFormSubmit(array &$form, FormStateInterface $form_state, OpignoAnswerInterface $answer): void;

}
