<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oauth2_client\Exception\InvalidOauth2ClientException;
use Drupal\oauth2_client\OwnerCredentials;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use Drupal\oauth2_client\Service\CredentialProvider;
use Drupal\oauth2_client\Service\Oauth2ClientService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OAuth2 Client form.
 *
 * @property \Drupal\oauth2_client\Entity\Oauth2ClientInterface $entity
 */
class Oauth2ClientForm extends EntityForm {

  /**
   * Injected credential service.
   */
  protected CredentialProvider $credentialService;

  /**
   * The Drupal state api.
   */
  protected StateInterface $state;

  /**
   * Injected client service.
   */
  protected Oauth2ClientService $clientService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->credentialService = $container->get('oauth2_client.service.credentials');
    $instance->state = $container->get('state');
    $instance->clientService = $container->get('oauth2_client.service');
    // $messenger is declared but not initialized
    // in FormBase via MessengerTrait.
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the oauth2 client.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\oauth2_client\Entity\Oauth2Client::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the oauth2 client.'),
    ];

    $plugin = $this->entity->getClient();
    if ($plugin instanceof Oauth2ClientPluginInterface) {
      $credentials = $this->credentialService->getCredentials($plugin);
      $grantType = $plugin->getGrantType();
      $form['plugin_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t(
          'Client Settings: <em>@name</em>',
          ['@name' => $plugin->getName()]),
      ];
      $form['plugin_settings']['credential_provider'] = [
        '#type' => 'hidden',
        '#value' => 'oauth2_client',
      ];
      $form['plugin_settings']['oauth2_client'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Stored locally'),
        'client_id' => [
          '#type' => 'textfield',
          '#title' => $this->t('Client ID'),
          '#default_value' => $credentials['client_id'] ?? '',
          '#states' => [
            'required' => [
              ':input[data-states-selector="provider"]' => ['value' => 'oauth2_client'],
            ],
            'enabled' => [
              ':input[data-states-selector="provider"]' => ['value' => 'oauth2_client'],
            ],
          ],
        ],
        'client_secret' => [
          '#type' => 'textfield',
          '#title' => $this->t('Client secret'),
          '#default_value' => $credentials['client_secret'] ?? '',
          '#states' => [
            'required' => [
              ':input[data-states-selector="provider"]' => ['value' => 'oauth2_client'],
            ],
            'enabled' => [
              ':input[data-states-selector="provider"]' => ['value' => 'oauth2_client'],
            ],
          ],
        ],

      ];
      if ($grantType == 'resource_owner') {
        $form['oauth2_client']['username'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Username'),
          '#description' => $this->t('The username and password entered here are not saved, but are only used to request the token.'),
        ];
        $form['oauth2_client']['password'] = [
          '#type' => 'password',
          '#title' => $this->t('Password'),
        ];
      }// If Key module or some other future additional provider is available:
      if ($this->credentialService->additionalProviders()) {
        $this->expandedProviderOptions($form, $grantType);
      }
    }

    return $form;
  }

  /**
   * Helper method to build the credential provider elements of the form.
   *
   * Only needed if we have more than one provider.  Currently supporting
   * oauth2_client controlled local storage and Key module controlled optional
   * storage.
   *
   * @param mixed[] $form
   *   The configuration form.
   * @param string $grantType
   *   The grant type for the current plugin.
   */
  protected function expandedProviderOptions(array &$form, string $grantType): void {
    $provider = $this->entity->get('credential_provider');
    // Provide selectors for the api key credential provider.
    $form['plugin_settings']['credential_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Credential provider'),
      '#default_value' => empty($provider) ? 'oauth2_client' : $provider,
      '#options' => [
        'oauth2_client' => $this->t('Local storage'),
        'key' => $this->t('Key module'),
      ],
      '#attributes' => [
        'data-states-selector' => 'provider',
      ],
      '#weight' => -99,
    ];
    $form['plugin_settings']['oauth2_client']['#states'] = [
      'visible' => [
        ':input[data-states-selector="provider"]' => ['value' => 'oauth2_client'],
      ],
    ];
    $key_id = $provider === 'key' ? $this->entity->getCredentialStorageKey() : '';
    $form['plugin_settings']['key'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Managed by the Key module'),
      '#states' => [
        'visible' => [
          ':input[data-states-selector="provider"]' => ['value' => 'key'],
        ],
      ],
      'key_id' => [
        '#type' => 'key_select',
        '#title' => $this->t('Select a stored Key'),
        '#default_value' => $key_id,
        '#empty_option' => $this->t('- Please select -'),
        '#key_filters' => ['type' => 'oauth2_client'],
        '#description' => $this->t('Select the key you have configured to hold the Oauth credentials.'),
        '#states' => [
          'required' => [
            ':input[data-states-selector="provider"]' => ['value' => 'key'],
          ],
          'enabled' => [
            ':input[data-states-selector="provider"]' => ['value' => 'key'],
          ],
        ],
      ],
    ];
    if ($grantType == 'resource_owner') {
      $form['plugin_settings']['key']['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#required' => TRUE,
        '#description' => $this->t('The username and password entered here are not saved, but are only used to request the token.'),
        '#states' => [
          'required' => [
            ':input[data-states-selector="provider"]' => ['value' => 'key'],
          ],
          'enabled' => [
            ':input[data-states-selector="provider"]' => ['value' => 'key'],
          ],
        ],
      ];
      $form['plugin_settings']['key']['password'] = [
        '#type' => 'password',
        '#required' => TRUE,
        '#title' => $this->t('Password'),
        '#states' => [
          'required' => [
            ':input[data-states-selector="provider"]' => ['value' => 'key'],
          ],
          'enabled' => [
            ':input[data-states-selector="provider"]' => ['value' => 'key'],
          ],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // Add a test button.
    $actions['test'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and request token'),
      '#submit' => ['::testToken'],
      '#button_type' => 'secondary',
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Prepare the credential data for storage and save in the entity,.
    $values = $form_state->getValues();
    $provider = $values['credential_provider'];
    // Set default storage key.
    $key = $this->entity->uuid() ?? $form['#build_id'];
    switch ($provider) {
      case 'oauth2_client':
        $credentials = [
          'client_id' => $values['client_id'],
          'client_secret' => $values['client_secret'],
        ];
        $this->state->set($key, $credentials);
        break;

      case 'key':
        $key = $values['key_id'];
    }
    $form_state->setValue('credential_storage_key', $key);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new oauth2 client %label.', $message_args)
      : $this->t('Updated oauth2 client %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Additional submit function for saving both config and token.
   *
   * @param mixed[] $form
   *   The current form build.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state object.
   */
  public function testToken(array &$form, FormStateInterface $formState): void {
    $this->submitForm($form, $formState);
    $this->save($form, $formState);
    // Try to obtain a token.
    try {
      $plugin = $this->entity->getClient();
      if ($plugin instanceof Oauth2ClientPluginInterface) {
        // Clear the existing token.
        $this->clientService->clearAccessToken($plugin->getId());
        $values = $formState->getValues();
        $user = $values['username'] ?? '';
        $password = $values['password'] ?? '';
        $this->clientService->getAccessToken($plugin->getId(), new OwnerCredentials($user, $password));
      }
    }
    catch (InvalidOauth2ClientException $e) {
      $formState->disableRedirect();
      // Failed to get the access token.
      $this->messenger->addError(
        $this->t(
          'Unable to obtain an OAuth token. The error message is: @message',
          ['@message' => $e->getMessage()]
        )
      );
      watchdog_exception('Oauth2 Client', $e);
    }
  }

}
