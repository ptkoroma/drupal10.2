<?php

namespace Drupal\user_csv_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\RoleInterface;
use Drupal\user_csv_import\Controller\UserCsvImportController;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides methods to define and build the user import form.
 */
class UserCsvImportForm extends FormBase {

  /**
   * Provides messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Provides user entity.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityManager;

  /**
   * User import Form constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\Core\Entity\EntityFieldManager $entityManager
   */
  public function __construct(MessengerInterface $messenger, EntityFieldManager $entityManager) {
    $this->messenger = $messenger;
    $this->entityManager = $entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('messenger'),
      $container->get('entity_field.manager')
    );

  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {

    return 'user_csv_import_form';

  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['user_csv_import.importconfig'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Return the form object.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('user_csv_import.importconfig');

    $form['#tree'] = true;

    // Options field set.
    $form['config_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Options'),
    ];

    // Roles field.
    $roles = user_role_names();
    unset($roles['anonymous']);

    $rldef = $config->get('roles');
    $form['config_options']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $roles,
      '#default_value' => $rldef ? $rldef : ['authenticated' => true],
      '#description' => t('Tick the roles the imported users shall get. The Authenticated user role is mandatory'),
    ];
    // Special handling for the inevitable "Authenticated user" role.
    $form['config_options']['roles'][RoleInterface::AUTHENTICATED_ID] = [
      '#default_value' => true,
      '#disabled' => true,
    ];

    // Separator
    $form['config_options']['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator character'),
      '#required' => true,
      '#default_value' => ',',
      '#description' => t('The separator character is used to separate fields in the CSV-file.'),
      '#size' => 1,
      '#maxlength' => 1,
    ];

    // Default password.
    $pwdef = $config->get('password');
    $form['config_options']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default password'),
      '#required' => true,
      '#description' => t("The password is set to this for all imported users, unless overridden. See this module's README.md for details."),
      '#default_value' => $pwdef ? $pwdef : 'change me',
    ];

    // Status.
    $stdef = $config->get('status');
    $form['config_options']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        '0' => $this->t('Blocked'),
        '1' => $this->t('Active'),
      ],
      '#description' => t('Ensure this is set to Active if you want the user to be enabled.'),
      '#default_value' => $stdef ? $stdef : 1,
    ];

    // Send email on create user.
    $form['config_options']['registration_email_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select registration email type to send to user'),
      '#options' => [
        'none' => $this->t('Do not send a registration email'),
        'register_admin_created' => $this->t('Welcome (new user created by administrator)'),
      ],
      '#description' => t("If sending a welcome email, ensure that <strong>Status</strong> is  set 'Active', so users are able to log in and set a password."),
      '#default_value' => $config->get('registration_email_type'),
    ];

    // Fields field set.
    $form['config_fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select fields to import'),
    ];

    // Get user entity fields.
    $user_fields = $this->filterDefaultFields($this->entityManager->getFieldStorageDefinitions('user'));

    // Construct values for checkboxes.
    $selectable_fields = [];

    foreach ($user_fields as $field) {
      $selectable_fields[$field->getName()] = $field->getName();
    }

    // User form fields.
    $fldef = $config->get('config_fields');
    $form['config_fields']['fields'] = [
      '#type' => 'checkboxes',
      '#options' => $selectable_fields,
      '#description' => t('Tick the fields to import by means of CSVs. The fields Name and Email will are mandatory'),
    ];
    // Special handling for 'name' and 'mail'.
    $form['config_fields']['fields']['name'] = [
      '#default_value' => 'name',
      '#disabled' => true,
    ];
    $form['config_fields']['fields']['mail'] = [
      '#default_value' => 'mail',
      '#disabled' => true,
    ];

    // Save all form value.
    $form['save_config'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save configuration'),
      '#description' => $this->t('Tick to save the form settings to the database.'),
      '#default_value' => false,
    ];

    // File to upload.
    $form['file'] = [
      '#type' => 'file',
      '#title' => 'CSV file upload',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import users'),
      '#button_type' => 'primary',
    ];

    $form['actions']['sample'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate sample CSV'),
      '#button_type' => 'secondary',
      '#submit' => [[$this, 'generateSample']],
    ];

    // By default, render the form using theme_system_config_form().
    $form['#theme'] = 'system_config_form';

    return $form;

  }

  public function generateSample(&$form, FormStateInterface $form_state) {
    $fields = $form_state->getValue(['config_fields', 'fields']);
    $sepchar = $form['config_options']['separator']['#value'];
    $sampleusers[0] = [
      'name' => 'john',
      'mail' => 'john@example.com',
      'pass' => 'examplePW1',
      'timezone' => 'UTC',
    ];
    $sampleusers[1] = [
      'name' => 'jane',
      'mail' => 'jane@example.com',
      'pass' => 'examplePW2',
      'timezone' => 'Europe/Oslo',
    ];
    $content = implode($sepchar, array_filter($fields));

    $cc = [];
    $cc[] = $content;

    for ($ii = 0; $ii < 2; $ii++) {
      $row = [];
      foreach (array_filter($fields) as $field) {
	switch ($field) {
	  case 'name':
	  case 'mail':
	  case 'pass':
	  case 'timezone':
            $row[] = $sampleusers[$ii][$field];
	    break;
	  default:
            $row[] = 'sample_' . $field . '_' . $ii;
	}
      }
      $cc[] = implode($sepchar, $row);
    }
    $lines = '';
    foreach ($cc as $line) {
      $lines .= $line . PHP_EOL;
    }

    $response = new Response();
    $response->setContent($lines);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="user-csv-import-sample.csv"');
    $form_state->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Get form data.
    $roles = $form_state->getValue(['config_options', 'roles']);
    $fields = $form_state->getValue(['config_fields', 'fields']);
    $sample_only = $form_state->getValue('op') == $form['actions']['sample']['#value'];

    // Filter vales and clean empty.
    $roles_selected = array_filter($roles, function ($item) {
      return ($item);
    });

    $fields_selected = array_filter($fields, function ($item) {
      return ($item);
    });

    // If there is no options selected, show the error.
    if (empty($roles_selected) && !$sample_only) {
      $form_state->setErrorByName('roles', $this->t('Please select at least one role to apply to the imported user(s).'));
    }
    elseif (empty($fields_selected)) {
      $form_state->setErrorByName('fields', $this->t('Please select at least one field to apply to the imported user(s).'));
    }
    elseif (!array_key_exists('mail', $fields_selected) or !array_key_exists('name', $fields_selected)) {
      // If "mail" and "name" fields are not selected, show an error.
      $form_state->setErrorByName('roles', $this->t('The email and username fields are required.'));
    }

    if ($sample_only) {
      // Don't validate file if we're generating a sample.
      return;
    }

    // Validate file.
    $this->file = file_save_upload('file', $form['file']['#upload_validators']);

    if (!isset($this->file[0])) {
      $form_state->setErrorByName('file', t('No file chosen.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get form data.
    $file = $this->file[0];
    $roles = $form_state->getValue(['config_options', 'roles']);
    $fields = $form_state->getValue(['config_fields', 'fields']);

    // Save config.
    if ($form_state->getValue('save_config')) {
      \Drupal::configFactory()->getEditable('user_csv_import.importconfig')
      ->set('roles', $roles)
      ->set('status', $form_state->getValue(['config_options', 'status']))
      ->set('password', $form_state->getValue(['config_options', 'password']))
      ->set('registration_email_type', $form_state->getValue(['config_options', 'registration_email_type']))
      ->set('config_fields', $fields)
      ->save();
    }

    // Construct data to send to the controller.
    $config = [
      'roles' => array_filter($roles, function ($item) {
        return ($item);
      }),
      'fields' => array_filter($fields, function ($item) {
        return ($item);
      }),
      'registration_email_type' => $form_state->getValue(['config_options', 'registration_email_type']),
      'separator' => $form_state->getValue(['config_options', 'separator']),
      'password' => $form_state->getValue(['config_options', 'password']),
      'status' => $form_state->getValue(['config_options', 'status']),
    ];

    // Return success message.
    if ($created = UserCsvImportController::processUpload($file, $config)) {

      $this->messenger->addMessage($this->t('Successfully imported @count users.', ['@count' => count($created)]));
    }

    else {

      // Return error message.
      $this->messenger->addWarning($this->t('No users imported.'));
    }

    // Redirect to admin users page.
    $form_state->setRedirectUrl(new Url('entity.user.collection'));

  }

  /**
   * Unset user account default fields.
   */
  private function filterDefaultFields($fields) {

    unset($fields['uid']);
    unset($fields['uuid']);
    unset($fields['langcode']);
    unset($fields['preferred_langcode']);
    unset($fields['preferred_admin_langcode']);
    unset($fields['status']);
    unset($fields['created']);
    unset($fields['changed']);
    unset($fields['access']);
    unset($fields['login']);
    unset($fields['init']);
    unset($fields['roles']);
    unset($fields['default_langcode']);
    unset($fields['user_picture']);

    return $fields;

  }

}
