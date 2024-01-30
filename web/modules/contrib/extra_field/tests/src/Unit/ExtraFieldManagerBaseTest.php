<?php

namespace Drupal\Tests\extra_field\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\extra_field\Plugin\ExtraFieldManagerBase
 *
 * @group extra_field
 */
class ExtraFieldManagerBaseTest extends UnitTestCase {

  /**
   * The ExtraFieldManagerBase under test.
   *
   * @var \Drupal\extra_field\Plugin\ExtraFieldManagerBaseInterface
   */
  protected $baseManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->baseManager = $this->getMockBuilder('Drupal\extra_field\Plugin\ExtraFieldManagerBase')
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getEntityTypeManager',
        'getEntityBundleType',
        'getEntityBundles',
        'getAllContentEntityTypes',
      ])
      ->getMockForAbstractClass();
  }

  /**
   * @covers ::matchEntityBundleKey
   *
   * @dataProvider matchEntityBundleKeyProvider
   *
   * @param array $pluginBundles
   *   Array of entity-bundle pairs the plugin can be used for.
   * @param string $entityBundleKey
   *   The entity-bundle string of a content entity to check.
   * @param bool $match
   *   The match result.
   */
  public function testMatchEntityBundleKey(array $pluginBundles, $entityBundleKey, $match) {

    $result = self::callMethod($this->baseManager, 'matchEntityBundleKey', [
      $pluginBundles,
      $entityBundleKey,
    ]);
    $this->assertEquals($result, $match);
  }

  /**
   * Data provider for matchEntityBundleKey.
   *
   * @return array
   *   Contains:
   *   - pluginBundles (array)
   *   - entityBundleKey (string)
   *   - output as returned by ::matchEntityBundleKey.
   */
  public function matchEntityBundleKeyProvider() {

    return [
      [
        // pluginBundles.
        [],
        // entityBundleKey.
        '',
        // result.
        FALSE,
      ],
      [
        ['entity_type.bundle'],
        '',
        FALSE,
      ],
      [
        [''],
        'entity_type.bundle',
        FALSE,
      ],
      [
        ['entity_type.bundle'],
        'entity_type.bundle',
        TRUE,
      ],
      [
        ['entity_type.*'],
        'entity_type.bundle',
        TRUE,
      ],
      [
        ['entity_type.bundle'],
        'entity_type.other_bundle',
        FALSE,
      ],
      [
        [
          'entity_type.bundle',
          'entity_type.*',
        ],
        'entity_type.other',
        TRUE,
      ],
      [
        ['other_entity_type.bundle'],
        'entity_type.bundle',
        FALSE,
      ],
      [
        [
          'other_entity_type.bundle',
          'other_entity_type.*',
        ],
        'entity_type.bundle',
        FALSE,
      ],
    ];
  }

  /**
   * @covers ::supportedEntityBundles
   *
   * @dataProvider supportedEntityBundlesProvider
   */
  public function testSupportedEntityBundles($entityBundleKeys, $result) {

    $this->baseManager->expects($this->any())
      ->method('getEntityBundleType')
      ->will($this->returnValueMap([
        ['without_bundles', NULL],
        ['node', 'node_type'],
      ]));

    $this->baseManager->expects($this->any())
      ->method('getEntityBundles')
      ->will($this->returnValueMap([
        ['node_type',
          [
            'article' => 'article',
            'page' => 'page',
          ],
        ],
      ]));

    $this->baseManager->expects($this->any())
      ->method('getAllContentEntityTypes')
      ->will($this->returnValue([
        'node',
        'without_bundles',
      ]));

    $supportedEntityBundles = self::callMethod($this->baseManager, 'supportedEntityBundles', [$entityBundleKeys]);
    $this->assertEquals($result, $supportedEntityBundles);
  }

  /**
   * Data provider for supportedEntityBundles.
   *
   * @return array
   *   Contains:
   *   - Entity bundle keys.
   *   - Output as returned by ::supportedEntityBundles.
   */
  public function supportedEntityBundlesProvider() {
    return [
      [
        // Entity bundle keys.
        [''],
        // Result.
        [],
      ],
      [
        // Entity bundle keys.
        ['*'],
        // Result.
        [],
      ],
      [
        // Entity bundle keys.
        ['node.article'],
        // Result.
        [
          'node.article' => [
            'entity' => 'node',
            'bundle' => 'article',
          ],
        ],
      ],
      [
        // Entity bundle keys.
        ['node.*'],
        // Result.
        [
          'node.article' => [
            'entity' => 'node',
            'bundle' => 'article',
          ],
          'node.page' => [
            'entity' => 'node',
            'bundle' => 'page',
          ],
        ],
      ],
      [
        // Entity bundle keys.
        ['no_bundles.*'],
        // Result.
        [
          'no_bundles.no_bundles' => [
            'entity' => 'no_bundles',
            'bundle' => 'no_bundles',
          ],
        ],
      ],
      [
        // All entity types and all bundle keys.
        ['*.*'],
        // Result.
        [
          'node.article' => [
            'entity' => 'node',
            'bundle' => 'article',
          ],
          'node.page' => [
            'entity' => 'node',
            'bundle' => 'page',
          ],
          'without_bundles.without_bundles' => [
            'entity' => 'without_bundles',
            'bundle' => 'without_bundles',
          ],
        ],
      ],
    ];
  }

  /**
   * @covers ::allEntityBundles
   *
   * @dataProvider allEntityBundlesProvider
   *
   * @param string $entityType
   *   The entity type for which to get the bundles.
   * @param string $bundleType
   *   The entity bundle type.
   * @param array $entityBundles
   *   Structured array of entity bundles. Keys and values are equal.
   * @param array $result
   *   The entity bundles as returned by ::allEntityBundles.
   */
  public function testAllEntityBundles($entityType, $bundleType, array $entityBundles, array $result) {

    $this->baseManager->expects($this->any())
      ->method('getEntityBundleType')
      ->will($this->returnValue($bundleType));

    $this->baseManager->expects($this->any())
      ->method('getEntityBundles')
      ->will($this->returnValue($entityBundles));

    $allEntityBundles = self::callMethod($this->baseManager, 'allEntityBundles', [$entityType]);
    $this->assertEquals($result, $allEntityBundles);
  }

  /**
   * Data provider for allEntityBundles.
   *
   * @return array
   *   Contains:
   *   - entityType (string)
   *   - bundleType (string)
   *   - entityBundles (array)
   *   - output as returned by ::allEntityBundles.
   */
  public function allEntityBundlesProvider() {
    return [
      [
        // Entity type.
        'entity_type_without_bundle',
        // Bundle type.
        NULL,
        // Entity bundles.
        [],
        // Result.
        [
          'entity_type_without_bundle' => 'entity_type_without_bundle',
        ],
      ],
      [
        // Entity type.
        'node',
        // Bundle type.
        'node_type',
        // Entity bundles.
        [
          'article' => 'article',
          'page' => 'page',
        ],
        // Result.
        [
          'article' => 'article',
          'page' => 'page',
        ],
      ],
    ];
  }

  /**
   * @covers ::entityBundleKey
   */
  public function testFieldName() {

    $result = self::callMethod($this->baseManager, 'fieldName', ['plugin_id']);
    $this->assertEquals('extra_field_plugin_id', $result);
  }

  /**
   * @covers ::entityBundleKey
   */
  public function testEntityBundleKey() {

    $result = self::callMethod($this->baseManager, 'entityBundleKey', [
      'foo',
      'bar',
    ]);
    $this->assertEquals('foo.bar', $result);
  }

  /**
   * Calls protected methods on an abstract class.
   *
   * @param object $object
   *   The class to call the method on.
   * @param string $name
   *   The method name.
   * @param array $arguments
   *   The arguments to call the method with.
   *
   * @return mixed
   *   The method result.
   */
  protected static function callMethod($object, $name, array $arguments) {
    $class = new \ReflectionClass($object);
    $method = $class->getMethod($name);
    $method->setAccessible(TRUE);
    return $method->invokeArgs($object, $arguments);
  }

}
