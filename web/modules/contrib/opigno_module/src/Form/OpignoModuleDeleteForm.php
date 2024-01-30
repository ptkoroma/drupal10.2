<?php

namespace Drupal\opigno_module\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting Module entities.
 *
 * @ingroup opigno_module
 */
class OpignoModuleDeleteForm extends ContentEntityDeleteForm {

  /**
   * Group content entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $groupContentStorage = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ...$default) {
    parent::__construct(...$default);
    try {
      $this->groupContentStorage = $entity_type_manager->getStorage('group_content');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_module_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['cancel']['#attributes']['class'] = ['btn', 'btn-rounded'];
    $form['actions']['submit']['#attributes']['class'] = [
      'btn',
      'btn-rounded',
      'btn-border-red',
    ];
    // Get the module entity object.
    $form_object = $form_state->getFormObject();

    if (!$form_object instanceof ContentEntityFormInterface || !$this->groupContentStorage instanceof EntityStorageInterface) {
      return $form;
    }

    $module = $form_object->getEntity();
    // Check if module related to at least one group.
    $gid = $this->groupContentStorage->getQuery()
      ->condition('entity_id', $module->id())
      ->condition('type', [
        'group_content_type_162f6c7e7c4fa',
        'group_content_type_411cfb95b8271',
      ], 'IN')
      ->execute();

    // Hide the delete button if it exists as group content.
    if (empty($gid)) {
      return $form;
    }

    $form['description'] = [
      '#markup' => $this->t('This module is being used and it needs to be removed from the trainings and/or courses using it before being able to delete it.'),
    ];
    unset($form['actions']['submit']);

    // Add extra class and update the cancel url for ajax routes.
    if ($this->getRequest()->isXmlHttpRequest()) {
      $form['actions']['cancel']['#url'] = Url::fromRoute('opigno_learning_path.close_modal');
      $form['actions']['cancel']['#attributes']['class'][] = 'use-ajax';
    }

    return $form;
  }

}
