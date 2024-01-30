<?php

namespace Drupal\opigno_ilt\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\opigno_calendar\Plugin\Field\FieldWidget\OpignoDateRangeWidget;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a form for creating/editing a opigno_ilt entity.
 */
class ILTForm extends ContentEntityForm {

  /**
   * The plugin manger.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $pluginManager;

  /**
   * Creates a ILTForm object.
   */
  public function __construct(
    EntityRepositoryInterface $entity_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    PluginManagerInterface $plugin_manager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct(
      $entity_manager,
      $entity_type_bundle_info,
      $time
    );
    $this->pluginManager = $plugin_manager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_ilt_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\opigno_ilt\ILTInterface $entity */
    $entity = $this->entity;
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

    $date_range_plugin_id = 'daterange_default';
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

    $form['place'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Place'),
      '#default_value' => $entity->getPlace(),
      '#placeholder' => $this->t('Enter here the address where the instructor-led training will take place'),
      '#required' => TRUE,
    ];

    $training = $entity->getTraining();
    if ($training !== NULL) {
      $trainer_id = $entity->getTrainerId();
      $trainer_name = '';

      if ($trainer_id) {
        $trainer = \Drupal::entityTypeManager()
                    ->getStorage('user')
                    ->load($trainer_id);

        if ($trainer) {
          $trainer_name = $trainer->getAccountName();
        }
      }

      $form['trainer'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Trainer'),
        '#default_value' => $trainer_name,
        '#autocomplete_route_name' => 'opigno_ilt.opigno_ilt_trainer_autocomplete',
        '#autocomplete_route_parameters' => [
          'group' => $training->id(),
        ],
        '#placeholder' => $this->t('Enter a user’s name or email'),
      ];

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
          'id' => 'members',
          'class' => [
            'row',
          ],
        ],
        '#default_value' => array_keys($options),
        '#entity_selector_option' => '\Drupal\opigno_ilt\Controller\ILTController::membersAutocompleteSelect',
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
        '#markup' => $this->t('Instructor-Led Training should have a related training to add a members restriction.'),
      ];
    }

    $form['status_messages'] = [
      '#type' => 'status_messages',
    ];

//    $form['#attached']['library'][] = 'datepicker';
    $form['#attached']['library'][] = 'opigno_ilt/form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $entity = $this->entity;
    $group = $entity->getTraining();

    if (!$group) {
      $form_state->setError($form['title'], $this->t('Instructor-Led Training should have a related training.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\opigno_ilt\ILTInterface $entity */
    $entity = $this->entity;
    $date = $form_state->getValue('date');
    $current_members_ids = [];
    $current_members = $form['members']['#default_value'];
    foreach ($current_members as $current_member) {
      list($type, $id) = explode('_', $current_member);
      $current_members_ids[] = $id;
    }

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

    parent::save($form, $form_state);

    // Load added users & classes from the form_state.
    $users_ids = [];
    $classes_ids = [];
    $owner_id = $entity->getOwnerId();

    $options = $form_state->getValue('members');
    if (count($options)) {
      $options['user_' . $owner_id] = 'user_' . $owner_id;
    }
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
    $trainer_name = $form_state->getValue('trainer');

    if ($trainer_name != '') {
      $users = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties([
          'name' => $trainer_name,
        ]);

      $trainer = $users ? reset($users) : FALSE;

      if ($trainer) {
        $entity->setTrainerId($trainer->id());
      }
    }

    // Save entity.
    $status = parent::save($form, $form_state);

    // Prepare email notifications.
    $mail_service = \Drupal::service('plugin.manager.mail');
    $params = [];
    $params['subject'] = $params['message'] = t('Created new Instructor Led Training %meeting', [
      '%meeting' => $entity->getTitle(),
    ]);
    if (\Drupal::hasService('opigno_calendar_event.iCal')) {
      $params['attachments'] = opigno_ilt_ical_prepare($entity);
    }
    $module = 'opigno_ilt';
    $key = 'upcoming_ilt_notify';

    // Set status message.
    $link = $entity->toLink()->toString();
    if ($status == SAVED_UPDATED) {
      $message = $this->t('The Instructor-Led Training %ilt has been updated.', [
        '%ilt' => $link,
      ]);

      // Send email notifications
      // about the Instructor-Led Training for added users.
      $users = User::loadMultiple($users_ids);
      foreach ($users as $user) {
        if (!in_array($user->id(), $current_members_ids)) {
          $to = $user->getEmail();
          $langcode = $user->getPreferredLangcode();
          $mail_service->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
        }
      }
    }
    else {
      $message = $this->t('The Instructor-Led Training %ilt has been created.', [
        '%ilt' => $link,
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

      // Send email notifications
      // about the new Instructor-Led Training for users.
      $users = User::loadMultiple($users_ids);
      foreach ($users as $user) {
        $to = $user->getEmail();
        $langcode = $user->getPreferredLangcode();
        $mail_service->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
      }
    }
    $this->messenger()->addMessage($message);

    // Set redirect.
    $form_state->setRedirect('entity.opigno_ilt.canonical', [
      'opigno_ilt' => $entity->id(),
    ]);
    return $status;
  }

}
