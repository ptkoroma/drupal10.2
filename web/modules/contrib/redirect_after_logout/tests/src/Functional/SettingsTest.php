<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Test settings page.
 *
 * @group redirect_after_logout
 */
class SettingsTest extends TestBase {

  use PathAliasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect_after_logout',
    'token',
  ];

  /**
   * Test settings page access.
   */
  public function testSettingsPageAccess() {
    $this->drupalGet('admin/config/system/redirect_after_logout');
    self::assertEquals(403, $this->getSession()->getStatusCode());
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('admin/config/system/redirect_after_logout');
    self::assertEquals(403, $this->getSession()->getStatusCode());
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/redirect_after_logout');
    self::assertEquals(200, $this->getSession()->getStatusCode());
  }

  /**
   * Test settings form configuration.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSettingsForm() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/redirect_after_logout');
    // Check form elements.
    foreach ($this->mappingSettingsFormAndConfig() as $form_item_key => $form_item_value) {
      $this->assertSession()
        ->fieldExists($form_item_key);
    }
    $this->assertSession()
      ->buttonExists('edit-submit');
  }

  /**
   * Test destination path validation.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testSettingsFormDestinationValidation() {
    $this->drupalLogin($this->adminUser);
    // Path without slash: invalid.
    $this->drupalGet('admin/config/system/redirect_after_logout');
    $destination = $this->randomMachineName();
    $this->submitForm([
      'redirect_after_logout_destination' => $destination,
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The path '$destination' has to start with a slash.");
    // Invalid path.
    $this->submitForm([
      'redirect_after_logout_destination' => '/' . $destination,
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains("Either the path '/$destination' is invalid or you do not have access to it.");
    // Totally invalid path without token.
    $this->submitForm([
      'redirect_after_logout_destination' => 'fooBarBaz://fooBarBaz',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The path 'fooBarBaz://fooBarBaz' is invalid.");
  }

}
