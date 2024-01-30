<?php

namespace Drupal\redirect_after_logout\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RedirectLogoutSettings.
 *
 * @package Drupal\redirect_after_logout\Form
 *
 * @ingroup redirect_after_logout
 */
/**
 * Class RedirectLogoutSettings Description.
 */
class RedirectLogoutSettings extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * The router.request_context service.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandler $module_handler, PathValidator $pathValidator, Token $tokenService, RequestContext $requestContext) {
    parent::__construct($config_factory);
    $this->moduleHandler = $module_handler;
    $this->pathValidator = $pathValidator;
    $this->tokenService = $tokenService;
    $this->requestContext = $requestContext;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('path.validator'),
      $container->get('token'),
      $container->get('router.request_context'),
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'redirect_after_logout_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['redirect_after_logout.settings'];
  }

  /**
   * Defines the settings form for Redirect After Logout.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('redirect_after_logout.settings');
    $form['redirect_after_logout_destination'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default user redirect destination'),
      '#description' => $this->t('%front is the front page.<br>You can use internal path with slash: %internal<br>or external path: %external<br>or token: %token', [
        '%front' => '<front>',
        '%internal' => '/node/1',
        '%external' => 'http://example.com/',
        '%token' => '[current-page:url]',
      ]),
      '#default_value' => $config->get('destination'),
      '#required' => TRUE,
    ];
    $form['redirect_after_logout_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default user redirect message, after logout'),
      '#description' => $this->t('Tokens are allowed.'),
      '#default_value' => $config->get('message'),
    ];
    $form['redirect_after_message_type'] = [
      '#title' => $this->t('Message Type'),
      '#description' => $this->t('Message type'),
      '#type' => 'select',
      '#options' => [
        'status' => $this->t('Status'),
        'warning' => $this->t('Warning'),
        'error' => $this->t('Error'),
      ],
      '#default_value' => $config->get('message_type'),
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      // Add the token help to a collapsed fieldset at
      // the end of the configuration page.
      $form['token_help'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Available Tokens List'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];
      $form['token_help']['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#global_types' => TRUE,
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Validate redirect destination.
    $base_url = $this->requestContext->getCompleteBaseUrl();
    $destination = $form_state->getValue('redirect_after_logout_destination');
    if ($destination === '<front>') {
      return;
    }
    if (strlen($destination) > 1 && $destination[0] === '/' && $destination[1] === '[') {
      // Token with left slash: remove left slash.
      $destination = substr($destination, 1);
      $form_state->setValue('redirect_after_logout_destination', $destination);
    }
    $tokenized_destination = UrlHelper::stripDangerousProtocols($this->tokenService->replace($destination));
    if (UrlHelper::isExternal($tokenized_destination) && !UrlHelper::isValid($tokenized_destination, TRUE)) {
      // Invalid URL.
      $form_state->setErrorByName('redirect_after_logout_destination', $this->t("The path '%path' is invalid.", ['%path' => $destination]));
    }
    if (UrlHelper::isExternal($tokenized_destination) && !UrlHelper::externalIsLocal($tokenized_destination, $base_url)) {
      // Really external URL.
      return;
    }
    if (substr($tokenized_destination, 0, strlen($base_url)) == $base_url) {
      // Remove a string from the beginning of a string.
      // @see https://stackoverflow.com/a/4517270
      // Remove base URL from tokenized destination.
      $tokenized_destination = substr($tokenized_destination, strlen($base_url));
    }
    if ($tokenized_destination[0] !== '/') {
      $form_state->setErrorByName('redirect_after_logout_destination', $this->t("The path '%path' has to start with a slash.", ['%path' => $destination]));
    }
    if (UrlHelper::isExternal($tokenized_destination)) {
      $valid = UrlHelper::isValid($tokenized_destination, TRUE);
    }
    else {
      $valid = $this->pathValidator->isValid($tokenized_destination);
    }
    if (!$valid) {
      $form_state->setErrorByName('redirect_after_logout_destination', $this->t("Either the path '%path' is invalid or you do not have access to it.", ['%path' => $destination]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('redirect_after_logout.settings');
    $config->set('destination', $form_state->getValue('redirect_after_logout_destination'));
    $config->set('message', $form_state->getValue('redirect_after_logout_message'));
    $config->set('message_type', $form_state->getValue('redirect_after_message_type'));

    $config->save();
  }

}
