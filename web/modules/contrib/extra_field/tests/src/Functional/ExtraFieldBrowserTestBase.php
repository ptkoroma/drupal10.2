<?php

namespace Drupal\Tests\extra_field\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for Extra Field browser tests.
 */
abstract class ExtraFieldBrowserTestBase extends BrowserTestBase {

  /**
   * The theme to use for functional tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * The machine name of the node type used for the test.
   *
   * @var string
   */
  protected $contentTypeName;

  /**
   * Setup the node type with form display.
   *
   * @param string $contentType
   *   Name of node bundle to be generated.
   */
  public function setupContentEntityForm($contentType) {

    $this->contentTypeName = $contentType;
    $this->createContentType(['type' => $contentType]);

    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $contentType,
      'mode' => 'default',
    ]);
  }

  /**
   * Setup the node type with view display.
   *
   * @param string $contentType
   *   Name of node bundle to be generated.
   */
  public function setupContentEntityDisplay($contentType) {

    $this->contentTypeName = $contentType;
    $this->createContentType(['type' => $contentType]);

    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $contentType,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }

  /**
   * Creates a node.
   *
   * @param string $contentType
   *   Content type of the node.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The new node.
   */
  public function createContent($contentType) {

    $this->createContentType(['type' => $contentType]);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => $contentType,
      'title' => $this->randomMachineName(),
    ]);
    $node->save();

    return $node;
  }

}
