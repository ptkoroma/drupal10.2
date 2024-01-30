<?php

namespace Drupal\Tests\opigno_learning_path\Functional;

use Drupal\search_api\Entity\Index;


/**
 * Tests for Search process.
 *
 * @group opigno_learning_path
 */
class SearchPageTest  extends LearningPathBrowserTestBase {
  use TrainingContentTrait;

  // Some keyword - should be found by search.
  protected $key = 'OMJ29zyMZ3nL';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'h5p',
    'block',
    'user',
    'node',
    'group',
    'language',
    'search',
    'views_ui',
    'opigno_messaging',
    'opigno_learning_path',
    'config_rewrite',
    'search_api_db_defaults',
    'opigno_search',
  ];

   /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Login as search admin.
    $adminUser = $this->drupalCreateUser([
      'administer search_api',
      'access administration pages',
    ], 'TestUser');

    $this->drupalLogin($adminUser);
  }

  /**
   * Tests creating a search server via the UI.
   */
  protected function SearchConfigs() {
    $server_name = 'Database Server';
    $server_description = 'Default database server created by the Database Search Defaults module';
    $index_name = 'Default content index';
    $index_description = 'Default content index created by the Database Search Defaults module';

    // Check server exist.
    $this->drupalGet('admin/config/search/search-api/server/default_server');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($server_name);
    $this->assertSession()->pageTextContains($server_description);
    $this->assertSession()->pageTextContains($index_name);

    // Check index exist.
    $this->drupalGet('admin/config/search/search-api');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($index_name);
    $this->assertSession()->pageTextContains($index_description);
  }

  /**
   * Tests creating an indexation content.
   */
  protected function IndexationContent() {
    // Create few trainings.
    for ($i = 0; $i < 5; $i++) {
      $training = $this->createGroup(
        [
          'label' => $this->key . ' ' . $this->randomMachineName(),
          'field_learning_path_visibility' => 'public',
          'field_learning_path_published' => TRUE,
        ]);
      $this->assertNotEmpty($training);
    }

    // Reindex content;
    $index = Index::load('default_index');
    $this->assertNotEmpty($index);

    if (!empty($index)) {
      $index->indexItems();
    }

    // Check if all items indexed.
    $this->drupalGet('admin/config/search/search-api/index/default_index');
    $this->assertSession()->pageTextContains('100%');
  }

  /**
   * Tests search.
   */
  public function testSearchPageContent() {
    $this->SearchConfigs();
    $this->IndexationContent();

    // Check if search works fine.
    $this->drupalGet('/search', ['query' => ['keys' => $this->key]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->key);

    // Check if user-logged-in variable is fine.
    $this->assertSession()->elementExists('css', 'body.user-logged-in');

    // Check if highlight works fine.
    $this->assertSession()->elementsCount('css', '.field-content strong', 5);
  }

}
