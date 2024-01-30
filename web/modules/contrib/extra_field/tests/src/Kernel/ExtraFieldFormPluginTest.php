<?php

namespace Drupal\Tests\extra_field\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\extra_field_test\Plugin\ExtraField\Form\SingleItemTest;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel test for Extra Field form plugins.
 *
 * @group extra_field
 */
class ExtraFieldFormPluginTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'extra_field',
    'extra_field_test',
  ];

  /**
   * Tests basic form plugins.
   *
   * @param string $pluginId
   *   Plugin ID of the plugin under test.
   * @param array $output
   *   Plugin output as returned by ::formElement.
   *
   * @covers \Drupal\extra_field\Plugin\ExtraFieldFormBase::formElement
   *
   * @dataProvider formPluginProvider
   */
  public function testFormPlugin($pluginId, array $output) {

    switch ($pluginId) {
      case 'single_item_test':
        $extraField = new SingleItemTest([], $pluginId, []);
        break;
    }

    $form = [];
    $formState = new FormState();
    $pluginOutput = $extraField->formElement($form, $formState);

    $this->assertEquals($output, $pluginOutput);
  }

  /**
   * Data provider for basic form plugins.
   *
   * @return array
   *   Contains:
   *   - Plugin ID.
   *   - Plugin output as returned by ::formElement.
   */
  public function formPluginProvider() {

    $info[] = [
      'single_item_test',
      [
        '#markup' => 'Example markup provided by extra field form plugin.',
      ],
    ];

    return $info;
  }

}
