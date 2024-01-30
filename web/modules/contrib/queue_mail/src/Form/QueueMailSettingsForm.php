<?php

namespace Drupal\queue_mail\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures module's settings.
 */
class QueueMailSettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Constructs a new QueueMailSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, RedirectDestinationInterface $redirect_destination) {
    parent::__construct($config_factory);

    $this->moduleHandler = $module_handler;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_mail_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'queue_mail.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('queue_mail.settings');

    $form['queue_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Queue status'),
      '#markup' => $this->formatPlural(_queue_mail_get_queue()->numberOfItems(), '1 mail currently queued for sending.', '@count mails currently queued for sending.'),
      '#description' => $this->t('Sending of queued mails happens on cron. You can <a href="@cron_link">run cron manually</a>.',
        [
          '@cron_link' => Url::fromRoute('system.run_cron', [], ['query' => $this->redirectDestination->getAsArray()])->toString(),
        ]),
    ];

    $form['queue_mail_keys'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mail IDs to queue'),
      '#description' => $this->t('Enter each mail ID to queue on a separate line. Use <strong>*</strong> to do a wildcard match.') . '<br/>' . $this->t('Mail IDs are a combination of the first and second arguments sent to <em>drupal_mail</em> when a module sends an email. E.g. <em>user_mail</em>, <em>register_pending_approval_admin</em>') . '<br />' . $this->t('For example, to queue all mails sent by the User module, enter: <em>user_*</em> above, to just queue password recovery emails enter: <em>user_password_reset</em>'),
      '#default_value' => $config->get('queue_mail_keys'),
    ];

    // Get a list of modules that implement hook_mail.
    $modules = $this->moduleHandler->getModuleList();
    $mail_modules = [];
    foreach ($modules as $module => $extension) {
      if ($this->moduleHandler->hasImplementations('mail', $module)) {
        $mail_modules[] = $module;
      }
    }

    $rows = [];
    foreach ($mail_modules as $name) {
      $row = [];
      $row[] = $name;
      $row[] = $name . '_*';
      $rows[] = $row;
    }
    $headers = [
      $this->t('Module name'),
      $this->t('Mail ID prefix'),
    ];

    $form['tables'] = [
      '#type' => 'table',
      '#caption' => $this->t('The following modules send emails. The contents of the second column can be used in the options above to queue the sending of those emails.'),
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No tables available.'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#collapsed' => TRUE,
    ];

    $options = [];
    for ($i = 5; $i <= 240; $i += 5) {
      $options[$i] = $this->formatPlural($i, '1 second', '@count seconds');
    }

    $form['advanced']['queue_mail_queue_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Queue processing time (max)'),
      '#description' => $this->t('How much time in seconds to allow queue mail to send emails for on cron. Warning if you set a very high limit your cron run could timeout and never complete.'),
      '#options' => $options,
      '#default_value' => $config->get('queue_mail_queue_time'),
    ];

    $form['advanced']['threshold'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Queue retry threshold'),
      '#default_value' => $config->get('threshold'),
      '#description' => $this->t('How many times to retry before deleting an item. Set "0" not to retry. Leave empty for unlimited.'),
    ];

    $form['advanced']['requeue_interval'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Requeue interval'),
      '#default_value' => $config->get('requeue_interval'),
      '#field_suffix' => $this->t('seconds'),
      '#description' => $this->t('How long to wait in seconds before retrying failed emails.'),
    ];

    $form['advanced']['queue_mail_queue_wait_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Wait time per item'),
      '#description' => $this->t('How long to wait in seconds in between the processing of each item. This value can not be bigger than "Queue processing time" value.'),
      '#step' => 1,
      '#min' => 0,
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $config->get('queue_mail_queue_wait_time') ?: 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $queue_mail_queue_time = $form_state->getValue('queue_mail_queue_time');
    $queue_mail_queue_wait_time = $form_state->getValue('queue_mail_queue_wait_time');
    if ($queue_mail_queue_time < $queue_mail_queue_wait_time) {
      $form_state->setErrorByName('queue_mail_queue_wait_time', $this->t('"Wait time per item" value can not be bigger than "Queue processing time" value.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('queue_mail.settings')
      ->set('queue_mail_keys', $form_state->getValue('queue_mail_keys'))
      ->set('queue_mail_queue_time', $form_state->getValue('queue_mail_queue_time'))
      ->set('threshold', $form_state->getValue('threshold'))
      ->set('requeue_interval', $form_state->getValue('requeue_interval'))
      ->set('queue_mail_queue_wait_time', $form_state->getValue('queue_mail_queue_wait_time'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
