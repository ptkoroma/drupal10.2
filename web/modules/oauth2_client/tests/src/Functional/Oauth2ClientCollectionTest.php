<?php

namespace Drupal\Tests\oauth2_client\Functional;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Verify client collection display.
 *
 * @coversDefaultClass \Drupal\oauth2_client\Entity\Oauth2ClientListBuilder
 *
 * @group oauth2_client
 */
class Oauth2ClientCollectionTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['oauth2_client', 'oauth2_client_test_plugins'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Required setting.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Our entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected EntityTypeInterface $oauth2Type;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->drupalCreateUser([
      'administer site configuration',
      'administer oauth2 clients',
      'access administration pages',
    ]);
    $this->drupalLogin($this->user);
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $typeManager */
    $typeManager = $this->container->get('entity_type.manager');
    $this->oauth2Type = $typeManager->getDefinition('oauth2_client');
  }

  /**
   * Tests that the config page loads with a 200 response.
   */
  public function testCollection() {
    $assertSession = $this->assertSession();
    $this->drupalGet(Url::fromUri('internal:' . $this->oauth2Type->getLinkTemplate('collection')));
    $assertSession->statusCodeEquals(200);
    $assertSession->pageTextContains('Auth Code Test plugin');
  }

}
