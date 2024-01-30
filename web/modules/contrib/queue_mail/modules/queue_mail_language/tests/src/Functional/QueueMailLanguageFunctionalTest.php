<?php

namespace Drupal\Tests\queue_mail_language\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\queue_mail\Functional\QueueMailFunctionalTest;

/**
 * Tests queue mail functionality with language support.
 *
 * @group queue_mail
 */
class QueueMailLanguageFunctionalTest extends QueueMailFunctionalTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['queue_mail_language', 'queue_mail_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->langcode = 'it';
    ConfigurableLanguage::createFromLangcode($this->langcode)->save();
  }

}
