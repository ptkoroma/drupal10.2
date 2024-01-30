<?php

namespace Drupal\Tests\queue_mail\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests configuration of Queue mail module.
 *
 * @group queue_mail
 */
class QueueMailConfigurationTest extends BrowserTestBase {

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  const CONFIGURATION_PATH = 'admin/config/system/queue_mail';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['queue_mail'];

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer site configuration']);
  }

  /**
   * Tests default settings on the settings form.
   */
  public function testDefaultConfiguration() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(static::CONFIGURATION_PATH);

    $default_values = [
      'queue_mail_keys' => '',
      'queue_mail_queue_time'  => 15,
      'queue_mail_queue_wait_time' => 0,
      'threshold' => 50,
      'requeue_interval' => 10800,
    ];
    $this->assertFieldValues($default_values);
    $this->assertSettings($default_values);
  }

  /**
   * Tests change of settings.
   */
  public function testChangeConfiguration() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(static::CONFIGURATION_PATH);

    $edit = [
      'queue_mail_keys' => '*',
      'queue_mail_queue_time'  => 60,
      'queue_mail_queue_wait_time' => 15,
      'threshold' => 100,
      'requeue_interval' => 21600,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->drupalGet(static::CONFIGURATION_PATH);
    $this->assertFieldValues($edit);
    $this->assertSettings($edit);

    // Unlimited retry threshold.
    $edit['threshold'] = 0;
    $this->submitForm($edit, 'Save configuration');
    $this->assertFieldValues($edit);
    $this->assertSettings($edit);

    // No retry.
    $edit['threshold'] = '';
    $this->submitForm($edit, 'Save configuration');
    $this->assertFieldValues($edit);
    $edit['threshold'] = NULL;
    $this->assertSettings($edit);
  }

  /**
   * Tests "Wait time per item" setting validation.
   */
  public function testWaitTimePerItemValidation() {
    $this->drupalLogin($this->adminUser);

    $validation_text = '"Wait time per item" value can not be bigger than "Queue processing time" value.';

    // "Wait time per item" value is bigger than "Queue processing time" value.
    $edit = [
      'queue_mail_queue_time'  => 30,
      'queue_mail_queue_wait_time' => 35,
    ];
    $this->drupalGet(static::CONFIGURATION_PATH);
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains($validation_text);

    // "Wait time per item" value is less than "Queue processing time" value.
    $edit = [
      'queue_mail_queue_time'  => 30,
      'queue_mail_queue_wait_time' => 25,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseNotContains($validation_text);
  }

  /**
   * Asserts values on the settings form.
   */
  protected function assertFieldValues($values) {
    foreach ($values as $field => $value) {
      $this->assertSession()->fieldValueEquals($field, $value);
    }
  }

  /**
   * Asserts settings stored in queue_mail.settings.
   */
  protected function assertSettings($values) {
    $config = \Drupal::config('queue_mail.settings');
    foreach ($values as $key => $value) {
      $this->assertSame($value, $config->get($key));
    }
  }

}
