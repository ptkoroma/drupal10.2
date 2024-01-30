<?php

namespace Drupal\opigno_module_restart\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_learning_path\LPStatusInterface;
use Drupal\opigno_module\Form\OpignoAnswerForm as OpignoAnswerFormBase;
use Drupal\opigno_module_restart\Entity\OpignoModule;

/**
 * Overrides the default Opigno answer form.
 *
 * @package Drupal\opigno_module_restart\Form
 */
class OpignoAnswerForm extends OpignoAnswerFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Next');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $this->redirectToResultPage($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function backwardsNavigation(array $form, FormStateInterface $form_state): void {
    parent::backwardsNavigation($form, $form_state);
    $this->redirectToResultPage($form_state);
  }

  /**
   * Redirects to the module result page if the current LP attempt is finished.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function redirectToResultPage(FormStateInterface $form_state): void {
    /** @var \Drupal\opigno_module\Entity\OpignoAnswerInterface $answer */
    $answer = $this->entity;
    $lp = $answer->getLearningPath();
    if (!$lp instanceof GroupInterface) {
      return;
    }

    $uid = (int) $this->currentUser()->id();
    $gid = (int) $lp->id();
    $lp_attempt = OpignoModule::getLastTrainingAttempt($uid, $gid, TRUE);
    if ($lp_attempt instanceof LPStatusInterface && $lp_attempt->isFinished()) {
      $form_state->setRedirect('opigno_module.module_result', [
        'opigno_module' => $answer->getModule()->id(),
        'user_module_status' => $answer->getUserModuleStatus()->id(),
      ]);
    }
  }

}
