<?php

namespace Drupal\opigno_learning_path\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Component\Utility\EmailValidator;

/**
 * Members create form.
 */
class LearningPathCreateUserForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'learning_path_create_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="learning_path_create_user_form_messages" class="alert-danger"></div><div id="learning_path_create_user_form">';
    $form['#suffix'] = '</div>';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
      '#placeholder' => $this->t('Enter the learner name'),
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email Address'),
      '#placeholder' => $this->t('Enter the learner email'),
    ];

    $form['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create new user'),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'btn_create',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'opigno_learning_path/create_member';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate empty user name.
    if (empty($form_state->getValue('name'))) {
      $form_state->setErrorByName('name', $this->t('User name is required.'));
    }
    else {
      $others = $this->entityTypeManager()->getStorage('user')->loadByProperties([
        'name' => $form_state->getValue('name'),
      ]);
      // Validate used user name.
      if (count($others) > 0) {
        $form_state->setErrorByName('name', $this->t('The user name is in use, please enter another name.'));
      }
    }
    $email = $form_state->getValue('email');
    // Validate empty user email.
    if (empty($email)) {
      $form_state->setErrorByName('email', $this->t('User email is required.'));
    }
    else {
      $validator = new EmailValidator();
      if (!$validator->isValid($email)) {
        $form_state->setErrorByName('email', $this->t('Invalid email'));
      }
      else {
        $others = $this->entityTypeManager()->getStorage('user')->loadByProperties([
          'mail' => $email,
        ]);
        // Validate used user email.
        if (count($others) > 0) {
          $form_state->setErrorByName('email', $this->t('The email is in use, please enter another email.'));
        }
      }
    }
  }

  /**
   * Handles AJAX form submit.
   */
  public function submitFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Alerts container.
    $msg = [
      '#prefix' => '<div id="learning_path_create_user_form_messages" class="alert-danger">',
      '#suffix' => '</div>',
    ];

    if ($form_state->hasAnyErrors()) {
      // If we have errors render it.
      $msg['message'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
    }
    else {
      $form = \Drupal::formBuilder()->getForm('Drupal\opigno_learning_path\Form\LearningPathCreateMemberForm');

      $response->addCommand(new ReplaceCommand('#learning_path_create_user_form', $form));
    }
    // Clear or render alerts container.
    $response->addCommand(new ReplaceCommand('#learning_path_create_user_form_messages', $msg));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');

    // Create new user.
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $user = User::create();
    $user->enforceIsNew();
    $user->setUsername($name);
    $user->setPassword(\Drupal::service('password_generator')->generate());
    $user->setEmail($email);
    $user->set('init', $email);
    $user->set('langcode', $lang);
    $user->set('preferred_langcode', $lang);
    $user->set('preferred_admin_langcode', $lang);

    if ($user->hasField('field_created_by')) {
      $user->set('field_created_by', [
        'target_id' => \Drupal::currentUser()->id(),
      ]);
    }

    $user->activate();
    $user->save();

    // Notify user for creating account.
    _user_mail_notify('register_admin_created', $user);

    // Assign the user to the learning path.
    $group = $this->getRequest()->get('group');
    if ($group !== NULL) {
      $group->addMember($user);
    }

    $this->messenger()->addMessage($this->t('The new user has been created'));
  }

  /**
   * Get entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    return \Drupal::entityTypeManager();
  }
}
