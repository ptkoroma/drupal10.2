<?php

/**
 * Created by PhpStorm.
 * User: wayne
 * Date: 2/6/17
 * Time: 10:53 AM
 */

namespace Drupal\form_options_attributes_test\Tests;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the #options_attributes functionality of select, checkboxes, and radios.
 *
 * @group Form Options Attributes
 */
class FormOptionsAttributesTest extends BrowserTestBase {

  protected static $modules = ['options', 'form_options_attributes', 'form_options_attributes_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp():void {
    parent::setUp();

    // Create test user.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);

  }

  /**
   * Test form #options_attributes on select elements
   */
  public function testSelect() {
    $this->drupalGet('/form-options-attributes-test');
    $this->assertSession()->elementAttributeContains('css', 'select option.southeast', 'data-bbq-meat', 'pork');
  }

  /**
   * Test form #options_attributes on select elements with option groups
   */
  public function testOptGroupSelect() {
    $this->drupalGet('form-options-attributes-test-optgroup');
    $this->assertSession()->elementAttributeContains('css', 'select option.southeast', 'data-bbq-meat', 'pork');
  }

  /**
   * Test form #options_attributes on radios elements
   */
  public function testRadios() {
    $this->drupalGet('form-options-attributes-test');
    // #options_attributes test.
    $this->assertSession()->elementAttributeContains('css', 'input.southeast.form-radio', 'data-bbq-meat', 'pork');
    // #options_wrapper_attributes test.
    $this->assertSession()->elementAttributeContains('css', 'div.southeast-wrapper input.southeast.form-radio', 'data-bbq-meat', 'pork');
    // #options_label_attributes test.
    $this->assertSession()->elementAttributeContains('css', 'label.southeast-label', 'data-bbq-meat', 'pork');
  }

  /**
   * Test form #options_attributes on checkboxes elements
   */
  public function testCheckboxes() {
    $this->drupalGet('form-options-attributes-test');
    // #options_attributes test.
    $this->assertSession()->elementAttributeContains('css', 'input.southeast.form-checkbox', 'data-bbq-meat', 'pork');
    // #options_wrapper_attributes test.
    $this->assertSession()->elementAttributeContains('css', 'div.southeast-wrapper input.southeast.form-checkbox', 'data-bbq-meat', 'pork');
    // #options_label_attributes test.
    $this->assertSession()->elementAttributeContains('css', 'label.southeast-label', 'data-bbq-meat', 'pork');

  }

}
