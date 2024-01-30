<?php

namespace Drupal\ggroup;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupContentInterface;

/**
 * An interface for the group hierarchy manager.
 */
interface GroupHierarchyManagerInterface {

  /**
   * Relates one group to another as a subgroup.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $group_content
   *   The group content representing the subgroup relationship.
   */
  public function addSubgroup(GroupContentInterface $group_content);

  /**
   * Removes the relationship of a subgroup.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $group_content
   *   The group content representing the subgroup relationship.
   */
  public function removeSubgroup(GroupContentInterface $group_content);

  /**
   * Checks if a group has a subgroup anywhere in its descendents.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The parent group whose subgroups will be checked.
   * @param \Drupal\group\Entity\GroupInterface $subgroup
   *   The subgroup that will be searched for within the parent group's
   *   subgroups.
   *
   * @return bool
   *   TRUE if the given group has the given subgroup, or FALSE if not.
   */
  public function groupHasSubgroup(GroupInterface $group, GroupInterface $subgroup);

  /**
   * Loads the subgroups of a given group.
   *
   * @param int $group_id
   *   The group for which subgroups will be loaded.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   An array of subgroups for the given group.
   */
  public function getGroupSubgroups($group_id);

  /**
   * Gets the IDs of the subgroups of a given group.
   *
   * @param int $group_id
   *   The group for which subgroups will be loaded.
   *
   * @return int[]
   *   An array of subgroup IDs for the given group.
   */
  public function getGroupSubgroupIds($group_id);

  /**
   * Loads the supergroups of a given group.
   *
   * @param int $group_id
   *   The group for which supergroups will be loaded.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   An array of supergroups for the given group.
   */
  public function getGroupSupergroups($group_id);

  /**
   * Gets the IDs of the supergroups of a given group.
   *
   * @param int $group_id
   *   The group for which supergroups will be loaded.
   *
   * @return int[]
   *   An array of supergroup IDs for the given group.
   */
  public function getGroupSupergroupIds($group_id);

}
