<?php

namespace Drupal\Tests\extra_field\Functional;

/**
 * Tests the view of extra field Displays.
 *
 * @group extra_field
 */
class ExtraFieldDisplayViewTest extends ExtraFieldBrowserTestBase {

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
  protected $content;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->content = $this->createContent('first_node_type');
  }

  /**
   * Test if AllNodeTypes plugin is displayed on node page.
   */
  public function testAllNodeTypesView() {

    $url = $this->content->toUrl();
    $this->drupalGet($url);

    $this->assertSession()->pageTextContains('Output from AllNodeTypesTest');
  }

}
