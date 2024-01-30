<?php

namespace Drupal\Tests\extra_field\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\extra_field\Plugin\ExtraFieldDisplayManager
 *
 * @group extra_field
 */
class ExtraFieldDisplayManagerTest extends UnitTestCase {

  /**
   * The plugin manager under test.
   *
   * @var \Drupal\extra_field\Plugin\ExtraFieldDisplayManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $displayManager;

  /**
   * Setup the SuT but allow selected methods to be overridden.
   *
   * @param string[] $methods
   *   The methods to override for test purpose.
   */
  protected function prepareDisplayManager(array $methods) {
    $this->displayManager = $this->getMockBuilder('Drupal\extra_field\Plugin\ExtraFieldDisplayManager')
      ->disableOriginalConstructor()
      ->onlyMethods($methods)
      ->getMock();
  }

  /**
   * Prepare ::getDefinitions to return the right values.
   *
   * @param array $definitions
   *   The plugin definitions to return.
   */
  protected function prepareDefinitions(array $definitions) {

    $this->displayManager->expects($this->any())
      ->method('getDefinitions')
      ->will($this->returnValue($definitions));
  }

  /**
   * Prepare ::allEntityBundles to return the right values.
   *
   * @param array $bundlesMap
   *   Array of bundle names.
   */
  protected function prepareEntityBundles(array $bundlesMap) {

    $this->displayManager->expects($this->any())
      ->method('allEntityBundles')
      ->will($this->returnValueMap($bundlesMap));
  }

  /**
   * Prepare ::allEntityBundleKey to return the right values.
   *
   * @param string $key
   *   The key to return.
   */
  protected function prepareEntityBundleKey($key) {

    $this->displayManager->expects($this->any())
      ->method('entityBundleKey')
      ->will($this->returnValue($key));
  }

  /**
   * Returns a mocked ExtraFieldDisplay object.
   *
   * @return \Drupal\extra_field\Plugin\ExtraFieldDisplayInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked object.
   */
  protected function createMockDisplayPlugin($entity, $display, $viewMode, $build) {
    $plugin = $this->createMock('\Drupal\extra_field\Plugin\ExtraFieldDisplayInterface');

    $plugin->expects($this->any())
      ->method('setEntity')
      ->with($entity);
    $plugin->expects($this->any())
      ->method('setEntityViewDisplay')
      ->with($display);
    $plugin->expects($this->any())
      ->method('setViewMode')
      ->with($viewMode);
    $plugin->expects($this->any())
      ->method('view')
      ->will($this->returnValue($build));

    return $plugin;
  }

  /**
   * @covers ::fieldInfo
   *
   * @dataProvider fieldInfoProvider
   *
   * @param array $definitions
   *   Plugin definitions as returned by ::getDefinitions.
   * @param array $bundles
   *   Entity bundles as returned by ::allEntityBundles.
   * @param array $results
   *   Field info as returned by ::fieldInfo.
   */
  public function testFieldInfo(array $definitions, array $bundles, array $results) {

    $this->prepareDisplayManager(['getDefinitions', 'allEntityBundles']);
    $this->prepareDefinitions($definitions);
    $this->prepareEntityBundles($bundles);

    $this->assertEquals(count($definitions), count($this->displayManager->getDefinitions()));
    $this->assertEquals($results, $this->displayManager->fieldInfo());
  }

  /**
   * Data provider for testFieldInfo().
   */
  public function fieldInfoProvider() {

    $info[] = [
      // Definitions.
      [
        'test' => [
          'id' => 'test',
          'bundles' => ['node.article'],
          'label' => 'test display node article',
          'description' => 'test description display node article',
          'weight' => 0,
          'visible' => FALSE,
        ],
      ],
      // Bundles.
      [],
      // Results.
      [
        'node' => [
          'article' => [
            'display' => [
              'extra_field_test' => [
                'label' => 'test display node article',
                'description' => 'test description display node article',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
        ],
      ],
    ];

    $info[] = [
      // Definitions.
      [
        'test' => [
          'id' => 'test',
          'bundles' => ['node.article'],
          'label' => 'test display node article',
          'description' => 'test description display node article',
          'weight' => 88,
          'visible' => TRUE,
        ],
      ],
      // Bundles.
      [],
      // Results.
      [
        'node' => [
          'article' => [
            'display' => [
              'extra_field_test' => [
                'label' => 'test display node article',
                'description' => 'test description display node article',
                'weight' => 88,
                'visible' => TRUE,
              ],
            ],
          ],
        ],
      ],
    ];

    $info[] = [
      // Definitions.
      [
        'test1' => [
          'id' => 'test1',
          'bundles' => [
            'node.*',
            'voucher.*',
          ],
          'label' => 'test display 1',
          'description' => 'test description display 1',
          'weight' => 0,
          'visible' => FALSE,
        ],
        'test2' => [
          'id' => 'test2',
          'bundles' => [
            'node.article',
          ],
          'label' => 'test display 2',
          'description' => 'test description display 2',
          'weight' => 2,
          'visible' => TRUE,
        ],
      ],
      // Bundles.
      [
        ['node', ['article', 'story', 'blog']],
        ['voucher', ['simple', 'extended']],
      ],
      // Results.
      [
        'node' => [
          'article' => [
            'display' => [
              'extra_field_test1' => [
                'label' => 'test display 1',
                'description' => 'test description display 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
              'extra_field_test2' => [
                'label' => 'test display 2',
                'description' => 'test description display 2',
                'weight' => 2,
                'visible' => TRUE,
              ],
            ],
          ],
          'story' => [
            'display' => [
              'extra_field_test1' => [
                'label' => 'test display 1',
                'description' => 'test description display 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
          'blog' => [
            'display' => [
              'extra_field_test1' => [
                'label' => 'test display 1',
                'description' => 'test description display 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
        ],
        'voucher' => [
          'simple' => [
            'display' => [
              'extra_field_test1' => [
                'label' => 'test display 1',
                'description' => 'test description display 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
          'extended' => [
            'display' => [
              'extra_field_test1' => [
                'label' => 'test display 1',
                'description' => 'test description display 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
        ],
      ],
    ];

    return $info;
  }

  /**
   * @covers ::entityView
   *
   * @dataProvider entityViewProvider
   *
   * @param array $definitions
   *   The plugin definitions as returned by ::getDefinitions().
   * @param string $entityType
   *   The content entity type.
   * @param string $bundle
   *   The content entity bundle.
   * @param bool $hasComponent
   *   The has component flag as returned by
   *   EntityDisplayInterface::getComponent().
   * @param array $result
   *   The ::entityView result.
   */
  public function testEntityView(array $definitions, $entityType, $bundle, $hasComponent, array $result) {

    $this->prepareDisplayManager([
      'getDefinitions',
      'getFactory',
    ]);
    $this->prepareDefinitions($definitions);

    // Mock content entity.
    /** @var \Drupal\Core\Entity\ContentEntityInterface|\PHPUnit\Framework\MockObject\MockObject $contentEntity */
    $contentEntity = $this->createMock('Drupal\Core\Entity\ContentEntityInterface');
    $contentEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->will($this->returnValue($entityType));
    $contentEntity->expects($this->any())
      ->method('bundle')
      ->will($this->returnValue($bundle));

    // Mock entity view display.
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay|\PHPUnit\Framework\MockObject\MockObject $display */
    $display = $this->createMock('Drupal\Core\Entity\Entity\EntityViewDisplay');
    $display->expects($this->any())
      ->method('getComponent')
      ->will($this->returnValue($hasComponent));

    // Mock extra field display plugin.
    /** @var \Drupal\Component\Plugin\Factory\FactoryInterface|\PHPUnit\Framework\MockObject\MockObject $pluginFactory */
    $pluginFactory = $this->createMock('Drupal\Component\Plugin\Factory\FactoryInterface');
    $pluginFactory->expects($this->any())
      ->method('createInstance')
      ->will($this->returnValue($this->createMockDisplayPlugin($contentEntity, $display, 'view_mode', ['mock' => 'display markup'])));

    $this->displayManager->expects($this->any())
      ->method('getFactory')
      ->will($this->returnValue($pluginFactory));

    $build = [];
    $this->displayManager->entityView($build, $contentEntity, $display, 'view_mode');

    $this->assertEquals($result, $build);
  }

  /**
   * Data provider for testFieldInfo().
   */
  public function entityViewProvider() {
    $info[] = [
      // Definitions.
      [],
      // Entity type.
      '',
      // Bundle.
      '',
      // Has component.
      FALSE,
      // Result.
      [],
    ];

    $info[] = [
      // Definitions.
      ['plugin_id' => ['bundles' => []]],
      // Entity type.
      'entity_type',
      // Bundle.
      'bundle',
      // Has component.
      FALSE,
      // Result.
      [],
    ];

    $info[] = [
      // Definitions.
      ['plugin_id' => ['bundles' => []]],
      // Entity type.
      'entity_type',
      // Bundle.
      'bundle',
      // Has component.
      FALSE,
      // Result.
      [],
    ];

    $info[] = [
      // Definitions.
      [
        'test' => [
          'id' => 'test',
          'bundles' => ['node.*'],
          'label' => 'test display node article',
          'weight' => 0,
          'visible' => TRUE,
        ],
      ],
      // Entity type.
      'node',
      // Bundle.
      'article',
      // Has component.
      TRUE,
      // Result.
      ['extra_field_test' => ['mock' => 'display markup']],
    ];

    $info[] = [
      // Definitions.
      [
        'first_test' => [
          'id' => 'test',
          'bundles' => ['node.article'],
          'label' => 'test display node article',
          'weight' => 0,
          'visible' => TRUE,
        ],
        'second_test' => [
          'id' => 'test',
          'bundles' => ['node.page'],
          'label' => 'test display node page',
          'weight' => 0,
          'visible' => TRUE,
        ],
      ],
      // Entity type.
      'node',
      // Bundle.
      'page',
      // Has component.
      TRUE,
      // Result.
      ['extra_field_second_test' => ['mock' => 'display markup']],
    ];

    return $info;
  }

}
