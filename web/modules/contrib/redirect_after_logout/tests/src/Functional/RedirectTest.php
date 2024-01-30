<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

/**
 * Redirect testing.
 *
 * @group redirect_after_logout
 */
class RedirectTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect_after_logout',
  ];

  /**
   * Test redirecting.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRedirecting() {
    $this->drupalLogin($this->adminUser);
    $message = $this->randomMachineName();
    $edit = [
      'edit-redirect-after-logout-message' => $message,
    ];
    // Not redirect authenticated user.
    $edit['edit-redirect-after-logout-destination'] = $this->authenticatedUser->toUrl('canonical', ['base_url' => ''])->toString();
    $this->setRedirectConfig($edit);
    $this->logoutNotRedirectHelper($this->authenticatedUser, $this->authenticatedUser->toUrl()->toString(), $message);
    // Redirect testing with regular user.
    // Frontpage.
    $edit['edit-redirect-after-logout-destination'] = '<front>';
    $this->setRedirectConfig($edit);
    $this->logoutRedirectHelper($this->regularUser, '/', $message);
    // External path.
    $edit['edit-redirect-after-logout-destination'] = 'http://example.com/';
    $this->setRedirectConfig($edit);
    $this->logoutRedirectHelper($this->regularUser, 'http://example.com/', '', 'status', FALSE);
    // External path with token.
    $edit['edit-redirect-after-logout-destination'] = 'http://[site:name].com/';
    $this->setRedirectConfig($edit);
    $this->logoutRedirectHelper($this->regularUser, 'http://example.com/', '', 'status', FALSE);
    // Valid node path.
    $edit['edit-redirect-after-logout-destination'] = '/foobar-example';
    $this->setRedirectConfig($edit);
    $this->logoutRedirectHelper($this->regularUser, '/foobar-example', $message);
    // Path with token.
    $edit['edit-redirect-after-logout-destination'] = '/foobar-[site:name]';
    $this->setRedirectConfig($edit);
    $this->logoutRedirectHelper($this->regularUser, '/foobar-example', $message);
    // Path only token.
    $edit['edit-redirect-after-logout-destination'] = '[current-user:url]';
    $this->setRedirectConfig( $edit);
    $this->logoutRedirectHelper($this->regularUser, $this->regularUser->toUrl('canonical', ['base_url' => ''])->toString(), $message);
    // Token with left slash: /[current-user:url] transform to [current-user:url] destination.
    $edit['edit-redirect-after-logout-destination'] = '/[current-user:url]';
    $this->setRedirectConfig($edit, NULL, '[current-user:url]');
    $this->logoutRedirectHelper($this->regularUser, $this->regularUser->toUrl('canonical', ['base_url' => ''])->toString(), $message);
  }

  /**
   * Test redirecting with newline-separated message.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRedirectingWithMessageNewLine() {
    $this->drupalLogin($this->adminUser);
    $message = "Line 1.\nLine 2.\n\nLine 3.";
    $edit = [
      'edit-redirect-after-logout-destination' => '/foobar-example',
      'edit-redirect-after-logout-message' => $message,
    ];
    $this->setRedirectConfig($edit);
    $this->logoutRedirectHelper($this->regularUser, '/foobar-example', nl2br($message));
  }

  /**
   * Test redirecting with message types.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRedirectingWithMessageType() {
    $message_types = [
      'status',
      'warning',
      'error'
    ];
    $this->drupalLogin($this->adminUser);
    $message = $this->randomMachineName();
    $edit = [
      'edit-redirect-after-logout-destination' => '/foobar-example',
      'edit-redirect-after-logout-message' => $message,
    ];
    foreach ($message_types as $message_type) {
      $edit['redirect_after_message_type'] = $message_type;
      $this->setRedirectConfig($edit);
      $this->logoutRedirectHelper($this->regularUser, '/foobar-example', $message, $message_type);
    }
  }
}
