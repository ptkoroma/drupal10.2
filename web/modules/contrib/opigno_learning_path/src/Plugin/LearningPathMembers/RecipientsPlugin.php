<?php

namespace Drupal\opigno_learning_path\Plugin\LearningPathMembers;

use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_learning_path\LearningPathMembersPluginBase;
use Drupal\user\Entity\User;

/**
 * Class RecipientsPlugin.
 *
 * @LearningPathMembers(
 *   id="recipients_plugin",
 * )
 */
class RecipientsPlugin extends LearningPathMembersPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getMembersForm(array &$form, FormStateInterface $form_state, User $current_user, \Closure $function, bool $hide = FALSE) {
    $users = call_user_func_array($function, [$current_user]);
    $options = [];

    foreach ($users as $user) {
      $options[$user->id()] = $user->getDisplayName();
    }

    // Remove the current users from the list of users
    // that once can send a message to.
    if (isset($options[$current_user->id()])) {
      unset($options[$current_user->id()]);
    }

    // Sort the users by name.
    uasort($options, 'strcasecmp');

    $form['users_to_send'] = [
      '#title' => t('Select the users you want to send a message to'),
      '#type' => 'entity_selector',
      '#options' => $options,
      '#weight' => -1,
      '#multiple' => TRUE,
      '#prefix' => $hide ? '<div id="users-to-send" class="hidden">' : '<div id="users-to-send">',
      '#suffix' => '</div>',
    ];
  }

}
