<?php

namespace Drupal\Tests\extra_field\Functional;

/**
 * Tests the view of extra field form plugins.
 *
 * @group extra_field
 */
class ExtraFieldFormViewTest extends ExtraFieldBrowserTestBase {

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
    $editor = $this->drupalCreateUser([
      'create first_node_type content',
      'edit any first_node_type content',
    ]);
    $this->drupalLogin($editor);
  }

  /**
   * Tests visibility of extra field form plugins.
   */
  public function testFormExtraFieldDisplay() {
    $url = $this->content->toUrl('edit-form');
    $this->drupalGet($url);

    $this->assertSession()->pageTextContains('Example markup provided by extra field form plugin.');
  }

}
