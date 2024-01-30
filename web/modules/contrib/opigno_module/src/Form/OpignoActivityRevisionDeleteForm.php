<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Answer revision.
 *
 * @ingroup opigno_module
 */
class OpignoActivityRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Answer revision.
   *
   * @var \Drupal\opigno_module\Entity\OpignoAnswerInterface
   */
  protected $revision;

  /**
   * The Answer revision.
   *
   * @var \Drupal\opigno_module\Entity\OpignoAnswerInterface
   */
  protected $entity;

  /**
   * The Answer storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $opignoActivityStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new OpignoActivityRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   */
  public function __construct(EntityStorageInterface $entity_storage) {
    $this->opignoActivityStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity_type.manager');
    return new static(
      $entity_manager->getStorage('opigno_activity')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_activity_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the revision?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.opigno_activity.version_history', ['opigno_activity' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $opigno_activity = NULL, $opigno_activity_revision = NULL) {
    $this->revision = $opigno_activity_revision;
    $this->entity = $opigno_activity;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->opignoActivityStorage->deleteRevision($this->revision->getRevisionId());
    $this->logger('content')->notice('Activity: deleted %title revision %revision.', ['%title' => $this->entity->label(), '%revision' => $this->revision->getRevisionId()]);
    \Drupal::messenger()->addMessage(t('Revision of Activity has been deleted.'));
    $form_state->setRedirect('entity.opigno_activity.version_history', ['opigno_activity' => $this->entity->id()]);
  }

}
