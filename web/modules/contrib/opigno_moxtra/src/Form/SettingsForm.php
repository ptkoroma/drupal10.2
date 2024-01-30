<?php

namespace Drupal\opigno_moxtra\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\user\Entity\User;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Implements the Opigno Moxtra settings form.
 */
class SettingsForm extends ConfigFormBase implements TrustedCallbackInterface {

  /**
   * Batch options.
   *
   * @var array
   */
  protected $batch;

  /**
   * The keyvalue storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactory
   */
  protected $keyValueStorage;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a SettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TimeInterface $time,
    KeyValueFactory $key_value,
    RequestStack $request_stack
  ) {
    parent::__construct($config_factory);
    $this->time = $time;
    $this->keyValueStorage = $key_value->get('opigno_moxtra');
    $this->request = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('keyvalue'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_moxtra_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'opigno_moxtra.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('opigno_moxtra.settings');
    $org_id = $config->get('org_id');

    $form['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row'],
      ],
    ];

    $form['content']['left'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-4'],
      ],
    ];

    $form['content']['right'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-8'],
      ],
    ];

    $form['content']['right']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Moxtra API Settings'),
    ];

    if (empty($org_id)) {
      $form['content']['left']['text'] = [
        '#type' => 'container',
        'text' => [
          '#markup' => $this->t('You need to register on'),
        ],
      ];

      $renew_title = $this->t('Moxtra');
      $renew_uri = 'https://moxtra.com/';
      $renew_url = Url::fromUri($renew_uri)->setOptions(['target' => '_blank']);
      $renew = Link::fromTextAndUrl($renew_title, $renew_url)->toRenderable();
      $form['content']['left']['renew'] = $renew;
    }
    else {
      $form['content']['left']['text'] = [
        '#type' => 'container',
        'text' => [
          '#markup' => $this->t('Add all users to Moxtra'),
        ],
      ];

      $form['content']['left']['text']['add'] = [
        '#type' => 'submit',
        '#attributes' => ['name' => 'add'],
        '#value' => $this->t('Register all'),
        '#submit' => [[$this, 'registerAllSubmit']]
      ];
    }

    $form['content']['right']['info'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['moxtra-info', 'col-8']],
    ];

    $form['content']['right']['info']['moxtra_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Moxtra URL'),
      '#default_value' => $config->get('url'),
      '#description' => $this->t('Example: @url', ['@url' => 'https://opigno-dev.moxtra.com']),
    ];

    $form['content']['right']['info']['org_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization ID'),
      '#default_value' => $config->get('org_id'),
    ];

    $form['content']['right']['info']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
    ];

    $form['content']['right']['info']['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
      '#post_render' => [[$this, 'rebuildPass']],
    ];

    $form['content']['right']['info']['moxtra_login'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#default_value' => $config->get('email'),
    ];

    $form['content']['right']['info']['agreement'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => $this->t('I understand and expressly Consent to the transfer of the following data to Moxtra Inc., a company located in the United States of America : URL of my website, email address of the website administrator, name of the website, total number of users of the website and my username, ID and timezone'),
      '#wrapper_attributes' => [
        'class' => ['inline-checkbox'],
      ],
      '#default_value' => $config->get('agreement'),
    ];

    $form['content']['right']['info']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => [[$this, 'settingsSubmitForm']],
    ];

    $form['#attached'] = [
      'library' => [
        'opigno_moxtra/settings_form',
      ],
    ];


    $form['#attached']['library'][] = 'opigno_moxtra/settings_form';
    $form['#attached']['library'][] = 'opigno_moxtra/moxtra.js';

    return $form;
  }

  /**
   * Rebuild moxtra client secret field.
   */
  public function rebuildPass($field, $form) {
    $default_value = isset($form['#default_value']) ? $form['#default_value'] : NULL;

    $field = $field->__toString();

    $field = str_replace(
      'type="password"',
      'type="password" value="' . $default_value . '" ', $field);

    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSubmitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = \Drupal::configFactory()->getEditable('opigno_moxtra.settings');
    $config->setData([]);
    $config->save();

    $connector = \Drupal::service('opigno_moxtra.connector');
    $token = $connector->getToken(1, TRUE);

    $this->config('opigno_moxtra.settings')
      ->set('url', $form_state->getValue('moxtra_url'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->set('email', $form_state->getValue('moxtra_login'))
      ->set('org_id', $form_state->getValue('org_id'))
      ->set('agreement', $form_state->getValue('agreement'))
      ->set('status', !empty($token))
      ->save();

    $prefix = $this->keyValueStorage->get('prefix');
    if (empty($prefix)) {
      $prefix = $this->request->getCurrentRequest()->getHost();
      $this->keyValueStorage->set('prefix', $prefix);
    }
  }

  public function registerAllSubmit(array &$form, FormStateInterface $form_state) {
    $this->setBatch();
  }


  /**
   * Set operation.
   *
   * @param array $data
   *   Process data.
   */
  public function setOperation(array $data) {
    $this->batch['operations'][] = [[$this, 'processItem'], $data];
  }

  /**
   * Process item of batch.
   *
   * @param array $data
   *   The mail of new user.
   *   Master user.
   * @param array $context
   *   Context.
   */
  public static function processItem($item, array &$context) {
    $moxtra = \Drupal::service('opigno_moxtra.moxtra_api');

    if (empty($context['results'])) {
      $context['results'] = [];
    }

    if (!empty($item->uid)) {
      $account = User::load($item->uid);
      $moxtra->setUser($account);
    }

    $context['results'][] = $item->uid;
  }

  /**
   * Set the batch.
   */
  public function setBatch() {
    $database = \Drupal::database();
    $query = $database->select('users', 'u');
    $query->fields('u', ['uid']);
    $users = $query->execute()->fetchAll();

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Register Moxtra users'))
      ->setFinishCallback([$this, 'finishBatch']);

    foreach ($users as $key => $user) {
      $batch_builder->addOperation([$this, 'processItem'], [
        'users' => $user
      ]);
    }

    batch_set($batch_builder->toArray());
  }

  /**
   * Finished callback of batch api.
   *
   * @param bool $success
   *   Success or not.
   * @param array $results
   *   The results.
   * @param array $operations
   *   The operations.
   */
  public function finishBatch(bool $success, array $results, array $operations) {
    if ($success) {
      $message = \Drupal::translation()
        ->formatPlural(count($results), 'One user processed.', '@count users processed.');
    }
    else {
      $message = $this->t('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'rebuildPass'
    ];
  }

}
