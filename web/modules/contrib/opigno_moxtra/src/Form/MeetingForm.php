<?php

namespace Drupal\opigno_moxtra\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\opigno_calendar\Plugin\Field\FieldWidget\OpignoDateRangeWidget;
use Drupal\opigno_moxtra\MoxtraServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a form for creating/editing a opigno_moxtra_meeting entity.
 */
class MeetingForm extends ContentEntityForm {

  /**
   * Plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $pluginManager;

  /**
   * Moxtra service interface.
   *
   * @var \Drupal\opigno_moxtra\MoxtraServiceInterface
   */
  protected $moxtraService;

  /**
   * Creates a WorkspaceForm object.
   */
  public function __construct(
    EntityRepositoryInterface $entity_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    PluginManagerInterface $plugin_manager,
    MoxtraServiceInterface $moxtra_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct(
      $entity_manager,
      $entity_type_bundle_info,
      $time
    );
    $this->pluginManager = $plugin_manager;
    $this->moxtraService = $moxtra_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.field.widget'),
      $container->get('opigno_moxtra.moxtra_api'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_moxtra_create_meeting_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\opigno_moxtra\MeetingInterface $entity */
    $entity = $this->entity;
    $owner_id = $entity->getOwnerId();
    $session_key = $entity->getSessionKey();
    if (!empty($session_key)) {
      $info = $this->moxtraService->getMeetingInfo($owner_id, $session_key);
      $status = !empty($info['data']) ? $info['data']['status'] : FALSE;
      if (!empty($status) && $status !== 'SESSION_SCHEDULED') {
        $form[] = [
          '#markup' => $this->t('You can edit only a scheduled live meeting.'),
        ];
        return $form;
      }
    }

    if ($entity->getTraining() === NULL) {
      $group = $this->getRequest()->get('group');
      if ($group !== NULL) {
        $group_type = $group->getGroupType()->id();
        if ($group_type === 'learning_path') {
          // If creating entity on a group page, set that group as a related.
          $entity->setTraining($group);
        }
      }
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $date_field_def = $entity->getFieldDefinition('date');
    $date_field_item_list = $entity->get('date');

    $date_range_plugin_id = 'opigno_daterange';
    $date_range = new OpignoDateRangeWidget(
      $date_range_plugin_id,
      $this->pluginManager->getDefinition($date_range_plugin_id),
      $date_field_def,
      array_merge(OpignoDateRangeWidget::defaultSettings(), [
        'value_format' => 'Y-m-d H:i:s',
        'value_timezone' => date_default_timezone_get(),
      ]),
      [],
      $this->entityTypeManager->getStorage('date_format')
    );

    $form['date'] = $date_range->form($date_field_item_list, $form, $form_state);

    $training = $entity->getTraining();
    if ($training !== NULL) {

      $options = [];
      $members = $entity->getMembers();
      foreach ($members as $member) {
        $options['user_' . $member->id()] = $this->t("@name (User #@id)", [
          '@name' => $member->getDisplayName(),
          '@id' => $member->id(),
        ]);
      }

      $form['members'] = [
        '#title' => $this->t('Members restriction'),
        '#type' => 'entity_selector',
        '#attributes' => [
          'id' => 'meeting_members',
          'class' => [
            'row',
          ],
        ],
        '#default_value' => array_keys($options),
        '#entity_selector_option' => '\Drupal\opigno_moxtra\Controller\MeetingController::membersAutocompleteSelect',
        '#entity_selector_parameters' => [
          'group' => $training,
        ],
        '#multiple' => TRUE,
        '#data_type' => 'key',
        '#options' => [],
        '#show_exists' => TRUE,
        '#validated' => TRUE,
      ];
    }
    else {
      $form['members'] = [
        '#markup' => $this->t('Live Meeting should have a related training to add a members restriction.'),
      ];
    }

    $form['status_messages'] = [
      '#type' => 'status_messages',
    ];

//    $form['#attached']['library'][] = 'multiselect/drupal.multiselect';
    $form['#attached']['library'][] = 'opigno_moxtra/meeting_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\opigno_moxtra\MeetingInterface $entity */
    $entity = $this->entity;

    $group = $entity->getTraining();

    if (!$group) {
      $form_state->setError($form['title'], $this->t('Live Meeting should have a related training.'));
    }

    // $date = $form_state->getValue('date');
    $start_date = $form_state->getValue('date')[0]['value'];
    $end_date = $form_state->getValue('date')[0]['end_value'];

    if (isset($start_date)) {
      $now = DrupalDateTime::createFromTimestamp($this->time->getRequestTime());
      if ($start_date <= $now) {
        $form_state->setError($form['date'], 'The start date should not be in the past');
      }
    }

    if (isset($end_date)) {
      if (isset($start_date) && $end_date < $start_date) {
        $form_state->setError($form['date'], 'The end date cannot be before the start date');
      }
    }

    if (empty($form_state->getErrors())) {
      $session_key = $entity->getSessionKey();
      if (empty($session_key)) {
        // Create meeting in the Moxtra.
        $user = $this->currentUser();
        $user_id = $user->id();
        $title = $form_state->getValue('title');

        // Get ISO-8601 date without a timezone when meeting starts.
        $start_date_string = !empty($start_date)
          ? $start_date->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z')
          : NULL;

        // Get ISO-8601 date without a timezone when meeting ends.
        $end_date_string = !empty($end_date)
          ? $end_date->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z')
          : NULL;

        $response = $this->moxtraService
          ->createMeeting($user_id, $title, $start_date_string, $end_date_string);

        if ((int) $response['http_code'] === 200) {
          $entity->setBinderId($response['data']['schedule_binder_id']);
          $entity->setSessionKey($response['data']['session_key']);
        }
        else {
          $form_state->setError($form, $this->t("Can't create live meeting. Moxtra error: @message", [
            '@message' => !empty($response['message']) ? $response['message'] : $response['error'],
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\opigno_moxtra\MeetingInterface $entity */
    $entity = $this->entity;
    $current_members_ids = [];
    $current_members = !empty($form['members']['#default_value']) ? $form['members']['#default_value'] : [];
    foreach ($current_members as $current_member) {
      list($type, $id) = explode('_', $current_member);
      $current_members_ids[] = $id;
    }

    $date = $form_state->getValue('date');

    if (isset($date[0]['value'])) {
      $start_date = $date[0]['value'];
    }

    if (isset($date[0]['end_value'])) {
      $end_date = $date[0]['end_value'];
    }

    $start_date_value = isset($start_date)
      ? $start_date->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        ->format(DrupalDateTime::FORMAT)
      : NULL;

    $end_date_value = isset($end_date)
      ? $end_date->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        ->format(DrupalDateTime::FORMAT)
      : NULL;

    $date_range = [
      'value' => $start_date_value,
      'end_value' => $end_date_value,
    ];
    $entity->setDate($date_range);

    // Load added users & classes from the form_state.
    $users_ids = [];
    $classes_ids = [];
    $invite_users = [];
    $owner_id = $entity->getOwnerId();

    $options = $form_state->getValue('members');
    if ($options) {
      if (count($options)) {
        $options['user_' . $owner_id] = 'user_' . $owner_id;
      }
      if (!empty($options)) {
        foreach ($options as $option) {
          list($type, $id) = explode('_', $option);

          if ($type === 'user') {
            $users_ids[] = $id;
          }
          elseif ($type === 'class') {
            $classes_ids[] = $id;
          }
        }

        $classes = Group::loadMultiple($classes_ids);
        foreach ($classes as $class) {
          // Add class members to the users.
          /** @var \Drupal\group\Entity\Group $class */
          $members = $class->getMembers();
          foreach ($members as $member) {
            /** @var \Drupal\group\GroupMembership $member */
            $user = $member->getUser();
            $users_ids[] = $user->id();
          }
        }

        $entity->setMembersIds($users_ids);
      }
    }

    $users = User::loadMultiple($users_ids);

    foreach ($users as $account) {
      $prefix = $this->moxtraService->prefix($account);
      $invite_users[] = [
        'user' => ['unique_id' => $prefix . $account->id()]
      ];
    }

    $this->moxtraService->AddUsersToMeeting($entity->getOwnerId(), $entity->getSessionKey(), $invite_users);

    // Save entity.
    $status = parent::save($form, $form_state);

    // Prepare email notifications.
    $mail_service = \Drupal::service('plugin.manager.mail');
    $params = [];
    $params['subject'] = $params['message'] = t('Created new Live Meeting %meeting', [
      '%meeting' => $entity->getTitle(),
    ]);
    if (\Drupal::hasService('opigno_calendar_event.iCal')) {
      $params['attachments'] = opigno_moxtra_ical_prepare($entity);
    }
    $module = 'opigno_moxtra';
    $key = 'upcoming_meeting_notify';

    // Set status message.
    $meeting_link = $entity->toLink()->toString();
    if ($status == SAVED_UPDATED) {
      $message = $this->t('The Live Meeting %meeting has been updated.', [
        '%meeting' => $meeting_link,
      ]);

      // Send email notifications about meeting for added users.
      foreach ($users as $user) {
        if (!in_array($user->id(), $current_members_ids)) {
          $to = $user->getEmail();
          $langcode = $user->getPreferredLangcode();
          $mail_service->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
        }
      }
    }
    else {
      $message = $this->t('The Live Meeting %meeting has been created.', [
        '%meeting' => $meeting_link,
      ]);

      if (empty($options) && $training = $entity->getTraining()) {
        $memberships = $training->getMembers();
        if ($memberships) {
          foreach ($memberships as $membership) {
            $uid = $membership->getUser()->id();
            if ($uid != $entity->getOwner()->id()) {
              $users_ids[] = $uid;
            }
          }
        }
      }

      // Send email notifications about new meeting for users.
      foreach ($users as $user) {
        $to = $user->getEmail();
        $langcode = $user->getPreferredLangcode();
        $mail_service->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
      }
    }

    $this->messenger()->addMessage($message);

    // Set redirect.
    $form_state->setRedirect('opigno_moxtra.meeting', [
      'opigno_moxtra_meeting' => $entity->id(),
    ]);
    return $status;
  }

}
