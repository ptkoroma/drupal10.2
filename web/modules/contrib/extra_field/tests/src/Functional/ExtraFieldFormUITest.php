<?php

namespace Drupal\Tests\extra_field\Functional;

/**
 * Tests the extra field Form on entity UI pages.
 *
 * @group extra_field
 */
class ExtraFieldFormUITest extends ExtraFieldBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'extra_field',
    'extra_field_test',
    'node',
    'field_ui',
  ];

  /**
   * Entity form display for each content type.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface[]
   */
  protected $entityFrom;

  /**
   * The URL to the manage form interface.
   *
   * @var string[]
   */
  protected $manageFormUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();
    $adminUser = $this->drupalCreateUser(['administer node form display']);
    $this->drupalLogin($adminUser);
    $this->entityFrom['first_node_type'] = $this->setupContentEntityForm('first_node_type');
    $this->manageFormUrl['first_node_type'] = 'admin/structure/types/manage/first_node_type/form-display';
    $this->entityFrom['another_node_type'] = $this->setupContentEntityForm('another_node_type');
    $this->manageFormUrl['another_node_type'] = 'admin/structure/types/manage/another_node_type/form-display';
  }

  /**
   * Test if SingleItemTest plugin is displayed on entity form pages.
   */
  public function testOneNodeTypePlugin() {

    // Check presence 'first_node_type' form page.
    $this->drupalGet($this->manageFormUrl['first_node_type']);
    $this->assertSession()->pageTextContains('Single item test');

    // Check enabled/disabled and weight.
    $this->assertSession()->fieldValueEquals('fields[extra_field_single_item_test][region]', 'content');
    $this->assertSession()->fieldValueEquals('fields[extra_field_single_item_test][weight]', '-30');

    // Check presence 'another_node_type' form page.
    $this->drupalGet($this->manageFormUrl['another_node_type']);
    $this->assertSession()->pageTextContains('Single item test');
  }

}
