<?php

namespace Drupal\Tests\opigno_learning_path\Functional;

use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\UserModuleStatus;

/**
 * Trait TrainingContentTrait.
 */
trait TrainingContentTrait {

  /**
   * Creates a Training.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\group\Entity\Group
   *   The created group entity of type learning_path.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createGroup(array $values = []) {
    /* @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    /* @var \Drupal\group\Entity\Group $group */
    $group = $entity_type_manager->getStorage('group')->create($values + [
      'type' => 'learning_path',
      'label' => $this->randomMachineName(),
    ]);

    $group->enforceIsNew();
    $group->save();

    return $group;
  }

  /**
   * Add Module to a Training.
   *
   * @param \Drupal\group\Entity\Group $training
   *   Group.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   * @param int $is_mandatory
   *   Content is required.
   *
   * @return \Drupal\group\Entity\Group
   *   The training with contents.
   */
  protected function addModuleToTraining(Group $training, OpignoModule $module, $is_mandatory = 1) {
    // Add module as a content to a training.
    $training = $this->createContentForTraining($module, 'ContentTypeModule', $training, $is_mandatory);

    return $training;
  }

  /**
   * Create module with activities.
   *
   * @param array $values
   *   Module values.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModule
   *   The Opigno module with activities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createOpignoModule(array $values = []) {
    /* @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    // Create module.
    /* @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = $entity_type_manager->getStorage('opigno_module')->create($values + [
      'name' => $this->randomMachineName(),
    ]);
    $module->save();

    $activities = $this->createActivities(['type' => 'opigno_long_answer'], 1);

    // Add activities to a modules.
    $opigno_module_controller = \Drupal::service('opigno_module.opigno_module');
    $opigno_module_controller->activitiesToModule($activities, $module);

    return $module;
  }

  /**
   * Creates training content.
   */
  private function createContentForTraining($content_item, $content_type, $group, $is_mandatory = 1) {
    // Create the added item as an group content.
    $group_content = OpignoGroupManagedContent::createWithValues(
      $group->id(),
      $content_type,
      $content_item->id(),
      0,
      $is_mandatory
    );
    $group_content->save();

    $content_types_manager = \Drupal::service('opigno_group_manager.content_types.manager');
    $plugin_definition = $content_types_manager->getDefinition($content_type);
    $added_entity = \Drupal::entityTypeManager()
      ->getStorage($plugin_definition['entity_type'])
      ->load($content_item->id());
    $group->addContent($added_entity, $plugin_definition['group_content_plugin_id']);

    return $group;
  }

  /**
   * Create a list of activities.
   *
   * @param array $values
   *   Array values.
   * @param int $number
   *   Number of activities to be generated.
   *
   * @return array
   *   An array of activities objects.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createActivities(array $values, $number = 1) {
    $activities = [];
    // Long answer activity type considered as default.
    for ($i = 0; $i < $number; $i++) {
      $activity = OpignoActivity::create($values + [
        'label' => $this->randomString(),
      ]);
      $activity->save();
      $activities[] = $activity;
    }

    return $activities;
  }

  /**
   * Create an Answer.
   *
   * @param OpignoActivity $activity
   *   Opigno Activity entity.
   * @param OpignoModule $module
   *   Opigno Module entity.
   * @param UserModuleStatus $attempt
   *   Opigno user module status.
   * @param int $uid
   *   User ID.
   * @param int $score
   *   Score.
   *
   * @return \Drupal\opigno_module\Entity\OpignoAnswer
   *   Opigno Answer entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createAnswer($activity, $module, $attempt, $uid, $score) {
    $answer = \Drupal::entityTypeManager()->getStorage('opigno_answer')->create([
      'type' => 'opigno_long_answer',
      'activity' => $activity->id(),
      'evaluated' => 1,
      'user_module_status' => $attempt->id(),
      'score' => $score,
      'module' => $module->id(),
      'user_id' => $uid,
    ]);

    $answer->save();

    return $answer;
  }

  /**
   * Adds user group role.
   */
  protected function addGroupRoleForUser(Group $group, $user, $roles) {
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    $plugin = $group->getGroupType()->getContentPlugin('group_membership');
    $group_content = GroupContent::create([
      'type' => $plugin->getContentTypeConfigId(),
      'gid' => $group->id(),
      'entity_id' => $user->id(),
      'group_roles' => $roles,
    ]);
    $group_content->save();
  }

}
