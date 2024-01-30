<?php

namespace Drupal\ggroup\Plugin\GroupContentEnabler;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\group\Entity\GroupType;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a content enabler for subgroups.
 *
 * @GroupContentEnabler(
 *   id = "subgroup",
 *   label = @Translation("Subgroup"),
 *   description = @Translation("Adds groups to groups."),
 *   entity_type_id = "group",
 *   entity_access = TRUE,
 *   pretty_path_key = "group",
 *   deriver = "Drupal\ggroup\Plugin\GroupContentEnabler\SubgroupDeriver",
 *   handlers = {
 *     "permission_provider" = "Drupal\group\Plugin\GroupContentPermissionProvider",
 *   },
 * )
 */
class Subgroup extends GroupContentEnablerBase {

  /**
   * Retrieves the group type this plugin supports.
   *
   * @return \Drupal\group\Entity\GroupTypeInterface
   *   The group type this plugin supports.
   */
  protected function getSubgroupType() {
    return GroupType::load($this->getEntityBundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOperations(GroupInterface $group) {
    $account = \Drupal::currentUser();
    $plugin_id = $this->getPluginId();
    $type = $this->getEntityBundle();
    $operations = [];

    if ($group->hasPermission("create $plugin_id entity", $account)) {
      $route_params = [
        'group' => $group->id(),
        'plugin_id' => $plugin_id,
      ];
      $operations["ggroup_create-$type"] = [
        'title' => $this->t('Create @type', ['@type' => $this->getSubgroupType()->label()]),
        'url' => new Url('entity.group_content.create_form', $route_params),
        'weight' => 35,
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();

    $config['entity_cardinality'] = 1;

    // Default parent_role_mapping.
    if ($this->getGroupType()) {
      $parent_roles = $this->getGroupType()->getRoles();
      foreach ($parent_roles as $role_id => $role) {
        $config['parent_role_mapping'][$role_id] = NULL;
      }
    }

    // Default child_role_mapping.
    if ($this->getSubgroupType()) {
      $child_roles = $this->getSubgroupType()->getRoles();
      foreach ($child_roles as $role_id => $role) {
        $config['child_role_mapping'][$role_id] = NULL;
      }
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Disable the entity cardinality field as the functionality of this module
    // relies on a cardinality of 1. We don't just hide it, though, to keep a UI
    // that's consistent with other content enabler plugins.
    $info = $this->t("This field has been disabled by the plugin to guarantee the functionality that's expected of it.");
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return ['config' => ["group.type.{$this->getEntityBundle()}"]];
  }

}
