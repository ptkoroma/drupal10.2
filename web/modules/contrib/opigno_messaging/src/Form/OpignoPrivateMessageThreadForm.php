<?php

namespace Drupal\opigno_messaging\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\opigno_learning_path\LearningPathMembersManager;
use Drupal\opigno_learning_path\Plugin\LearningPathMembers\RecipientsPlugin;
use Drupal\opigno_messaging\Services\OpignoMessageThread;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom form to create/edit private message thread.
 *
 * @package Drupal\opigno_messaging\Form
 */
class OpignoPrivateMessageThreadForm extends FormBase {

  /**
   * The current user ID.
   *
   * @var int
   */
  protected $currentUid;

  /**
   * The loaded current user entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $currentUser = NULL;

  /**
   * The PM thread entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $threadStorage = NULL;

  /**
   * The PM entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $messageStorage = NULL;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Learning path members manager service.
   *
   * @var \Drupal\opigno_learning_path\LearningPathMembersManager
   */
  protected $lpMembersManager;

  /**
   * Opigno PM manager service.
   *
   * @var \Drupal\opigno_messaging\Services\OpignoMessageThread
   */
  protected $pmService;

  /**
   * PM thread view builder service.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $threadViewBuilder;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * OpignoPrivateMessageThreadForm constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\opigno_learning_path\LearningPathMembersManager $lp_members_manager
   *   The LP members manager service.
   * @param \Drupal\opigno_messaging\Services\OpignoMessageThread $pm_service
   *   The private messages manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    LearningPathMembersManager $lp_members_manager,
    OpignoMessageThread $pm_service,
    ModuleHandlerInterface $module_handler
  ) {
    $this->currentUid = (int) $account->id();
    $this->dateFormatter = $date_formatter;
    $this->lpMembersManager = $lp_members_manager;
    $this->pmService = $pm_service;
    $this->threadViewBuilder = $entity_type_manager->getViewBuilder('private_message_thread');
    $this->moduleHandler = $module_handler;

    try {
      $this->threadStorage = $entity_type_manager->getStorage('private_message_thread');
      $this->messageStorage = $entity_type_manager->getStorage('private_message');
      $this->currentUser = $entity_type_manager->getStorage('user')->load($this->currentUid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_messaging_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('opigno_learning_path.members.manager'),
      $container->get('opigno_messaging.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_pm_thread_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $tid = 0) {
    $plugin_instance = $this->lpMembersManager->createInstance('recipients_plugin');
    if (!$this->currentUser instanceof UserInterface || !$plugin_instance instanceof RecipientsPlugin) {
      return [];
    }

    // Don't render form for existing 1-to-1 messages.
    $thread = NULL;
    if ($tid && $this->threadStorage instanceof EntityStorageInterface) {
      $thread = $this->threadStorage->load($tid);
      if (!$thread instanceof PrivateMessageThreadInterface || count($thread->getMembers()) <= 2) {
        return [];
      }
    }

    // Check if the thread is a group discussion.
    $is_group = $thread instanceof PrivateMessageThreadInterface
      && $thread->hasField('field_create_group')
      && $thread->get('field_create_group')->getString();

    $form['#attributes']['class'] = $is_group ? ['opigno-pm-thread-form__edit'] : ['opigno-pm-thread-form__add'];

    // Add the placeholder for the status messages.
    $form['status_messages_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['opigno-status-messages-container'],
      ],
      '#weight' => -50,
    ];

    // Add the "Create a group" checkbox.
    $form['create_group'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create a group'),
      '#default_value' => $is_group,
      '#weight' => -20,
      '#attributes' => [
        'class' => ['checkbox-slider'],
      ],
    ];

    // Hide the checkbox on the thread editing form.
    if ($is_group) {
      $form['create_group']['#prefix'] = '<div class="hidden">';
      $form['create_group']['#suffix'] = '</div>';
    }

    $form['group_data_container'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      '#states' => [
        'visible' => [':input[name="create_group"]' => ['checked' => TRUE]],
      ],
      '#weight' => -15,
    ];

    // Add the subject.
    $form['group_data_container']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#placeholder' => $this->t('Enter a subject'),
      '#default_value' => $is_group ? $thread->get('field_pm_subject')->getString() : '',
      '#maxlength' => 128,
    ];

    // The group picture field.
    $timestamp = strtotime('now');
    $year = $this->dateFormatter->format($timestamp, 'custom', 'Y');
    $month = $this->dateFormatter->format($timestamp, 'custom', 'm');
    $form['group_data_container']['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Picture'),
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
      ],
      '#upload_location' => "public://{$year}-{$month}",
      '#theme' => 'image_widget',
      '#preview_image_style' => 'private_message_group_upload',
      '#default_value' => $is_group && !$thread->get('field_image')->isEmpty() ? [$thread->get('field_image')->target_id] : '',
      '#multiple' => FALSE,
    ];

    // Add members selection tool and message field.
    $plugin_instance->getMembersForm($form, $form_state, $this->currentUser, function ($current_user) {
      // Get the groups, this allows to filter the available users.
      $show_all = $current_user->hasPermission('message anyone regardless of groups');
      return opigno_messaging_get_all_recipients($show_all);
    }, $is_group);
    if ($is_group) {
      $form['users_to_send']['#default_value'] = $thread->getMembersId();

      // Display the extra checkbox to manage members on thread edit form.
      $form['edit_members'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Manage participants'),
        '#ajax' => [
          'callback' => '::showMembersAjax',
          'event' => 'change',
        ],
        '#weight' => -5,
        '#attributes' => [
          'class' => ['checkbox-slider'],
        ],
      ];
    }

    if (!$tid) {
      $form['message'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Message'),
        '#title_display' => 'invisible',
        '#format' => 'basic_html',
        '#required' => TRUE,
      ];
    }

    // Actions.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $is_group ? $this->t('Save') : $this->t('Send'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
      '#attributes' => [
        'class' => ['use-ajax-submit'],
      ],
    ];

    // Enable honeypot protection.
    if ($this->moduleHandler->moduleExists('honeypot')) {
      \Drupal::service('honeypot')->addFormProtection($form, $form_state, [
        'honeypot',
        'time_restriction',
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $selected_users = $form_state->getValue('users_to_send', []);
    // Display the error message if fields are empty.
    if (!$selected_users) {
      $form_state->setErrorByName('users_to_send', t("Please select at least one user to send your message."));
    }

    // Force the group creation if there are more than 2 members selected.
    $is_group = $form_state->getValue('create_group', FALSE);
    if (count($selected_users) > 1 && !$is_group) {
      $form_state->setErrorByName('create_group', t('Please create a group to send the message to the several participants.'));
    }

    // Don't create a group if there are only 2 members.
    if (count($selected_users) === 1 && $is_group) {
      $form_state->setErrorByName('create_group', t('Please use one-to-one discussion if you want to send the message only to one person.'));
    }

    // Add the error message if the subject isn't set for the group.
    $subject = $form_state->getValue('subject');
    if ($is_group && !$subject) {
      $form_state->setErrorByName('subject', t('Subject field is required for the group discussion.'));
    }
  }

  /**
   * Hide/show members selector depending on the checkbox value.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function showMembersAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $command = $form_state->getValue('edit_members') ? 'removeClass' : 'addClass';
    $response->addCommand(new InvokeCommand('#users-to-send', $command, ['hidden']));

    return $response;
  }

  /**
   * The custom form AJAX submit callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxSubmit(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    // Check if there are any errors and display them.
    if ($form_state->getErrors()) {
      $status_messages = [
        '#type' => 'status_messages',
        '#weight' => -50,
      ];
      $response->addCommand(new HtmlCommand('.modal-ajax .modal-body .opigno-status-messages-container', $status_messages));
      $response->setStatusCode(400);

      return $response;
    }

    // That's impossible to render the ajax form in the ajax callback, so we'll
    // reload the page to display the updated content.
    $build = $form_state->getBuildInfo();
    $tid = $build['args'][0] ?? 0;
    if ($tid) {
      $url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $tid]);
    }
    else {
      $url = Url::fromRoute('private_message.private_message_page');
    }
    $response->addCommand(new RedirectCommand($url->toString()));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->messageStorage instanceof EntityStorageInterface
      || !$this->threadStorage instanceof EntityStorageInterface
    ) {
      return;
    }

    // Prepare the list of thread members.
    $members = $form_state->getValue('users_to_send', []);
    array_unshift($members, $this->currentUid);

    // Check if the thread can be taken from the form.
    $thread = NULL;
    $build = $form_state->getBuildInfo();
    $tid = $build['args'][0] ?? 0;
    if ($tid) {
      $thread = $this->threadStorage->load($tid);
    }

    // Update the members list, it can be changed for existing thread.
    if ($thread instanceof PrivateMessageThreadInterface) {
      $thread->set('members', $members);
    }
    else {
      $thread = $this->pmService->getThreadForMembers($members);
    }

    // Set the thread info.
    $create_group = $form_state->getValue('create_group', FALSE);
    if (count($members) > 2 && $create_group) {
      // Set the author only if no other value was set before.
      if ($thread->hasField('field_author') && $thread->get('field_author')->isEmpty()) {
        $thread->set('field_author', $this->currentUid);
      }

      // Don't update the subject/image/members/etc if the user picks the same
      // members list that is exactly the same as in any existing thread.
      if ($thread->hasField('field_author')
        && (int) $thread->get('field_author')->getString() === $this->currentUid
      ) {
        $subject = $form_state->getValue('subject', 'Discussion');
        $thread->set('field_pm_subject', $subject);
        $thread->set('field_create_group', TRUE);

        // Set/unset the group image.
        $image = $form_state->getValue('image');
        $thread->set('field_image', $image);
      }
    }

    $text = $form_state->getValue('message', '');
    $msg = NULL;

    if ($text) {
      // Create the message, add it to the thread and save.
      $msg = $this->messageStorage->create([
        'message' => $text,
      ]);

      try {
        $msg->save();
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_messaging_exception', $e);
      }
    }

    if ($msg instanceof PrivateMessageInterface) {
      $thread->addMessage($msg);
    }

    try {
      $thread->save();
      if ($msg instanceof PrivateMessageInterface) {
        $this->pmService->sendEmailToThreadMembers($thread, $msg);
      }
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_messaging_exception', $e);
    }
  }

}
