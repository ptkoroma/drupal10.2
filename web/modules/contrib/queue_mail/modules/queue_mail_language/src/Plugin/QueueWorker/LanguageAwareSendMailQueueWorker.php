<?php

namespace Drupal\queue_mail_language\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\queue_mail\Plugin\QueueWorker\SendMailQueueWorker;
use Drupal\queue_mail_language\QueueMailLanguageNegotiator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends emails from queue with language support.
 */
class LanguageAwareSendMailQueueWorker extends SendMailQueueWorker {

  /**
   * The configurable language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The queue mail language negotiator.
   *
   * @var \Drupal\queue_mail_language\QueueMailLanguageNegotiator
   */
  protected $queueMailLanguageNegotiator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme.manager'),
      $container->get('theme.initialization'),
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('queue'),
      $container->get('module_handler'),
      $container->get('datetime.time'),
      $container->get('language_manager'),
      $container->get('queue_mail.language_negotiator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    ThemeManagerInterface $theme_manager,
    ThemeInitializationInterface $theme_init,
    MailManagerInterface $mail_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    ContainerAwareInterface $queue_factory,
    ModuleHandlerInterface $module_handler,
    TimeInterface $time,
    ConfigurableLanguageManagerInterface $language_manager,
    QueueMailLanguageNegotiator $queue_mail_language_negotiator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $theme_manager, $theme_init, $mail_manager, $logger_factory, $config_factory, $queue_factory, $module_handler, $time);
    $this->languageManager = $language_manager;
    $this->queueMailLanguageNegotiator = $queue_mail_language_negotiator;
  }

  /**
   * {@inheritdoc}
   */
  protected function setMailLanguage(array $message) {
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    if ($message['langcode'] !== $default_langcode) {
      $this->setNegotiatorLanguage($message['langcode']);
    }
    return $default_langcode;
  }

  /**
   * {@inheritdoc}
   */
  protected function setActiveLanguage(array $message, $langcode) {
    if ($message['langcode'] !== $langcode) {
      $this->setNegotiatorLanguage($langcode);
    }
  }

  /**
   * Sets the queue mail negotiator language.
   *
   * @param string $langcode
   *   The new language code.
   */
  protected function setNegotiatorLanguage($langcode) {
    if ($this->languageManager->getNegotiator() !== $this->queueMailLanguageNegotiator) {
      $this->languageManager->setNegotiator($this->queueMailLanguageNegotiator);
    }
    $this->queueMailLanguageNegotiator->setLanguageCode($langcode);
    // Needed to re-run language negotiation.
    $this->languageManager->reset();
  }

}
