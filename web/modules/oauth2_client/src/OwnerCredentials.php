<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client;

/**
 * Provides a value object for securely managing usernames and passwords.
 */
final class OwnerCredentials {

  /**
   * Store the values.
   *
   * @param string $username
   *   A username to store as a read only property.
   * @param string $password
   *   A password to store as a read only property.
   */
  public function __construct(private readonly string $username, private readonly string $password) {
  }

  /**
   * Getter method for username.
   *
   * @return string
   *   The username value.
   */
  public function getUsername(): string {
    return $this->username;
  }

  /**
   * Getter method for password.
   *
   * @return string
   *   The string value.
   */
  public function getPassword(): string {
    return $this->password;
  }

}
