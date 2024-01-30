<?php

namespace Drupal\Tests\oauth2_client\Kernel;

use Drupal\oauth2_client\Service\CredentialProvider;

/**
 * Tests Oauth2Client credential service.
 *
 * @group oauth2_client
 */
class Oauth2ClientCredentialServiceKernelTest extends Oauth2ClientKernelTestBase {

  /**
   * Credential provider with mocked state.
   *
   * @var \Drupal\oauth2_client\Service\CredentialProvider
   */
  protected CredentialProvider $emptyCredentialProvider;

  /**
   * Standard credential provider.
   *
   * @var \Drupal\oauth2_client\Service\CredentialProvider
   */
  protected CredentialProvider $credentialProvider;

  /**
   * Set up the test.
   */
  public function setUp(): void {
    parent::setUp();
    $nullState = $this->createMock('\Drupal\Core\State\StateInterface');
    $nullState->method('get')->willReturn(NULL);
    $this->emptyCredentialProvider = new CredentialProvider(
      $nullState,
      $this->entityTypeManager
    );
    $this->credentialProvider = $this->container->get('oauth2_client.service.credentials');
  }

  /**
   * @covers \Drupal\oauth2_client\Service\CredentialProvider::getCredentials
   */
  public function testRetrieveFromState() {
    $app = $this->getApp('authcode_access_test');
    /** @var \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $plugin */
    $plugin = $app->getClient();
    $this->assertSame(
      [
        'client_id' => Oauth2ClientKernelTestBase::CLIENT_ID,
        'client_secret' => Oauth2ClientKernelTestBase::CLIENT_SECRET,
      ],
      $this->credentialProvider->getCredentials($plugin)
    );
  }

  /**
   * @covers \Drupal\oauth2_client\Service\CredentialProvider::getCredentials
   */
  public function testNullState() {
    $app = $this->getApp('authcode_access_test');
    /** @var \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $plugin */
    $plugin = $app->getClient();
    $this->assertSame(
      [],
      $this->emptyCredentialProvider->getCredentials($plugin)
    );
  }

}
