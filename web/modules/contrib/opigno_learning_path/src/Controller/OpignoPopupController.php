<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Popups controller.
 *
 * @package Drupal\opigno_learning_path\Controller
 */
class OpignoPopupController extends ControllerBase {

  /**
   * OpignoPopupController constructor.
   *
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   */
  public function __construct(EntityFormBuilderInterface $entity_form_builder) {
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder')
    );
  }

  /**
   * Close the modal.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function closeModal(): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand('.modal', 'modal', ['hide']));

    return $response;
  }

  /**
   * Get the delete group confirmation form.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to be deleted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function getDeleteGroupForm(GroupInterface $group): AjaxResponse {
    return $this->getDeleteEntityConfirmPopup($group);
  }

  /**
   * Get the delete opigno_module confirmation form.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $opigno_module
   *   The module entity to be deleted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function getDeleteModuleForm(OpignoModuleInterface $opigno_module): AjaxResponse {
    return $this->getDeleteEntityConfirmPopup($opigno_module);
  }

  /**
   * Get the delete entity confirmation form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be deleted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  private function getDeleteEntityConfirmPopup(EntityInterface $entity): AjaxResponse {
    $response = new AjaxResponse();
    $build = [
      '#theme' => 'opigno_confirmation_popup',
      '#body' => $this->entityFormBuilder->getForm($entity, 'delete'),
    ];

    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $build));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

}
