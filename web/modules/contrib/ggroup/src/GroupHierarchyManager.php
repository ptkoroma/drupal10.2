<?php

namespace Drupal\ggroup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ggroup\Graph\GroupGraphStorageInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoader;

/**
 * Manages the relationship between groups (as subgroups).
 */
class GroupHierarchyManager implements GroupHierarchyManagerInterface {

  /**
   * The group graph storage.
   *
   * @var \Drupal\ggroup\Graph\GroupGraphStorageInterface
   */
  protected $groupGraphStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $membershipLoader;

  /**
   * Constructs a new GroupHierarchyManager.
   *
   * @param \Drupal\ggroup\Graph\GroupGraphStorageInterface $group_graph_storage
   *   The group graph storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoader $membership_loader
   *   The group membership loader.
   */
  public function __construct(GroupGraphStorageInterface $group_graph_storage, EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoader $membership_loader) {
    $this->groupGraphStorage = $group_graph_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;

  }

  /**
   * {@inheritdoc}
   */
  public function addSubgroup(GroupContentInterface $group_content) {
    $plugin = $group_content->getContentPlugin();

    if ($plugin->getEntityTypeId() !== 'group') {
      throw new \InvalidArgumentException('Given group content entity does not represent a subgroup relationship.');
    }

    $parent_group = $group_content->getGroup();
    /** @var \Drupal\group\Entity\GroupInterface $child_group */
    $child_group = $group_content->getEntity();

    if ($parent_group->id() === NULL) {
      throw new \InvalidArgumentException('Parent group must be saved before it can be related to another group.');
    }

    if ($child_group->id() === NULL) {
      throw new \InvalidArgumentException('Child group must be saved before it can be related to another group.');
    }

    $new_edge_id = $this->groupGraphStorage->addEdge($parent_group->id(), $child_group->id());

    // @todo Invalidate some kind of cache?
  }

  /**
   * {@inheritdoc}
   */
  public function removeSubgroup(GroupContentInterface $group_content) {
    $plugin = $group_content->getContentPlugin();

    if ($plugin->getEntityTypeId() !== 'group') {
      throw new \InvalidArgumentException('Given group content entity does not represent a subgroup relationship.');
    }

    $parent_group = $group_content->getGroup();

    $child_group_id = $group_content->get('entity_id')->getValue();

    if (!empty($child_group_id)) {
      $child_group_id = reset($child_group_id)['target_id'];
      $this->groupGraphStorage->removeEdge($parent_group->id(), $child_group_id);
    }

    // @todo Invalidate some kind of cache?
  }

  /**
   * {@inheritdoc}
   */
  public function groupHasSubgroup(GroupInterface $group, GroupInterface $subgroup) {
    return $this->groupGraphStorage->isDescendant($subgroup->id(), $group->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSubgroups($group_id) {
    $subgroup_ids = $this->getGroupSubgroupIds($group_id);
    return $this->entityTypeManager->getStorage('group')->loadMultiple($subgroup_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSubgroupIds($group_id) {
    return $this->groupGraphStorage->getDescendants($group_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSupergroups($group_id) {
    $subgroup_ids = $this->getGroupSupergroupIds($group_id);
    return $this->entityTypeManager->getStorage('group')->loadMultiple($subgroup_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSupergroupIds($group_id) {
    return $this->groupGraphStorage->getAncestors($group_id);
  }

}
