<?php

namespace Drupal\Tests\oauth2_client\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oauth2_client\Entity\Oauth2Client;
use Drupal\oauth2_client\PluginManager\Oauth2ClientPluginManager;
use Drupal\oauth2_client\PluginManager\Oauth2GrantTypePluginManager;
use Drupal\oauth2_client\Service\CredentialProvider;
use Drupal\oauth2_client\Service\Oauth2ClientService;
use PHPUnit\Framework\OutputError;

/**
 * Base class for building Oauth2Client kernel tests.
 */
abstract class Oauth2ClientKernelTestBase extends KernelTestBase {

  public const CLIENT_SECRET = 'client-secret-string';

  public const CLIENT_ID = 'client-id-string';

  public const STORAGE_KEY = 'oauth2-test-storage-id';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['oauth2_client', 'oauth2_client_test_plugins'];

  /**
   * Injected grant plugin manager.
   *
   * @var \Drupal\oauth2_client\PluginManager\Oauth2GrantTypePluginManager
   */
  protected $grantPluginManager;

  /**
   * Injected entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Injected plugin manager.
   *
   * @var \Drupal\oauth2_client\PluginManager\Oauth2ClientPluginManager
   */
  protected Oauth2ClientPluginManager $clientPluginManager;

  /**
   * Set up the test case.
   */
  public function setUp(): void {
    parent::setUp();
    /** @var \Drupal\Core\State\State $state */
    $state = $this->container->get('state');
    $entityManager = $this->container->get('entity_type.manager');
    $this->entityTypeManager = $entityManager;
    // Store test credentials.
    $credentials = [
      'client_id' => self::CLIENT_ID,
      'client_secret' => self::CLIENT_SECRET,
    ];
    $state->set(self::STORAGE_KEY, $credentials);

    // Setup services.
    $manager = new Oauth2ClientPluginManager(
      $this->container->get('container.namespaces'),
      $this->container->get('cache.discovery'),
      $this->container->get('module_handler'),
      $entityManager
    );
    $this->clientPluginManager = $manager;
    $grant = new Oauth2GrantTypePluginManager(
      $this->container->get('container.namespaces'),
      $this->container->get('cache.discovery'),
      $this->container->get('module_handler')
    );
    $this->grantPluginManager = $grant;
    $credentials = new CredentialProvider(
      $state,
      $entityManager
    );
    $this->clientService = new Oauth2ClientService($entityManager, $state);
    $this->container->set('oauth2_client.plugin_manager', $manager);
    $this->container->set('plugin.manager.oauth2_grant_type', $grant);
    $this->container->set('oauth2_client.service.credentials', $credentials);
  }

  /**
   * Helper function that creates an Oauth2Client.
   *
   * @param string $id
   *   The entity id.
   *
   * @return \Drupal\oauth2_client\Entity\Oauth2Client
   *   A configured app.
   */
  public function getApp(string $id): Oauth2Client {
    // Trigger auto-populate.
    $this->clientPluginManager->getDefinitions();
    // Get the matching entity.
    $app = $this->entityTypeManager->getStorage('oauth2_client')->load($id);
    if (!($app instanceof Oauth2Client)) {
      throw new OutputError('Matching entity not generated.');
    }
    $app->set('description', 'test_description');
    $app->set('credential_storage_key', self::STORAGE_KEY);
    $app->save();
    return $app;
  }

}
