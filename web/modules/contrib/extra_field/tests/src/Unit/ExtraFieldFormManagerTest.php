<?php

namespace Drupal\Tests\extra_field\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\extra_field\Plugin\ExtraFieldFormManager
 *
 * @group extra_field
 */
class ExtraFieldFormManagerTest extends UnitTestCase {

  /**
   * The plugin manager under test.
   *
   * @var \Drupal\extra_field\Plugin\ExtraFieldFormManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $formManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->formManager = $this->getMockBuilder('Drupal\extra_field\Plugin\ExtraFieldFormManager')
      ->disableOriginalConstructor()
      ->onlyMethods(['getDefinitions', 'allEntityBundles'])
      ->getMock();
  }

  /**
   * Prepare ::getDefinitions to return the right values.
   *
   * @param array $definitions
   *   The plugin definitions to return.
   */
  protected function prepareDefinitions(array $definitions) {

    $this->formManager->expects($this->any())
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

    $this->formManager->expects($this->any())
      ->method('allEntityBundles')
      ->will($this->returnValueMap($bundlesMap));
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

    $this->prepareDefinitions($definitions);
    $this->prepareEntityBundles($bundles);

    $this->assertEquals(count($this->formManager->getDefinitions()), count($definitions));
    $this->assertEquals($this->formManager->fieldInfo(), $results);
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
          'label' => 'test form node article',
          'description' => 'test description form node article',
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
            'form' => [
              'extra_field_test' => [
                'label' => 'test form node article',
                'description' => 'test description form node article',
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
          'label' => 'test form node article',
          'description' => 'test description form node article',
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
            'form' => [
              'extra_field_test' => [
                'label' => 'test form node article',
                'description' => 'test description form node article',
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
            'come.*',
          ],
          'label' => 'test form 1',
          'description' => 'test description form 1',
          'weight' => 0,
          'visible' => FALSE,
        ],
        'test2' => [
          'id' => 'test2',
          'bundles' => [
            'node.article',
          ],
          'label' => 'test form 2',
          'description' => 'test description form 2',
          'weight' => 2,
          'visible' => TRUE,
        ],
      ],
      // Bundles.
      [
        ['node', ['article', 'story', 'blog']],
        ['come', ['rain', 'shine']],
      ],
      // Results.
      [
        'node' => [
          'article' => [
            'form' => [
              'extra_field_test1' => [
                'label' => 'test form 1',
                'description' => 'test description form 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
              'extra_field_test2' => [
                'label' => 'test form 2',
                'description' => 'test description form 2',
                'weight' => 2,
                'visible' => TRUE,
              ],
            ],
          ],
          'story' => [
            'form' => [
              'extra_field_test1' => [
                'label' => 'test form 1',
                'description' => 'test description form 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
          'blog' => [
            'form' => [
              'extra_field_test1' => [
                'label' => 'test form 1',
                'description' => 'test description form 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
        ],
        'come' => [
          'rain' => [
            'form' => [
              'extra_field_test1' => [
                'label' => 'test form 1',
                'description' => 'test description form 1',
                'weight' => 0,
                'visible' => FALSE,
              ],
            ],
          ],
          'shine' => [
            'form' => [
              'extra_field_test1' => [
                'label' => 'test form 1',
                'description' => 'test description form 1',
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

}
