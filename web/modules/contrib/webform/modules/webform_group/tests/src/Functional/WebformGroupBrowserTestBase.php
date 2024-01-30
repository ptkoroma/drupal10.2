<?php

namespace Drupal\Tests\webform_group\Functional;

use Drupal\group\Entity\GroupRole;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\webform\Traits\WebformBrowserTestTrait;
use Drupal\Tests\webform_node\Traits\WebformNodeBrowserTestTrait;

/**
 * Base class for webform group tests.
 *
 * @see \Drupal\Tests\group\Functional\GroupBrowserTestBase
 */
abstract class WebformGroupBrowserTestBase extends BrowserTestBase {

  use WebformBrowserTestTrait;
  use WebformNodeBrowserTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'group',
    'group_test_config',
    'webform_group',
    'webform_group_test',
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A test user with group creation rights.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupCreator;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->groupCreator = $this->drupalCreateUser($this->getGlobalPermissions());
    $this->drupalLogin($this->groupCreator);

    // Allow all roles to view webform nodes.
    /** @var \Drupal\group\Entity\GroupRoleInterface[] $group_roles */
    $group_roles = GroupRole::loadMultiple();
    foreach ($group_roles as $group_role) {
      $group_role->grantPermission('view group_node:webform entity');
      $group_role->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown():void {
    $this->purgeSubmissions();
    parent::tearDown();
  }

  /**
   * Gets the global (site) permissions for the group creator.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getGlobalPermissions() {
    return [
      'view the administration theme',
      'access administration pages',
      'access group overview',
      'create default group',
      'create other group',
    ];
  }

  /**
   * Creates a group.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\group\Entity\Group
   *   The created group entity.
   */
  protected function createGroup($values = []) {
    $group = $this->entityTypeManager->getStorage('group')->create($values + [
        'type' => 'default',
        'label' => $this->randomMachineName(),
    ]);
    $group->enforceIsNew();
    $group->save();
    return $group;
  }

}
