<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoAnswerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a Answer revision.
 *
 * @ingroup opigno_module
 */
class OpignoActivityRevisionRevertForm extends ConfirmFormBase {

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
    return 'opigno_activity_revision_revert';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to revert the revision?');
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
    return t('Revert');
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
    // The revision timestamp will be updated when the revision is saved.
    $this->revision = $this->prepareRevertedRevision($this->revision);
    $this->revision->save();

    $this->logger('content')->notice('Answer: reverted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    \Drupal::messenger()->addMessage(t('Answer %title has been reverted.', ['%title' => $this->revision->label()]));
    $form_state->setRedirect('entity.opigno_activity.version_history', ['opigno_activity' => $this->revision->id()]);
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\opigno_module\Entity\OpignoActivityInterface $revision
   *   The revision to be reverted.
   *
   * @return \Drupal\opigno_module\Entity\OpignoActivityInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevertedRevision(OpignoActivityInterface $revision): OpignoActivityInterface {
    $revision->isDefaultRevision(TRUE);
    $revision->setCreatedTime(\Drupal::time()->getRequestTime());
    return $revision;
  }

}
