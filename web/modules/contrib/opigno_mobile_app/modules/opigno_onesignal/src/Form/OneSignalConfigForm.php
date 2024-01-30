<?php

namespace Drupal\opigno_onesignal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\opigno_onesignal\Config\ConfigManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OneSignalConfigForm.
 */
class OneSignalConfigForm extends ConfigFormBase {
  
  /**
   * The config manager service.
   *
   * @var \Drupal\opigno_onesignal\Config\ConfigManagerInterface
   */
  private $configManager;
  
  /**
   * OneSignalConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\opigno_onesignal\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ConfigManagerInterface $configManager) {
    parent::__construct($configFactory);
    
    $this->configManager = $configManager;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('opigno_onesignal.config_manager')
    );
  }
  
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'opigno_onesignal.config',
    ];
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_one_signal_config_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['onesignal_app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OneSignal App ID'),
      '#description' => $this->t('Find it at https://onesignal.com under your app Settings &gt; Keys &amp; IDs.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $this->configManager->getAppId(),
    ];

    $form['onesignal_rest_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OneSignal REST API key'),
      '#description' => $this->t('Find it at https://onesignal.com under your app Settings &gt; Keys &amp; IDs.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $this->configManager->getRestApiKey(),
    ];

    return parent::buildForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    
    $this->config('opigno_onesignal.config')
      ->set('onesignal_app_id', $form_state->getValue('onesignal_app_id'))
      ->set('onesignal_rest_api_key', $form_state->getValue('onesignal_rest_api_key'))
      ->save();
  }
  
}
