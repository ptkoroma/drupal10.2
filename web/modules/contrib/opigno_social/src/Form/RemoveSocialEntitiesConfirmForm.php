<?php

namespace Drupal\opigno_social\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form to remove all user connections.
 *
 * @package Drupal\opigno_social\Form
 */
class RemoveSocialEntitiesConfirmForm extends ConfirmFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * RemoveConnectionsConfirmForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_remove_social_entities_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('opigno_class.social_settings_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t("Are you sure you want to delete all selected social entities?");
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['entities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select entities to be removed'),
      '#options' => [
        'opigno_post' => $this->t('Opigno posts/comments'),
        'user_invitation' => $this->t('User invitations/connections'),
        'opigno_like' => $this->t('Opigno likes'),
      ],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_types = $form_state->getValue('entities');
    $entity_types = array_filter($entity_types);
    if (!$entity_types) {
      return;
    }

    foreach ($entity_types as $entity_type) {
      try {
        $storage = $this->entityTypeManager->getStorage($entity_type);
      }
      catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
        watchdog_exception('opigno_social_exception', $e);
        $storage = NULL;
      }
      if (!$storage instanceof EntityStorageInterface) {
        continue;
      }

      $entities = $storage->loadMultiple();
      if (!$entities) {
        continue;
      }

      // Set batch to remove entities.
      $chunks = array_chunk($entities, 5);
      $operations = [];
      foreach ($chunks as $chunk) {
        $operations[] = [
          [$this, 'deleteEntities'],
          [$chunk, $storage],
        ];
      }

      // Provide all the operations and the finish callback to the batch.
      $batch = [
        'title' => $this->t('Removing social entities (@type)', ['@type' => $entity_type]),
        'operations' => $operations,
        'finished' => [$this, 'batchFinish'],
      ];

      batch_set($batch);
    }

    $this->messenger()->addStatus($this->t('Social entities have been deleted.'));
    $redirect = Url::fromRoute('opigno_class.social_settings_form');
    $form_state->setRedirectUrl($redirect);
  }

  /**
   * Batch operation to delete social entities.
   *
   * @param array $entities
   *   The list of loaded entities that should be removed.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   */
  public function deleteEntities(array $entities, EntityStorageInterface $storage) {
    foreach ($entities as $entity) {
      if (!$entity instanceof OpignoPostInterface) {
        continue;
      }

      try {
        $entity->delete();
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_social_exception', $e);
      }
    }
  }

  /**
   * Finish callback for batch.
   *
   * @param bool $success
   *   TRUE if the update was fully succeeded.
   * @param array $results
   *   Contains individual results per operation.
   * @param array $operations
   *   Contains the unprocessed operations that failed or weren't touched yet.
   */
  public function batchFinish(bool $success, array $results, array $operations): void {
    if ($success) {
      $this->messenger()->addStatus($this->formatPlural(count($results), '1 entity was removed.', '@count entities were removed.'));
    }
    else {
      $this->messenger()->addError($this->t('Something went wrong, batch finished with an error.'));
    }
  }

}
