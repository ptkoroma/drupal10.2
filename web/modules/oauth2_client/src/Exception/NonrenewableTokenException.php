<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Exception;

/**
 * Allows implementing code to catch and renew with user interaction.
 */
class NonrenewableTokenException extends \Exception {

  /**
   * Construct the exception with a default message.
   *
   * @param string $grantType
   *   The grant type.
   * @param string $message
   *   The Exception message to throw.
   * @param int $code
   *   The Exception code.
   * @param \Throwable|null $previous
   *   The previous exception used for the exception chaining.
   */
  public function __construct(string $grantType = '', string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    if (!empty($message) && !empty($grantType)) {
      $message = "A token obtained using the $grantType grant has expired without a refresh, and cannot be renewed without user interaction";
    }
    parent::__construct($message, $code, $previous);
  }

}
