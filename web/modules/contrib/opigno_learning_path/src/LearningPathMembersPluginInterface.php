<?php

namespace Drupal\opigno_learning_path;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Interface ActivityAnswerPluginInterface.
 */
interface LearningPathMembersPluginInterface extends PluginInspectionInterface {

  /**
   * Get plugin id.
   */
  public function getId();

  /**
   * Get members form.
   *
   * @param array $form
   *   Form array.
   * @param mixed $form_state
   *   Form state.
   * @param mixed $current_user
   *   User object.
   *
   * @return mixed
   *   From.
   */
  public function getMembersForm(array &$form, FormStateInterface $form_state, User $current_user, \Closure $function, bool $hide = FALSE);

}
