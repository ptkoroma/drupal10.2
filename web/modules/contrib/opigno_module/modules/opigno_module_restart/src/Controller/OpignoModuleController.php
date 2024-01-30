<?php

namespace Drupal\opigno_module_restart\Controller;

use Drupal\group\Entity\Group;
use Drupal\opigno_learning_path\LPStatusInterface;
use Drupal\opigno_module\Controller\OpignoModuleController as ModuleControllerBase;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Overrides the default OpignoModuleController in order to restart activities.
 *
 * @package Drupal\opigno_module_restart\Controller
 */
class OpignoModuleController extends ModuleControllerBase {

  /**
   * {@inheritdoc}
   */
  public function takeModule(Request $request, Group $group, OpignoModuleInterface $opigno_module) {
    // If the training is already finished and the module is attempted in scope
    // of the latest LP attempt, redirect the user to the module result page.
    $lp_attempt = $opigno_module->getTrainingActiveAttempt($this->currentUser, $group);
    $module_attempt = $opigno_module->getLastModuleAttempt((int) $this->currentUser->id(), (int) $group->id(), TRUE);

    if ($lp_attempt instanceof LPStatusInterface
      && $lp_attempt->isFinished()
      && $module_attempt instanceof UserModuleStatusInterface
      && $module_attempt->isFinished()
    ) {
      return $this->redirect('opigno_module.module_result', [
        'opigno_module' => $opigno_module->id(),
        'user_module_status' => $module_attempt->id(),
      ]);
    }

    return parent::takeModule($request, $group, $opigno_module);
  }

}
