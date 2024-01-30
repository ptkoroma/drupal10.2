<?php

namespace Drupal\opigno_learning_path\Plugin\LearningPathMembers;

use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_learning_path\LearningPathMembersPluginBase;
use Drupal\user\Entity\User;

/**
 * Class MembersPlugin.
 *
 * @LearningPathMembers(
 *   id="members_plugin",
 * )
 */
class MembersPlugin extends LearningPathMembersPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getMembersForm(array &$form, FormStateInterface $form_state, User $current_user, \Closure $function, bool $hide = FALSE) {
    $storage = $form_state->getStorage();

    // Add filters for the members field.
    $form['members'] = [
      '#type' => 'container',
      '#weight' => 100,
    ];

    $form['members']['title'] = [
      '#type' => 'label',
      '#title' => t('Members'),
    ];

    $users = call_user_func_array($function, [$current_user]);
    $allowed_uids = [];

    foreach ($users as $user) {
      $allowed_uids[] = $user->id();
    }

    if ($allowed_uids) {
      $allowed_uids = array_unique($allowed_uids);

      // Save to form storage.
      $storage['allowed_uids'] = $allowed_uids;

      // Filter allowed users.
      if ($options = $form["field_calendar_event_members"]["widget"]["#options"]) {
        foreach ($options as $key => $option) {
          if (!in_array($key, $allowed_uids)) {
            unset($form["field_calendar_event_members"]["widget"]["#options"][$key]);
          }
        }
      }
    }

    $form['members']['field_calendar_event_members'] = $form['field_calendar_event_members'];
    unset($form['field_calendar_event_members']);

    $members = &$form['members']['field_calendar_event_members'];
    $members["widget"]['#type'] = 'entity_selector';
    $members['#prefix'] = '<div id="members">';
    $members['#suffix'] = '</div>';
    unset($members['widget']['#title']);

    $form_state->setStorage($storage);

    if (!$current_user->hasPermission('add members to calendar event')) {
      // Hide calendar events members field.
      if (!empty($form["field_calendar_event_members"])) {
        $form["field_calendar_event_members"]["#access"] = FALSE;
      }
      if (!empty($form['members'])) {
        $form['members']['#access'] = FALSE;
      }
    }
  }

}
