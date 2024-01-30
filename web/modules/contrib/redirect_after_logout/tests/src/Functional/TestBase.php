<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * Redirect after logout test base.
 *
 * @group redirect_after_logout
 */
abstract class TestBase extends BrowserTestBase {

  use BlockCreationTrait;
  use PathAliasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'redirect_after_logout',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The authenticated user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $authenticatedUser;

  /**
   * The regular user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $regularUser;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access user profiles',
    ]);
    $this->authenticatedUser = $this->drupalCreateUser();
    $this->regularUser = $this->drupalCreateUser([
      'redirect user after logout',
    ]);
    // Add user login block.
    $this->placeBlock('user_login_block', [
      'id' => $this->defaultTheme . '_' . strtolower($this->randomMachineName(8)),
      'label' => 'user_login_block',
    ]);
    // Add neccessary permission for anonymous user role.
    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['access user profiles']);
    // Create test node.
    $this->createContentType(['type' => 'page']);
    $this->testNode = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Example node title',
    ]);
    $this->createPathAlias($this->testNode->toUrl('canonical', ['base_url' => ''])->toString(), '/foobar-example');
    // Create test configuration for tokens.
    $config = $this->config('system.site');
    $config->set('name', 'example');
    $config->save();
  }

  /**
   * Logs a user out of the Mink controlled browser, without check login page.
   *
   * @see \Drupal\Tests\UiHelperTrait::drupalLogout
   */
  protected function drupalLogoutWithoutCheck() {
    // Make a request to the logout page, and redirect to the user page, the
    // idea being if you were properly logged out you should be seeing a login
    // screen.
    $destination = Url::fromRoute('user.page')->toString();
    $this->drupalGet(Url::fromRoute('user.logout', [], ['query' => ['destination' => $destination]]));
    // @see BrowserTestBase::drupalUserIsLoggedIn()
    unset($this->loggedInUser->sessionId);
    $this->loggedInUser = FALSE;
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
  }

  /**
   * Helper method for logout testing: redirect.
   *
   * @param \Drupal\user\Entity\User $user
   *   Tested user.
   * @param string $path
   *   Expected path.
   * @param string $message
   *   Given message.
   * @param string $message_type
   *   Given message type, possible values: 'status', 'warning', 'error'.
   *   Default value: status
   * @param bool $logout_check
   *   If TRUE, use drupalLogout() method. Otherwise
   *   use drupalLogoutWithoutCheck() method.
   */
  protected function logoutRedirectHelper(User $user, string $path, string $message = '', string $message_type = 'status', bool $logout_check = TRUE) {
    $this->drupalLogin($user);
    if ($logout_check) {
      $this->drupalLogout();
    }
    else {
      $this->drupalLogoutWithoutCheck();
    }
    $this->assertSession()
      ->addressEquals($path);
    if ($message !== '') {
      $this->assertSession()
        ->responseContains($message);
      // Check status message type
      $status_element = $this->assertSession()->elementExists('css', 'div[data-drupal-messages=""] h2.visually-hidden');
      $status_element_message_type = preg_replace('/\smessage$/', '', strtolower($status_element->getText()));
      $this->assertSame($status_element_message_type, $message_type, 'Status message type is corresponding.');
    }
  }

  /**
   * Helper method for logout testing: not redirect.
   *
   * @param \Drupal\user\Entity\User $user
   *   Tested user.
   * @param string $path
   *   Not expected path.
   * @param string $message
   *   Given message.
   * @param bool $logout_check
   *   If TRUE, use drupalLogout() method. Otherwise
   *   use drupalLogoutWithoutCheck() method.
   */
  protected function logoutNotRedirectHelper(User $user, string $path, string $message = '', bool $logout_check = TRUE) {
    $this->drupalLogin($user);
    if ($logout_check) {
      $this->drupalLogout();
    }
    else {
      $this->drupalLogoutWithoutCheck();
    }
    $this->assertSession()
      ->addressNotEquals($path);
    if ($message !== '') {
      $this->assertSession()
        ->responseNotContains($message);
    }
  }

  /**
   * Set redirect configuration, without settings form.
   *
   * @param string $destination
   *   Redirect path.
   * @param array $edit
   *   Filled form settings array, include destination.
   * @param \Drupal\user\Entity\User|null $account
   *   Administrator user account.
   * @param string|null $given_destination
   *   Given destination path.
   *   Use it if you need to change destination, after submit settings form.
   */
  protected function setRedirectConfig(array $edit = [], User $account = NULL, string $given_destination = NULL) {
    if ($account instanceof UserInterface) {
      $this->drupalLogin($account);
    }
    else {
      $this->drupalLogin($this->adminUser);
    }
    if ($given_destination === NULL) {
      $given_destination = $edit['edit-redirect-after-logout-destination'];
    }
    $this->drupalGet('admin/config/system/redirect_after_logout');
    $this->submitForm($edit, 'Save configuration');
    // Configuration submitted.
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');
    // Check submitted form and saved configuration.
    $this->assertSession()
      ->addressEquals('admin/config/system/redirect_after_logout');
    $config = $this->config('redirect_after_logout.settings');
    foreach ($edit as $form_item_key => $form_item_value) {
      $config_key = $this->mappingSettingsFormAndConfig($form_item_key);
      if ($form_item_key === 'edit-redirect-after-logout-destination') {
        $this->assertSession()
          ->fieldValueEquals($form_item_key, $given_destination);
        $this::assertEquals($given_destination, $config->get($config_key));
      }
      else {
        $this->assertSession()
          ->fieldValueEquals($form_item_key, $form_item_value);
        $this::assertEquals($form_item_value, $config->get($config_key));
      }
    }
  }

  /**
   * Mapping settings form item key (see element #name property) and configuration key.
   *
   * @param string|NULL $key
   *   If $reverse is FALSE, then use form item key. If $reverse is TRUE, use configuration item key.
   *   If value is NULL, function result is a fully mapping array.
   *
   * @param bool $reverse
   *   Use configuration item key, instead of the settings form item key.
   *
   * @return string|array
   *   If $key value is not NULL, function result is a mapping key. If $key value is NULL,
   *   result is a fully mapping array.
   */
  protected function mappingSettingsFormAndConfig(string $key = NULL, bool $reverse = FALSE) {
    $mapping = [
      'edit-redirect-after-logout-destination' => 'destination',
      'edit-redirect-after-logout-message' => 'message',
      'redirect_after_message_type' => 'message_type',
    ];
    if ($reverse) {
      $mapping = array_reverse($mapping);
    }
    if ($key !== NULL) {
      return $mapping[$key];
    }
    return $mapping;
  }

}
