<?php

namespace Drupal\extra_field_test\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;

/**
 * Extra field Display for one node type.
 *
 * @ExtraFieldDisplay(
 *   id = "one_node_type_test",
 *   label = @Translation("Extra field for first node type"),
 *   description = @Translation("An extra field first node type."),
 *   bundles = {
 *     "node.first_node_type",
 *   }
 * )
 */
class OneNodeTypeTest extends ExtraFieldDisplayBase {

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {

    return ['#markup' => 'Output from OneNodeTypeTest'];
  }

}
