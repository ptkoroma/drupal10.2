<?php

namespace Drupal\Tests\extra_field\Kernel;

use Drupal\extra_field_test\Plugin\ExtraField\Display\AllNodeTypesTest;
use Drupal\extra_field_test\Plugin\ExtraField\Display\EmptyFormattedFieldTest;
use Drupal\extra_field_test\Plugin\ExtraField\Display\SingleTextFieldTest;
use Drupal\extra_field_test\Plugin\ExtraField\Display\MultipleItemsFieldTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Kernel test for Extra Field display plugins.
 *
 * @group extra_field
 */
class ExtraFieldDisplayPluginTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'extra_field',
    'extra_field_test',
    'node',
    'field',
    'user',
    'system',
  ];

  /**
   * The plugin manager under test.
   *
   * @var \Drupal\extra_field\Plugin\ExtraFieldDisplayManager
   */
  protected $displayManager;

  /**
   * The node that contains the extra fields under test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    $this->container->get('entity_type.manager')->getStorage('node_type')
      ->create([
        'name' => 'Test',
        'title_label' => 'Title',
        'type' => 'test',
        'create_body' => FALSE,
      ])
      ->save();

    $this->node = $this->container->get('entity_type.manager')->getStorage('node')
      ->create([
        'type' => 'test',
        'title' => $this->randomString(),
      ]);
  }

  /**
   * Tests display basic plugins.
   *
   * @param string $pluginId
   *   Plugin ID of the plugin under test.
   * @param array $output
   *   Plugin output as returned by ::view.
   *
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayBase::view
   *
   * @dataProvider displayPluginProvider
   */
  public function testDisplayPlugin($pluginId, array $output) {

    switch ($pluginId) {
      case 'all_node_types_test':
        $extraField = new AllNodeTypesTest([], $pluginId, []);
        break;
    }

    $pluginOutput = $extraField->view($this->node);
    unset($pluginOutput['#object']);
    unset($pluginOutput['#items']);

    $this->assertEquals($output, $pluginOutput);
  }

  /**
   * Data provider for basic display plugins.
   *
   * @return array
   *   Contains:
   *   - Plugin ID.
   *   - Plugin output as returned by ::view.
   */
  public function displayPluginProvider() {

    $info[] = [
      'all_node_types_test',
      [
        '#markup' => 'Output from AllNodeTypesTest',
      ],
    ];

    return $info;
  }

  /**
   * Tests formatted display plugins.
   *
   * @param string $pluginId
   *   Plugin ID of the plugin under test.
   * @param array $output
   *   Plugin output as returned by ::view.
   * @param array $methods
   *   Return value of various plugin methods.
   *
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::view
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::getLabel
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::getLabelDisplay
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::getFieldName
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::getFieldType
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::isEmpty
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::getLangcode
   * @covers \Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase::isTranslatable
   *
   * @dataProvider displayPluginFormattedProvider
   */
  public function testDisplayFormattedPlugin($pluginId, array $output, array $methods) {

    switch ($pluginId) {

      case 'single_text_test':
        $extraField = new SingleTextFieldTest([], $pluginId, []);
        break;

      case 'empty_formatted_test':
        $extraField = new EmptyFormattedFieldTest([], $pluginId, []);
        break;

      case 'multiple_text_test':
        $extraField = new MultipleItemsFieldTest([], $pluginId, []);
        break;
    }

    $pluginOutput = $extraField->view($this->node);
    unset($pluginOutput['#object']);
    unset($pluginOutput['#items']);

    $pluginMethod = [
      'getLabel' => $extraField->getLabel(),
      'getLabelDisplay' => $extraField->getLabelDisplay(),
      'getFieldName' => $extraField->getFieldName(),
      'getFieldType' => $extraField->getFieldType(),
      'isEmpty' => $extraField->isEmpty(),
      'getLangcode' => $extraField->getLangcode(),
      'isTranslatable' => $extraField->isTranslatable(),
    ];

    $this->assertEquals($output, $pluginOutput);
    $this->assertEquals($methods, $pluginMethod);
  }

  /**
   * Data provider for formatted display plugins.
   *
   * @return array
   *   Contains:
   *   - Plugin ID.
   *   - Plugin output as returned by ::view.
   *   - Return value of various plugin methods. Keyed by their method name.
   */
  public function displayPluginFormattedProvider() {

    $info[] = [
      'single_text_test',
      [
        '#theme' => 'field',
        '#title' => 'Single text',
        '#label_display' => 'inline',
        '#view_mode' => '_custom',
        '#language' => 'und',
        '#field_name' => 'field_single_text',
        '#field_type' => 'single_text',
        '#field_translatable' => FALSE,
        '#entity_type' => 'node',
        '#bundle' => 'test',
        '#formatter' => 'single_text_test',
        '#is_multiple' => FALSE,
        '0' => [
          '#markup' => 'Output from SingleTextFieldTest',
        ],
      ],
      [
        'getLabel' => 'Single text',
        'getLabelDisplay' => 'inline',
        'getFieldName' => 'field_single_text',
        'getFieldType' => 'single_text',
        'isEmpty' => FALSE,
        'getLangcode' => 'und',
        'isTranslatable' => FALSE,
      ],
    ];

    $info[] = [
      'empty_formatted_test',
      [
        '#cache' => [
          'max-age' => 0,
        ],
      ],
      [
        'getLabel' => 'Empty field',
        'getLabelDisplay' => 'inline',
        'getFieldName' => 'extra_field_empty_formatted_test',
        'getFieldType' => 'extra_field',
        'isEmpty' => TRUE,
        'getLangcode' => 'und',
        'isTranslatable' => FALSE,
      ],
    ];

    $info[] = [
      'multiple_text_test',
      [
        '#theme' => 'field',
        '#title' => '',
        '#label_display' => 'hidden',
        '#view_mode' => '_custom',
        '#language' => 'und',
        '#field_name' => 'extra_field_multiple_text_test',
        '#field_type' => 'extra_field',
        '#field_translatable' => FALSE,
        '#entity_type' => 'node',
        '#bundle' => 'test',
        '#formatter' => 'multiple_text_test',
        '#is_multiple' => TRUE,
        '#cache' => [],
        '#children' => '',
        '0' => [
          '#markup' => 'Aap',
        ],
        '1' => [
          '#markup' => 'Noot',
        ],
        '2' => [
          '#markup' => 'Mies',
        ],
        '3' => [
          '#markup' => 'Wim',
        ],
        '4' => [
          '#markup' => 'Zus',
        ],
        '5' => [
          '#markup' => 'Jet',
        ],
      ],
      [
        'getLabel' => '',
        'getLabelDisplay' => 'hidden',
        'getFieldName' => 'extra_field_multiple_text_test',
        'getFieldType' => 'extra_field',
        'isEmpty' => FALSE,
        'getLangcode' => 'und',
        'isTranslatable' => FALSE,
      ],
    ];

    return $info;
  }

}
