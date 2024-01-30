<?php

namespace Drupal\queue_mail_test\Plugin\Mail;

use Drupal\Core\Mail\Plugin\Mail\TestMailCollector;

/**
 * QueueMailTestMailCollector class.
 *
 * Defines a mail backend that captures sent and formatted messages in the state
 * system.
 */
class QueueMailTestMailCollector extends TestMailCollector {

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    $message = parent::format($message);

    $message['current_langcode'] = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();

    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    $result = parent::mail($message);

    if ($message['key'] == 'fail_message') {
      $result = FALSE;
    }

    return $result;
  }

}
