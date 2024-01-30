<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Exception;

/**
 * Exception thrown when trying to retrieve a non-existent OAuth2 Client.
 */
class InvalidOauth2ClientException extends \Exception {

  /**
   * Constructs an InvalidOauth2ClientException object.
   *
   * @param string $invalidClientId
   *   The passed Oauth2 Client ID that was found to be invalid.
   * @param bool $isDisabled
   *   Boolean flag to adjust exception message for plugins that are disabled.
   * @param int $code
   *   The Exception code.
   * @param \Throwable|null $previous
   *   The previous exception used for the exception chaining.
   */
  public function __construct(string $invalidClientId, bool $isDisabled = FALSE, int $code = 0, ?\Throwable $previous = NULL) {
    if (!empty($invalidClientId)) {
      $predicate = $isDisabled ? 'is disabled' : 'does not exist';
      $message = "The OAuth2 Client plugin '" . $invalidClientId . "' " . $predicate;
    }
    else {
      $message = 'An invalid value was passed for the OAuth2 Plugin ID';
    }
    parent::__construct($message, $code, $previous);
  }

}
