<?php

namespace Drupal\opigno_messaging\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_messaging\Ajax\OpignoScrollToLastMessage;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Form\PrivateMessageForm as PrivateMessageFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Override the default PrivateMessageForm.
 *
 * @package Drupal\opigno_messaging\Form
 */
class PrivateMessageForm extends PrivateMessageFormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleHandlerInterface $module_handler, ...$default) {
    parent::__construct(...$default);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('typed_data_manager'),
      $container->get('user.data'),
      $container->get('config.factory'),
      $container->get('private_message.service'),
      $container->get('private_message.thread_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PrivateMessageThreadInterface $privateMessageThread = NULL) {
    $form = parent::buildForm($form, $form_state, $privateMessageThread);
    $form['#attached']['library'][] = 'opigno_messaging/ajax_commands';

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
  public function ajaxCallback(array $form, FormStateInterface $formState) {
    $response = parent::ajaxCallback($form, $formState);
    // On submit scroll to the last message.
    $response->addCommand(new OpignoScrollToLastMessage());

    return $response;
  }

}
