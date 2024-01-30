<?php

declare(strict_types = 1);

namespace Drupal\Tests\oauth2_client\Kernel;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\oauth2_client\Exception\AuthCodeRedirect;
use Drupal\oauth2_client\OwnerCredentials;
use GuzzleHttp\Psr7\Request;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Tests Oauth2Client entities and plugins.
 *
 * @group oauth2_client
 */
class Oauth2ClientEntityKernelTest extends Oauth2ClientKernelTestBase {

  /**
   * Tests the simple get methods.
   *
   * Implicitly tests other methods as listed.
   *
   * @covers \Drupal\oauth2_client\Plugin\Discovery\Oauth2ClientDiscoveryDecorator::getDefinitions
   * @covers \Drupal\oauth2_client\Entity\Oauth2Client::getCredentialProvider
   * @covers \Drupal\oauth2_client\Entity\Oauth2Client::getCredentialStorageKey
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::create
   * @covers \Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypePluginBase::create
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getName
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getId
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getGrantType
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getClientId
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getClientSecret
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getRedirectUri
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getAuthorizationUri
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getTokenUri
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getRequestOptions
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getScopes
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase::getScopeSeparator
   */
  public function testGetters(): void {
    $app = $this->getApp('authcode_access_test');
    $this->assertEquals('oauth2_client', $app->getCredentialProvider());
    $this->assertEquals(Oauth2ClientKernelTestBase::STORAGE_KEY, $app->getCredentialStorageKey());
    /** @var \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $plugin */
    $plugin = $app->getClient();
    $this->assertInstanceOf('\Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface', $plugin);
    $this->assertEquals('Auth Code Test plugin', $plugin->getName());
    $this->assertEquals('authcode_access_test', $plugin->getId());
    $this->assertEquals('authorization_code', $plugin->getGrantType());
    $this->assertEquals(Oauth2ClientKernelTestBase::CLIENT_ID, $plugin->getClientId());
    $this->assertEquals(Oauth2ClientKernelTestBase::CLIENT_SECRET, $plugin->getClientSecret());
    $this->assertStringEndsWith('oauth2-client/authcode_access_test/code', $plugin->getRedirectUri());
    $this->assertEquals('https://www.example.com/oauth/authorize', $plugin->getAuthorizationUri());
    $this->assertEquals('https://www.example.com/oauth/token', $plugin->getTokenUri());
    $app = $this->getApp('request_options_test');
    /** @var \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $plugin */
    $plugin = $app->getClient();
    $options = $plugin->getRequestOptions(['added' => 'added_value']);
    $scopes = $plugin->getScopes();
    $separator = $plugin->getScopeSeparator();
    $this->assertArrayHasKey('test_parameter', $options);
    $this->assertEquals('test_value', $options['test_parameter']);
    $this->assertArrayHasKey('added', $options);
    $this->assertEquals('added_value', $options['added']);
    $this->assertIsArray($scopes);
    $this->assertContains('a', $scopes);
    $this->assertContains('b', $scopes);
    $this->assertEquals(',', $separator);
  }

  /**
   * Verifies that a redirect exception is thrown.
   *
   * @covers \Drupal\oauth2_client\Plugin\Oauth2GrantType\AuthorizationCode::getAccessToken
   */
  public function testAuthCodeGetToken(): void {
    $app = $this->getApp('authcode_access_test');
    /** @var \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $plugin */
    $plugin = $app->getClient();
    $authCodePlugin = $this->grantPluginManager->createInstance($plugin->getGrantType());
    $this->expectException(AuthCodeRedirect::class);
    try {
      $authCodePlugin->getAccessToken($plugin);
    }
    catch (\Exception $e) {
      if ($e instanceof AuthCodeRedirect) {
        $response = $e->getResponse();
        $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
        $this->assertStringStartsWith($plugin->getAuthorizationUri(), $response->getTargetUrl());
      }
      throw $e;
    }
  }

  /**
   * @covers \Drupal\oauth2_client\Plugin\Oauth2GrantType\AuthorizationCode::requestAccessToken
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\StateTokenStorage::storeAccessToken
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\StateTokenStorage::retrieveAccessToken
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\StateTokenStorage::clearAccessToken
   */
  public function testAuthCodeRequestToken(): void {
    $app = $this->getApp('authcode_access_test');
    /** @var \Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client\AuthCodeTest $plugin */
    $plugin = $app->getClient();
    /** @var \Drupal\oauth2_client\Plugin\Oauth2GrantType\AuthorizationCode $authCodePlugin */
    $authCodePlugin = $this->grantPluginManager->createInstance($plugin->getGrantType());
    $this->assertTrue($authCodePlugin->requestAccessToken($plugin, 'test-code'));
    $token = $plugin->retrieveAccessToken();
    $this->assertInstanceOf(AccessTokenInterface::class, $token);
    $plugin->clearAccessToken();
    $token = $plugin->retrieveAccessToken();
    $this->assertNull($token);
  }

  /**
   * @covers \Drupal\oauth2_client\Plugin\Oauth2GrantType\AuthorizationCode::getPostCaptureRedirect
   */
  public function testAuthCodeRedirect(): void {
    $app = $this->getApp('authcode_access_test');
    /** @var \Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client\AuthCodeTest $plugin */
    $plugin = $app->getClient();
    $authCodePlugin = $this->grantPluginManager->createInstance($plugin->getGrantType());
    $redirect = $authCodePlugin->getPostCaptureRedirect($plugin);
    $this->assertInstanceOf(RedirectResponse::class, $redirect);
    $this->assertStringEndsWith('admin/config/system/oauth2-client', $redirect->getTargetUrl());
  }

  /**
   * @covers \Drupal\oauth2_client\Plugin\Oauth2GrantType\ClientCredentials::getAccessToken
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\TempStoreTokenStorage::storeAccessToken
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\TempStoreTokenStorage::retrieveAccessToken
   * @covers \Drupal\oauth2_client\Plugin\Oauth2Client\TempStoreTokenStorage::clearAccessToken
   */
  public function testClientCredGetToken(): void {
    $app = $this->getApp('client_cred_test');
    /** @var \Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client\ClientCredTest $plugin */
    $plugin = $app->getClient();
    /** @var \Drupal\oauth2_client\Plugin\Oauth2GrantType\ClientCredentials $clientCodePlugin */
    $clientCodePlugin = $this->grantPluginManager->createInstance($plugin->getGrantType());
    $token = $clientCodePlugin->getAccessToken($plugin);
    $this->assertInstanceOf(AccessTokenInterface::class, $token);
    $plugin->clearAccessToken();
    $token = $plugin->retrieveAccessToken();
    $this->assertNull($token);
    $history = $plugin->getHistory();
    $roundTrip = reset($history);
    $request = $roundTrip['request'] ?? NULL;
    $this->assertInstanceOf(Request::class, $request);
    if ($request instanceof Request) {
      $body = [];
      parse_str($request->getBody()->getContents(), $body);
      $this->assertArrayHasKey('scope', $body);
      $this->assertEquals('test-1,test-2', $body['scope']);
    }
  }

  /**
   * @covers \Drupal\oauth2_client\Plugin\Oauth2GrantType\ResourceOwner::getAccessToken
   */
  public function testResourceOwnerGetToken(): void {
    $app = $this->getApp('res_owner_test');
    /** @var \Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client\ResOwnerTest $plugin */
    $plugin = $app->getClient();
    /** @var \Drupal\oauth2_client\Plugin\Oauth2GrantType\ResourceOwner $resOwnerPlugin */
    $resOwnerPlugin = $this->grantPluginManager->createInstance($plugin->getGrantType());
    $username = 'test-user';
    $password = 'test-pass';
    $resOwnerPlugin->setUsernamePassword(new OwnerCredentials($username, $password));
    $token = $resOwnerPlugin->getAccessToken($plugin);
    $this->assertInstanceOf(AccessTokenInterface::class, $token);
    $history = $plugin->getHistory();
    $roundtrip = reset($history);
    $request = $roundtrip['request'] ?? NULL;
    $this->assertInstanceOf(Request::class, $request);
    if ($request instanceof Request) {
      $body = [];
      parse_str($request->getBody()->getContents(), $body);
      $this->assertArrayHasKey('username', $body);
      $this->assertArrayHasKey('password', $body);
      $this->assertEquals($username, $body['username']);
      $this->assertEquals($password, $body['password']);
    }
  }

  /**
   * @covers \Drupal\oauth2_client\Plugin\Oauth2GrantType\RefreshToken::getAccessToken
   */
  public function testRefreshGetToken(): void {
    $refresh = $this->grantPluginManager->createInstance('refresh_token');
    $app = $this->getApp('client_cred_test');
    /** @var \Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client\ClientCredTest $plugin */
    $plugin = $app->getClient();
    /** @var \Drupal\oauth2_client\Plugin\Oauth2GrantType\ClientCredentials $clientCodePlugin */
    $clientCodePlugin = $this->grantPluginManager->createInstance($plugin->getGrantType());
    $token = $clientCodePlugin->getAccessToken($plugin);
    $this->assertInstanceOf(AccessTokenInterface::class, $token);
    $plugin->storeAccessToken($token);
    $refreshedToken = $refresh->getAccessToken($plugin);
    $this->assertInstanceOf(AccessTokenInterface::class, $refreshedToken);
  }

}
