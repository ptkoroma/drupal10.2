<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client;

use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auth code with access example.
 *
 * @Oauth2Client(
 *   id = "quick_expire_test",
 *   name = @Translation("Quick Expiring Test plugin"),
 *   grant_type = "client_credentials",
 *   authorization_uri = "https://www.example.com/oauth/authorize",
 *   token_uri = "https://www.example.com/oauth/token",
 *   resource_owner_uri = "https://www.example.com/userinfo"
 * )
 */
class QuickExpireTest extends Oauth2ClientPluginBase {
  use MockClientTrait;

  /**
   * Array to hold response history.
   *
   * @var array
   */
  protected array $responses = [];

  /**
   * Access Token storage implementation.
   */
  private PrivateTempStore $tempStore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tempStore = $container->get('tempstore.private')->get('authcode_private_temp_store_example');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function storeAccessToken(AccessTokenInterface $accessToken): void {
    $key = 'oauth2_client_access_token-' . $this->getId();
    $this->tempStore->set($key, $accessToken);
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken(): ?AccessTokenInterface {
    $key = 'oauth2_client_access_token-' . $this->getId();
    return $this->tempStore->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function clearAccessToken(): void {
    $key = 'oauth2_client_access_token-' . $this->getId();
    $this->tempStore->delete($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getProvider(): GenericProvider {
    $provider = parent::getProvider();
    $client = $this->getClient(['expires_in' => 5]);

    $provider->setHttpClient($client);
    return $provider;
  }

  /**
   * Override the trait function to build a mock Guzzle client with 2 responses.
   *
   * @param array $overrides
   *   Array of response overrides.
   *
   * @return \GuzzleHttp\Client
   *   The configured client.
   */
  protected function getClient(array $overrides): Client {
    $responses = [];
    $responses[] = $this->getResponse($overrides);
    $responses[] = $this->getResponse($overrides + ['access_token' => 'replacement_test_token']);
    $handler = new MockHandler($responses);
    $handlerStack = HandlerStack::create($handler);

    // Add a history tracker.
    $history = Middleware::history($this->responses);
    $handlerStack->push($history);

    $client = new Client([
      'handler' => $handlerStack,
    ]);
    return $client;
  }

}
