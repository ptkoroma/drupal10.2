<?php

namespace Drupal\Tests\twig_blocks\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Test the Twig Blocks module install without errors.
 *
 * @group twig_blocks
 */
class InstallTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['twig_blocks'];

  /**
   * Assert that the twig_blocks module installed correctly.
   */
  public function testModuleInstalls() {
    // If we get here, then the module was successfully installed during the
    // setUp phase without throwing any Exceptions. Assert that TRUE is true,
    // so at least one assertion runs, and then exit.
    $this->assertTrue(TRUE, 'Module installed correctly.');
  }

}
