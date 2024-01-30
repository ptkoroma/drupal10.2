<?php

namespace Drupal\queue_mail\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends emails form queue.
 *
 * @QueueWorker(
 *   id = "queue_mail",
 *   title = @Translation("Queue mail worker"),
 *   cron = {"time" = 60}
 * )
 */
class SendMailQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Theme initialization.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected $themeInitialization;

  /**
   * Active theme.
   *
   * @var \Drupal\Core\Theme\ActiveTheme
   */
  protected $activeTheme;

  /**
   * Mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Queue mail configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Queue for sending mails.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

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
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_init, MailManagerInterface $mail_manager, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, ContainerAwareInterface $queue_factory, ModuleHandlerInterface $module_handler, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_init;
    $this->activeTheme = $this->themeManager->getActiveTheme();
    $this->mailManager = $mail_manager;
    $this->logger = $logger_factory->get('mail');
    $this->config = $config_factory->get('queue_mail.settings');
    $this->queue = $queue_factory->get('queue_mail', TRUE);
    $this->moduleHandler = $module_handler;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($message) {
    $original_message = $message;
    $interval = $this->config->get('requeue_interval');

    // Prevent retrying until specified interval has elapsed.
    $current_time = $this->time->getCurrentTime();
    if (isset($message['last_attempt']) && ($message['last_attempt'] + $interval) > $current_time) {
      // Skip item until interval expires.
      $delay = $message['last_attempt'] + $interval - $current_time;
      throw new DelayedRequeueException($delay);
    }

    // Invoke hook_queue_mail_send_alter() to allow all modules to alter the
    // email before sending.
    $this->moduleHandler->alter('queue_mail_send', $message);

    // The caller requested sending. Sending was canceled by one or more
    // hook_queue_mail_send_alter() implementations. We set 'result' to NULL,
    // because FALSE indicates an error in sending.
    if (empty($message['send'])) {
      $message['result'] = NULL;

      return $message;
    }

    // Retrieve the responsible implementation for this message.
    $system = $this->mailManager->getInstance([
      'module' => $message['module'],
      'key' => $message['key'],
    ]);

    // Set theme that was used to generate mail body.
    $this->setMailTheme($message);

    // Set mail's language as active.
    $current_langcode = $this->setMailLanguage($message);

    try {
      // Format the message body.
      $message = $system->format($message);
    }
    finally {
      // Revert the active theme, this is done inside a finally block so it is
      // executed even if an exception is thrown during sending a mail.
      $this->setActiveTheme($message);

      // Revert the active language.
      $this->setActiveLanguage($message, $current_langcode);
    }

    // Ensure that subject is plain text. By default translated and
    // formatted strings are prepared for the HTML context and email
    // subjects are plain strings.
    if ($message['subject']) {
      $message['subject'] = PlainTextOutput::renderFromHtml($message['subject']);
    }
    $message['result'] = $system->mail($message);

    // Log errors.
    if (!$message['result']) {
      $this->logger->error('Error sending email (from %from to %to with reply-to %reply).', [
        '%from' => $message['from'],
        '%to' => $message['to'],
        '%reply' => $message['reply-to'] ?: $this->t('not set'),
      ]);

      $this->processRetryLimit($original_message);
    }

    $this->waitBetweenSending();

    return $message;
  }

  /**
   * Sets language from the message.
   *
   * @param array $message
   *   Mail message.
   *
   * @return string
   *   The negotiated language code.
   */
  protected function setMailLanguage(array $message) {
    return $message['langcode'];
  }

  /**
   * Restores back the negotiated language.
   *
   * @param array $message
   *   Mail message.
   * @param string $langcode
   *   The negotiated language code.
   */
  protected function setActiveLanguage(array $message, $langcode) {
  }

  /**
   * Set theme from the theme.
   *
   * @param array $message
   *   Mail message.
   */
  protected function setMailTheme(array $message) {
    if ($this->messageHasAnotherTheme($message)) {
      $theme = $this->themeInitialization->initTheme($message['theme']);
      $this->themeManager->setActiveTheme($theme);
    }
  }

  /**
   * Restore back theme that is used by default.
   *
   * @param array $message
   *   Mail message.
   */
  protected function setActiveTheme(array $message) {
    if ($this->messageHasAnotherTheme($message)) {
      $this->themeManager->setActiveTheme($this->activeTheme);
    }
  }

  /**
   * Checks if message has been generated using another theme.
   *
   * @param array $message
   *   Mail message.
   *
   * @return bool
   *   TRUE if message has theme that is not an active theme, FALSE otherwise.
   */
  protected function messageHasAnotherTheme(array $message) {
    return !empty($message['theme']) && $message['theme'] != $this->activeTheme->getName();
  }

  /**
   * Wait between items processing.
   *
   * Wait if "Wait time per item" configuration is enabled.
   */
  protected function waitBetweenSending() {
    if ($wait_time = $this->config->get('queue_mail_queue_wait_time')) {
      sleep($wait_time);
    }
  }

  /**
   * Retry limit handler.
   *
   * Counts number of attempts and removes mails from queue after
   * reaching threshold.
   *
   * @param array $original_message
   *   Original message.
   */
  protected function processRetryLimit(array $original_message) {
    $original_message['last_attempt'] = $this->time->getCurrentTime();

    if (!isset($original_message['fail_count'])) {
      $original_message['fail_count'] = 0;
    }
    $original_message['fail_count']++;

    $threshold = $this->config->get('threshold');

    // Add back to the queue with an updated fail count.
    if (!isset($threshold) || $original_message['fail_count'] < $threshold) {
      $this->queue->createItem($original_message);
    }
    else {
      $this->logger->error('Attempt sending email (from %from to %to with reply-to %reply) exceeded retry threshold and was deleted.', [
        '%from' => $original_message['from'],
        '%to' => $original_message['to'],
        '%reply' => $original_message['reply-to'] ?: $this->t('not set'),
      ]);
    }
  }

}
