<?php

namespace Drupal\opigno_learning_path\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the LP settings form.
 *
 * @package Drupal\opigno_learning_path\Form
 */
class LearningPathAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'opigno_learning_path.learning_path_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'learning_path_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $tokens_description = [
      '#type' => 'container',
      [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Group tokens:'),
      ],
      [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          '[group] - ' . $this->t('group name'),
          '[link] - ' . $this->t('link to group'),
          '[user] - ' . $this->t('user account name'),
          '[user-role] - ' . $this->t('user role in group'),
          '[user-status] - ' . $this->t('user current status'),
        ],
      ],
    ];

    $config = $this->config('opigno_learning_path.learning_path_settings');

    $form['opigno_learning_path_mail'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('E-mail notifications settings'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify administrators'),
      '#description' => $this->t('If checked administrators will be notified on trainings updates.'),
      '#default_value' => $config->get('opigno_learning_path_notify_admin'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_admin_mails'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Administrators email list.'),
      '#default_value' => $config->get('opigno_learning_path_notify_admin_mails'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_admin_user_subscribed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to admins when user subscribed/updated/removed'),
      '#default_value' => $config->get('opigno_learning_path_notify_admin_user_subscribed'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_admin_user_approval'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to admins when user awaiting approval'),
      '#default_value' => $config->get('opigno_learning_path_notify_admin_user_approval'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_admin_user_blocked'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to admins when user blocked'),
      '#default_value' => $config->get('opigno_learning_path_notify_admin_user_blocked'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_group_tokens_1'] = $tokens_description;
    $form['opigno_learning_path_mail']['token_tree_1'] = [
      '#theme' => 'token_tree_link',
      '#show_restricted' => TRUE,
    ];

    $form['opigno_learning_path_mail']['opigno_learning_path_notify_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify users'),
      '#description' => $this->t('If checked users will be notified on training updates.'),
      '#default_value' => $config->get('opigno_learning_path_notify_users'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_user_user_subscribed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to user when he subscribed/updated/removed'),
      '#default_value' => $config->get('opigno_learning_path_notify_user_user_subscribed'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_user_user_approval'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to user when he awaiting approval'),
      '#default_value' => $config->get('opigno_learning_path_notify_user_user_approval'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_user_user_blocked'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to user when he blocked'),
      '#default_value' => $config->get('opigno_learning_path_notify_user_user_blocked'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_group_tokens_2'] = $tokens_description;
    $form['opigno_learning_path_mail']['token_tree_2'] = [
      '#theme' => 'token_tree_link',
      '#show_restricted' => TRUE,
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_user_user_certificate_expired'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to user when a training certificate has expired'),
      '#default_value' => $config->get('opigno_learning_path_notify_user_user_certificate_expired'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_notify_group_tokens_3'] = [
      '#type' => 'container',
      [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Group tokens:'),
      ],
      [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          '[group:title] - ' . $this->t('group name'),
          '[group:url] - ' . $this->t('link to group'),
          '[group:expiration_date] - ' . $this->t('certificate expiration date'),
        ],
      ],
    ];
    $form['opigno_learning_path_mail']['token_tree_3'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['group'],
      '#show_restricted' => TRUE,
    ];

    $form['opigno_learning_path_mail']['opigno_learning_path_student_does_activity_notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify manager'),
      '#description' => $this->t('If checked a manager will be notified when student does an activity.'),
      '#default_value' => $config->get('opigno_learning_path_student_does_activity_notify'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_student_does_activity'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to a manager when student does an activity.'),
      '#default_value' => $config->get('opigno_learning_path_student_does_activity'),
    ];
    $form['opigno_learning_path_mail']['student_does_activity_tokens'] = [
      '#type' => 'container',
      [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Tokens:'),
      ],
      [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          '[module] - ' . $this->t('module name'),
          '[link] - ' . $this->t('link to evaluate'),
          '[manager] - ' . $this->t('manager name'),
          '[user] - ' . $this->t('user name'),
          '[sitename] - ' . $this->t('site name'),
        ],
      ],
    ];

    $form['opigno_learning_path_mail']['opigno_learning_path_students_answer_is_reviewed_notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify student'),
      '#description' => $this->t('If checked a student will be notified when student does an activity.'),
      '#default_value' => $config->get('opigno_learning_path_students_answer_is_reviewed_notify'),
    ];
    $form['opigno_learning_path_mail']['opigno_learning_path_students_answer_is_reviewed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to a student when his answer has been reviewed.'),
      '#default_value' => $config->get('opigno_learning_path_students_answer_is_reviewed'),
    ];
    $form['opigno_learning_path_mail']['students_answer_is_reviewed_tokens'] = [
      '#type' => 'container',
      [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Tokens:'),
      ],
      [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          '[module] - ' . $this->t('module name'),
          '[manager] - ' . $this->t('manager name'),
          '[user] - ' . $this->t('user name'),
          '[sitename] - ' . $this->t('site name'),
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('opigno_learning_path.learning_path_settings')
      ->set('opigno_learning_path_notify_admin', $form_state->getValue('opigno_learning_path_notify_admin'))
      ->set('opigno_learning_path_notify_admin_mails', $form_state->getValue('opigno_learning_path_notify_admin_mails'))
      ->set('opigno_learning_path_notify_users', $form_state->getValue('opigno_learning_path_notify_users'))
      ->set('opigno_learning_path_notify_admin_user_subscribed', $form_state->getValue('opigno_learning_path_notify_admin_user_subscribed'))
      ->set('opigno_learning_path_notify_admin_user_approval', $form_state->getValue('opigno_learning_path_notify_admin_user_approval'))
      ->set('opigno_learning_path_notify_admin_user_blocked', $form_state->getValue('opigno_learning_path_notify_admin_user_blocked'))
      ->set('opigno_learning_path_notify_user_user_subscribed', $form_state->getValue('opigno_learning_path_notify_user_user_subscribed'))
      ->set('opigno_learning_path_notify_user_user_approval', $form_state->getValue('opigno_learning_path_notify_user_user_approval'))
      ->set('opigno_learning_path_notify_user_user_blocked', $form_state->getValue('opigno_learning_path_notify_user_user_blocked'))
      ->set('opigno_learning_path_notify_user_user_certificate_expired', $form_state->getValue('opigno_learning_path_notify_user_user_certificate_expired'))
      ->set('opigno_learning_path_student_does_activity_notify', $form_state->getValue('opigno_learning_path_student_does_activity_notify'))
      ->set('opigno_learning_path_student_does_activity', $form_state->getValue('opigno_learning_path_student_does_activity'))
      ->set('opigno_learning_path_students_answer_is_reviewed_notify', $form_state->getValue('opigno_learning_path_students_answer_is_reviewed_notify'))
      ->set('opigno_learning_path_students_answer_is_reviewed', $form_state->getValue('opigno_learning_path_students_answer_is_reviewed'))
      ->save();
  }

}
