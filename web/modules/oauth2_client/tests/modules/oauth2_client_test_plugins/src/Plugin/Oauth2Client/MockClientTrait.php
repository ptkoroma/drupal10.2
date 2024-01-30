<?php

namespace Drupal\oauth2_client_test_plugins\Plugin\Oauth2Client;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Allows plugins to build a Guzzle Client with MockHandler.
 */
trait MockClientTrait {

  /**
   * Array to hold response history.
   *
   * @var array
   */
  protected array $responses = [];

  /**
   * Helper function to build a mock Guzzle client.
   *
   * @param array $overrides
   *   Array of response overrides.
   *
   * @return \GuzzleHttp\Client
   *   The configured client.
   */
  protected function getClient(array $overrides = []): Client {
    $response = $this->getResponse($overrides);
    $handler = new MockHandler([$response]);
    $handlerStack = HandlerStack::create($handler);

    // Add a history tracker.
    $history = Middleware::history($this->responses);
    $handlerStack->push($history);

    $client = new Client([
      'handler' => $handlerStack,
    ]);
    return $client;
  }

  /**
   * Gets the history from the Guzzle handler stack.
   *
   * @return array
   *   The history array.
   */
  public function getHistory(): array {
    return $this->responses;
  }

  /**
   * Including as a method so test plugins can override.
   *
   * @param array $overrides
   *   Override default token values.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   A prepared Oauth2 token response.
   */
  protected function getResponse(array $overrides): Response {
    // Specify a default.
    $tokenValues = $overrides + [
      'access_token' => 'test_token',
      'token_type' => 'Bearer',
      'expires_in' => 3600,
      'refresh_token' => 'refresh_test_token',
    ];

    // Assemble the mock client.
    $body = json_encode($tokenValues);
    // Match value to expected types in Response constructor.
    $body = $body === FALSE ? NULL : $body;
    $response = new Response(
      200,
      ['Content-Type' => 'application/json'],
      $body
    );
    return $response;
  }

}
