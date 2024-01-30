<?php

namespace Drupal\Tests\oauth2_client\Functional;

use Drupal\Core\Database\Database;
use Drupal\oauth2_client\Entity\Oauth2ClientInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UpdatePathTestTrait;

/**
 * Tests the update and post update functions for moving from 3.x to 4.x.
 *
 * @group oauth2_client
 */
class Oauth2ClientUpdateTest extends BrowserTestBase {
  use UpdatePathTestTrait;

  /**
   * Required setting.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * An array of config object names that are excluded from schema checking.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'oauth2_client.credentials.authcode_access_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $connection = Database::getConnection();

    // Set the schema version.
    \Drupal::service('update.update_hook_registry')->setInstalledVersion('oauth2_client', 8000);

    // Update core.extension.
    $extensions = $connection->select('config')
      ->fields('config', ['data'])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute()
      ->fetchField();
    $extensions = unserialize($extensions);
    $extensions['module']['oauth2_client'] = 0;
    $extensions['module']['oauth2_client_test_plugins'] = 0;
    $connection->update('config')
      ->fields([
        'data' => serialize($extensions),
      ])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute();
    // Prepare config for post update verification.
    $config = $this->config('oauth2_client.credentials.authcode_access_test');
    $config->set('uuid', 'b3b29944-b945-4b5e-a32b-513883cefcc6');
    $config->set('credentials', [
      'credential_provider' => 'key',
      'storage_key' => 'test_key',
    ]);
    $config->save();
  }

  /**
   * Test Oauth2 Client update hooks.
   */
  public function testOauth2UpdateHooks() {
    /** @var \Drupal\Core\Config\CachedStorage $configStorage */
    $configStorage = $this->container->get('config.storage');
    $this->assertTrue($configStorage->exists('oauth2_client.credentials.authcode_access_test'));
    $this->runUpdates();
    // Updates completed without error verifies that unconfigured but installed
    // plugins do not cause an error.
    $this->assertFalse($configStorage->exists('oauth2_client.credentials.authcode_access_test'));
    /** @var \Drupal\Core\Entity\EntityStorageInterface $clientStorage */
    $clientStorage = $this->container->get('entity_type.manager')->getStorage('oauth2_client');
    /** @var \Drupal\oauth2_client\Entity\Oauth2ClientInterface|null $clientEntity */
    $clientEntity = $clientStorage->load('authcode_access_test');
    $this->assertInstanceOf(Oauth2ClientInterface::class, $clientEntity);
    $this->assertEquals('key', $clientEntity->getCredentialProvider());
    $this->assertEquals('test_key', $clientEntity->getCredentialStorageKey());
  }

  /**
   * {@inheritdoc}
   */
  protected function doSelectionTest() {
    // Ensure that our updates are pending.
    $this->assertSession()->responseContains('<ul><li>9401 - Install the entity definition for Oauth2Client config entities.</li><li>Migrates configuration to new Oauth2Client config entities.</li></ul>');
  }

}
