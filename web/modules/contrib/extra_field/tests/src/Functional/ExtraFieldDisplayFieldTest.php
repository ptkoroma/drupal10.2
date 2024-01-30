<?php

namespace Drupal\Tests\extra_field\Functional;

/**
 * Tests the extra_field Display with field wrapper.
 *
 * @group extra_field
 */
class ExtraFieldDisplayFieldTest extends ExtraFieldBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'extra_field',
    'extra_field_test',
    'node',
  ];

  /**
   * A node that contains the extra fields.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $firstNode;

  /**
   * A second node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $secondNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->firstNode = $this->createContent('first_node_type');
    $this->secondNode = $this->createContent('another_node_type');
  }

  /**
   * Test the output of field with single value item.
   */
  public function testFirstNodeTypeFields() {

    $url = $this->firstNode->toUrl();
    $this->drupalGet($url);

    // Test the output of field with single value item.
    $this->assertSession()->pageTextContains('Output from SingleTextFieldTest');
    $this->assertSession()->pageTextContains('Single text');

    // Test the output of field with multiple value items.
    $this->assertSession()->pageTextContains('Aap');
    $this->assertSession()->pageTextContains('Noot');
    $this->assertSession()->pageTextContains('Zus');

    // Test the output of field with cacheable dependency.
    $this->assertSession()->pageTextContains('Related pages');
    $this->assertSession()->pageTextContains($this->secondNode->label());
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'node:' . $this->secondNode->id());

    // Test the output of the empty field.
    $this->assertSession()->pageTextNotContains('Empty field');
  }

}
