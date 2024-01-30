<?php

namespace Drupal\Tests\opigno_learning_path\Functional;

use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedLink;
use \Drupal\opigno_learning_path\Controller\LearningPathStepsController;
use Drupal\opigno_module\Entity\UserModuleStatus;

/**
 * Tests for training complete process.
 *
 * @group opigno_learning_path
 */
class TrainingCompleteTest extends LearningPathBrowserTestBase {

  /**
   * Tests training visibility for users.
   */
  public function testTrainingComplete() {
    // Create a new training.
    $training = $this->createGroup([
      'field_learning_path_visibility' => 'public',
      'field_learning_path_published' => TRUE,
    ]);

    $training->addMember($this->groupCreator);

    // Create module and add to a training.
    $mandatory_module = $this->createOpignoModule();
    $child_module_1 = $this->createOpignoModule();
    $child_module_2 = $this->createOpignoModule();

    // Add modules to training.
    $this->addModuleToTraining($training, $mandatory_module);
    $this->addModuleToTraining($training, $child_module_1, 0);
    $this->addModuleToTraining($training, $child_module_2, 0);

    // Create links.
    $content_mandatory_module = OpignoGroupManagedContent::loadByProperties([
      'group_content_type_id' => 'ContentTypeModule',
      'entity_id' => $mandatory_module->id(),
    ]);
    $content_mandatory_module = reset($content_mandatory_module);

    $content_child_module_1 = OpignoGroupManagedContent::loadByProperties([
      'group_content_type_id' => 'ContentTypeModule',
      'entity_id' => $child_module_1->id(),
    ]);
    $content_child_module_1 = reset($content_child_module_1);

    $content_child_module_2 = OpignoGroupManagedContent::loadByProperties([
      'group_content_type_id' => 'ContentTypeModule',
      'entity_id' => $child_module_2->id(),
    ]);
    $content_child_module_2 = reset($content_child_module_2);

    $link_1 = OpignoGroupManagedLink::createWithValues(
      $content_child_module_1->getGroupId(),
      $content_mandatory_module->id(),
      $content_child_module_1->id(),
      50
    );
    $link_1->save();

    $link_2 = OpignoGroupManagedLink::createWithValues(
      $content_child_module_2->getGroupId(),
      $content_mandatory_module->id(),
      $content_child_module_2->id(),
      0
    );
    $link_2->save();

    $this->createAnswersAndAttempt($training, $mandatory_module, $this->groupCreator, 100);
    $this->createAnswersAndAttempt($training, $child_module_1, $this->groupCreator, 100);

    // Check the status of the training. Should be completed, because all mandatory modules are completed.
    $is_complated = opigno_learning_path_completed_on($training->id(), $this->groupCreator->id());
    $is_complated = $is_complated > 0;
    $this->assertTrue($is_complated, 'The training is completed.');

    // Check achievements.
    $this->drupalGet('/achievements', ['query' => ['preload-progress' => 'true']]);
    // Check if Training has passed status on the achievements page.
    $content = $this->getSession()->getPage()->find('css', '.lp_summary_step_state_passed');
    $this->assertNotEmpty($content, 'Training status is "Passed" on achievements page');
  }

  /**
   * Creates and answers and attempt and finish these.
   */
  private function createAnswersAndAttempt($training, $module, $user, $score) {
    $attempt = UserModuleStatus::create([]);
    $attempt->setModule($module);
    $attempt->setScore($score);
    $attempt->setOwnerId($user->id());
    $attempt->setEvaluated(1);
    $attempt->setFinished(time());
    $attempt->save();

    $activities_ids = array_keys($module->getModuleActivities());

    if (count($activities_ids) > 0) {
      $activities_storage = \Drupal::entityTypeManager()->getStorage('opigno_activity');
      $activities = $activities_storage->loadMultiple($activities_ids);

      // Each Activity and attempt should have answer to complete step.
      foreach ($activities as $activity) {
        $this->createAnswer($activity, $module, $attempt, $user->id(), $score);
      }
    }

    // Reset all static variables.
    drupal_static_reset();

    // Save all achievements.
    $step = opigno_learning_path_get_module_step($training->id(), $user->id(), $module);

    $step['completed on'] = time();
    $step['passed'] = TRUE;

    opigno_learning_path_save_step_achievements($training->id(), $user->id(), $step);
    opigno_learning_path_save_achievements($training->id(), $user->id());

    return $attempt;
  }


}
