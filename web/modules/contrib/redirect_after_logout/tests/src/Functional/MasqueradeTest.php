<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Test redirecting with masquerade module.
 *
 * @group redirect_after_logout
 */
class MasqueradeTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'masquerade',
    'node',
    'redirect_after_logout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Add user login block and account menu
    $this->placeBlock('masquerade', [
      'id' => $this->defaultTheme . '_' . strtolower($this->randomMachineName(8)),
      'label' => 'masquerade',
    ]);
    $this->placeBlock('system_menu_block:account', [
      'id' => $this->defaultTheme . '_' . strtolower($this->randomMachineName(8)),
      'label' => 'system_menu_block:account',
    ]);
    // Add neccessary permission for authenticated user role.
    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), ['masquerade as any user']);
  }

  /**
   * Avoid redirect, if masquerade module is enabled.
   */
  public function testRedirectingWithMasquerade() {
    $message = $this->randomMachineName();
    $this->drupalLogin($this->adminUser);
    $edit = [
      'edit-redirect-after-logout-destination' => '/foobar-example',
      'edit-redirect-after-logout-message' => $message,
    ];
    $this->setRedirectConfig($edit);
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Node for masquerade test',
    ]);
    $this->drupalGet($node->toUrl()->toString());
    // Masquerade regular user.
    $edit = [
      'masquerade_as' => $this->regularUser->getAccountName(),
    ];
    $this->submitForm($edit, 'Switch');
    // Unmasquerade and check path.
    $this->clickLink('Unmasquerade');
    $this->assertSession()->addressNotEquals('/foobar-example');
  }
}
