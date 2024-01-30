<?php

namespace Drupal\Tests\queue_mail\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests queue mail functionality.
 *
 * @group queue_mail
 */
class QueueMailFunctionalTest extends BrowserTestBase {

  use AssertMailTrait;
  use CronRunTrait;

  /**
   * The mail language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['queue_mail', 'queue_mail_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();
    $this->langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
  }

  /**
   * Prepares parameters of test mails.
   */
  protected function getMessageParams() {
    return [
      'content' => $this->randomMachineName(),
    ];
  }

  /**
   * Sets settings that all emails have to be queued for testing.
   */
  protected function setAllEmailsToBeQueued() {
    // Set all emails to be queued and test.
    \Drupal::configFactory()->getEditable('queue_mail.settings')
      ->set('queue_mail_keys', '*')
      ->save();
  }

  /**
   * Test that if we're not queuing any emails that they get sent as normal.
   */
  public function testNonQueuedEmail() {
    // Send an email and ensure it was sent immediately.
    \Drupal::configFactory()->getEditable('queue_mail.settings')
      ->set('queue_mail_keys', '')
      ->save();
    $this->sendEmailAndTest('basic', FALSE);
  }

  /**
   * Test that if we are queuing emails, that they get queued.
   */
  public function testQueuedEmail() {
    $this->setAllEmailsToBeQueued();
    $this->sendEmailAndTest();
  }

  /**
   * This tests the matching of mailkeys to be queued.
   *
   * For example, we test that a specific email from a module is queued, and
   * that emails from another module are not queued.
   */
  public function testQueuedEmailKeyMatching() {
    // Set only some emails to be queued and test.
    \Drupal::configFactory()->getEditable('queue_mail.settings')
      ->set('queue_mail_keys', 'queue_mail_test_queued')
      ->save();
    $this->sendEmailAndTest('queued', TRUE);
    $this->sendEmailAndTest('not_queued', FALSE);

    // And test the wildcard matching.
    \Drupal::configFactory()->getEditable('queue_mail.settings')
      ->set('queue_mail_keys', 'queue_mail_test_que*')
      ->save();
    $this->sendEmailAndTest('queued', TRUE);
    $this->sendEmailAndTest('not_queued', FALSE);
  }

  /**
   * Test that messages are not queued if the the "send" flag is FALSE.
   */
  public function testSkippedEmail() {
    $this->setAllEmailsToBeQueued();

    $this->assertEmpty($this->getMails(), 'Ensure that mail collector is empty.');

    $this->sendQueueMailTest('skipped', 'info@example.com', $this->getMessageParams());
    $this->assertEmpty($this->getMails(), 'Emails has not been sent.');

    $queue = _queue_mail_get_queue();
    $this->assertEquals(0, $queue->numberOfItems(), 'Email has not been added to the mail queue.');

    $this->cronRun();
    $this->assertEmpty($this->getMails(), 'Emails has not been sent after cron run.');
  }

  /**
   * Send an email and ensure it is queued or sent immediately.
   *
   * @param string $mail_key
   *   The key of the email to send.
   * @param bool $should_be_queued
   *   Pass in TRUE to test if the email was queued, FALSE to test that it
   *   wasn't queued.
   */
  public function sendEmailAndTest($mail_key = 'basic', $should_be_queued = TRUE) {
    $queue = _queue_mail_get_queue();
    // Parameters before testing.
    $queue_count_before = $queue->numberOfItems();
    $email_count_before = count($this->getMails());
    $content = $this->randomMachineName();

    // Send test email.
    $message = $this->sendQueueMailTest($mail_key, 'info@example.com', ['content' => $content]);

    $queue_count_after = $queue->numberOfItems();
    $email_count_after = count($this->getMails());

    // Now do the desired assertions.
    if ($should_be_queued === TRUE) {
      $this->assertEquals(1, $queue_count_after - $queue_count_before, 'Email is queued.');
      $this->assertEquals(0, $email_count_after - $email_count_before, 'Queued email is not sent immediately.');

      // Now run cron and see if our email gets sent.
      $queue_count_before = $queue->numberOfItems();
      $email_count_before = count($this->getMails());
      $this->cronRun();
      $this->assertMailString('body', $content, 1);
      $queue_count_after = $queue->numberOfItems();
      $email_count_after = count($this->getMails());
      $this->assertEquals(-1, $queue_count_after - $queue_count_before, 'Email is sent from the queue.');
      $this->assertEquals(1, $email_count_after - $email_count_before, 'Queued email is sent on cron.');
      $this->assertMail('current_langcode', $this->langcode, 'The mail language was respected');
      $this->assertTrue($message['queued'], 'Message has been added to the queue.');
    }
    elseif ($should_be_queued === FALSE) {
      $this->assertEquals(0, $queue_count_after - $queue_count_before, 'Email is not queued.');
      $this->assertEquals(1, $email_count_after - $email_count_before, 'Email is sent immediately.');
      $this->assertMailString('body', $content, 1);
      $this->assertFalse($message['queued'], 'Message has not been added to the queue.');
    }
  }

  /**
   * Test that message sending may be canceled.
   *
   * @see queue_mail_test_queue_mail_send_alter()
   */
  public function testCancelMessage() {
    $this->setAllEmailsToBeQueued();

    $queue = _queue_mail_get_queue();
    $queue_count_init = $queue->numberOfItems();

    // Send test mails.
    $params = $this->getMessageParams();
    $this->sendQueueMailTest('cancel_message', 'cancel@example.com', $params);
    $this->sendQueueMailTest('send_message', 'send@example.com', $params);

    // Ensures that both mails in the queue.
    $queue_count_after_adding = $queue->numberOfItems();
    $this->assertEquals(2, $queue_count_after_adding - $queue_count_init, 'Emails are queued.');

    $this->cronRun();

    // Checks that queue has been emptied.
    $queue_count_after_sending = $queue->numberOfItems();
    $this->assertEquals($queue_count_init, $queue_count_after_sending, 'Emails have been removed from queue');

    // Ensures that just one emails has been sent from two created.
    $email_count_after_sending = count($this->getMails());
    $this->assertEquals(1, $email_count_after_sending, 'One email is sent only.');
    $this->assertMailString('key', 'send_message', 1);
  }

  /**
   * Wraps send mail function.
   *
   * @param string $key
   *   A key to identify the email sent.
   * @param string $to
   *   The email address or addresses where the message will be sent to.
   * @param array $params
   *   (optional) Parameters to build the email.
   *
   * @return array
   *   The $message array structure containing all details of the message.
   */
  protected function sendQueueMailTest($key, $to, array $params = []) {
    return \Drupal::service('plugin.manager.mail')
      ->mail('queue_mail_test', $key, $to, $this->langcode, $params);
  }

  /**
   * Test that message sending may be failed.
   */
  public function testFailMessage() {
    $this->setAllEmailsToBeQueued();

    $queue = _queue_mail_get_queue();
    $queue_count_init = $queue->numberOfItems();

    $params = $this->getMessageParams();
    // Send message that won't be send and will be re-queued.
    $this->sendQueueMailTest('fail_message', 'fail@example.com', $params);
    $this->cronRun();
    $queue_count_after_adding = $queue->numberOfItems();
    // Ensures that "fail_message" hasn't been sent.
    $this->assertEquals(1, $queue_count_after_adding - $queue_count_init, 'Mail sending has been failed. Message is in the queue.');

    // Send normal message.
    $this->sendQueueMailTest('send_message', 'send@example.com', $params);
    $queue_count_after_adding = $queue->numberOfItems();
    // Ensures that there are two messages in the queue - "fail_message" and
    // "send_message".
    $this->assertEquals(2, $queue_count_after_adding - $queue_count_init, 'Mail sending has been failed. Message is in the queue.');
    $this->cronRun();

    // Ensures that one mail has been processed and one is still in the queue.
    $queue_count_after_adding = $queue->numberOfItems();
    $this->assertEquals(1, $queue_count_after_adding - $queue_count_init, 'One message has been processed.');
  }

  /**
   * Tests retry threshold functionality.
   */
  public function testRetryThreshold() {
    $this->setAllEmailsToBeQueued();
    $queue = _queue_mail_get_queue();
    $settings = \Drupal::configFactory()->getEditable('queue_mail.settings');
    // Set requeue interval as 1 to make it possible to process the queue item
    // on the next cron run.
    $settings->set('requeue_interval', 1)->save();

    // No retry.
    $settings->set('threshold', 0)->save();
    $this->sendQueueMailTest('fail_message', 'fail@example.com', $this->getMessageParams());
    $this->assertEquals(1, $queue->numberOfItems());
    $this->cronRun();
    $this->assertEquals(0, $queue->numberOfItems());

    // Retry threshold is 3 - message is removed from queue after 3 attempts.
    $settings->set('threshold', 3)->save();
    $this->sendQueueMailTest('fail_message', 'fail@example.com', $this->getMessageParams());
    $this->assertEquals(1, $queue->numberOfItems());
    for ($i = 1; $i <= 2; $i++) {
      $this->cronRun();
      $this->assertEquals(1, $queue->numberOfItems());
      $this->releaseQueueItems();
      $item = $queue->claimItem();
      $this->assertEquals($i, $item->data['fail_count']);
      $this->releaseQueueItems();
      sleep(1);
    }
    $this->cronRun();
    $this->assertEquals(0, $queue->numberOfItems());

    // Retry threshold is unlimited - message won't be removed from queue.
    $settings->set('threshold', NULL)->save();
    $this->sendQueueMailTest('fail_message', 'fail@example.com', $this->getMessageParams());
    $this->assertEquals(1, $queue->numberOfItems());
    for ($i = 1; $i <= 10; $i++) {
      $this->cronRun();
      $this->assertEquals(1, $queue->numberOfItems());
      $this->releaseQueueItems();
      $item = $queue->claimItem();
      $this->assertEquals($i, $item->data['fail_count']);
      $this->releaseQueueItems();
      sleep(1);
    }
  }

  /**
   * Releases queue items between cron run.
   *
   * \Drupal\Core\Queue\DatabaseQueue::garbageCollection() uses request time
   * that don't release items inside few cron runs in one request.
   */
  protected function releaseQueueItems() {
    \Drupal::database()->update('queue')
      ->fields([
        'expire' => 0,
      ])
      ->execute();
  }

}
