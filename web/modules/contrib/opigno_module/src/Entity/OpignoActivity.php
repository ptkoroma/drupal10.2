<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Activity entity.
 *
 * @ingroup opigno_module
 *
 * @ContentEntityType(
 *   id = "opigno_activity",
 *   label = @Translation("Activity"),
 *   bundle_label = @Translation("Activity type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\opigno_module\OpignoActivityListBuilder",
 *     "views_data" = "Drupal\opigno_module\Entity\OpignoActivityViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\opigno_module\Form\OpignoActivityForm",
 *       "add" = "Drupal\opigno_module\Form\OpignoActivityForm",
 *       "edit" = "Drupal\opigno_module\Form\OpignoActivityForm",
 *       "delete" = "Drupal\opigno_module\Form\OpignoActivityDeleteForm",
 *     },
 *     "access" = "Drupal\opigno_module\OpignoActivityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\opigno_module\OpignoActivityHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "opigno_activity",
 *   data_table = "opigno_activity_field_data",
 *   revision_table = "opigno_activity_revision",
 *   revision_data_table = "opigno_activity_field_revision",
 *   translatable = TRUE,
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer activity entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   links = {
 *     "canonical" = "/activity/{opigno_activity}",
 *     "add-page" = "/admin/structure/opigno_activity/add",
 *     "add-form" = "/admin/structure/opigno_activity/add/{opigno_activity_type}",
 *     "edit-form" = "/admin/structure/opigno_activity/{opigno_activity}/edit",
 *     "delete-form" = "/admin/structure/opigno_activity/{opigno_activity}/delete",
 *     "collection" = "/admin/structure/opigno_activity",
 *     "version-history" = "/admin/structure/opigno_activity/{opigno_activity}/revisions",
 *     "revision-revert" = "/admin/structure/opigno_activity/{opigno_activity}/revision/{opigno_activity_revision}/revert",
 *     "revision-delete" = "/admin/structure/opigno_activity/{opigno_activity}/revision/{opigno_activity_revision}/delete",
 *     "revision-preview" = "/activity/{opigno_activity}/revisions/{opigno_activity_revision}/view",
 *   },
 *   bundle_entity_type = "opigno_activity_type",
 *   field_ui_base_route = "entity.opigno_activity_type.edit_form"
 * )
 */
class OpignoActivity extends RevisionableContentEntityBase implements OpignoActivityInterface {

  use EntityChangedTrait;

  /**
   * Static cache of user answers.
   *
   * @var array
   */
  protected $userAnswers = [];

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSkillId() {
    return $this->get('skills_list')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setSkillId($sid) {
    $this->set('skills_list', $sid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSkillLevel() {
    return $this->get('skill_level')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * Returns module.
   */
  public function getModule() {

  }

  /**
   * {@inheritdoc}
   */
  public function getModules(): array {
    $om_ids = \Drupal::database()->select('opigno_module_relationship', 'om')
      ->fields('om', ['parent_id'])
      ->condition('om.child_id', $this->id())
      ->execute()
      ->fetchCol();

    return static::entityTypeManager()->getStorage('opigno_module')->loadMultiple($om_ids);
  }

  /**
   * Returns user answer.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $opigno_module
   *   Opigno module object.
   * @param \Drupal\opigno_module\Entity\UserModuleStatusInterface $attempt
   *   Attempt object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User object.
   * @param null|int $latest_cert_date
   *   Latest certification date.
   *
   * @return mixed
   *   Opigno answer object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUserAnswer(OpignoModuleInterface $opigno_module, UserModuleStatusInterface $attempt, AccountInterface $account, $latest_cert_date = NULL) {
    $cid = $this->id() . '-' . $opigno_module->id() . '-' . $attempt->id() . '-' . $account->id();
    $answer_storage = static::entityTypeManager()->getStorage('opigno_answer');
    if (isset($this->userAnswers[$cid])) {
      return $this->userAnswers[$cid] ? $answer_storage->load($this->userAnswers[$cid]) : NULL;
    }

    $query = $answer_storage->getQuery();
    $query->condition('user_id', $account->id())
      ->condition('user_module_status', $attempt->id())
      ->condition('module', $opigno_module->id())
      ->condition('activity', $this->id());

    if ($latest_cert_date) {
      $query->condition('created', $latest_cert_date, '>');
    }

    $aid = $query->range(0, 1)
      ->execute();
    $id = reset($aid);

    $this->userAnswers[$cid] = $id;
    return $this->userAnswers[$cid] ? $answer_storage->load($this->userAnswers[$cid]) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnswers($return_entities_array = NULL) {
    $answer_storage = static::entityTypeManager()->getStorage('opigno_answer');
    $query = $answer_storage->getQuery();
    $aids = $query->condition('activity', $this->id())->execute();
    if ($return_entities_array) {
      return $answer_storage->loadMultiple($aids);
    }
    return $aids;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Activity entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Activity entity plus.'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSettings([
        'max_length' => 60,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['skills_list'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Skill'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'skills' => 'skills',
          ],
        ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ]);

    $options = [
      1 => t('Level 1'),
      2 => t('Level 2'),
      3 => t('Level 3'),
      4 => t('Level 4'),
      5 => t('Level 5'),
      6 => t('Level 6'),
      7 => t('Level 7'),
      8 => t('Level 8'),
      9 => t('Level 9'),
      10 => t('Level 10'),
    ];

    $fields['skill_level'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Level of skill'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue('local')
      ->setRequired(FALSE)
      ->setSetting('allowed_values', $options)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ]);

    $options = [
      'local' => t('Only in current module'),
      'global' => t('In global system of Opigno skills'),
    ];

    $fields['usage_activity'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Usage of activity'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue('local')
      ->setRequired(TRUE)
      ->setSetting('allowed_values', $options)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 4,
      ]);

    $fields['auto_skills'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Use activity in auto skills management'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 1,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Activity is published.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the Module was created.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the Module was last edited.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);

    $revision_log_field_name = static::getRevisionMetadataKey($this->getEntityType(), 'revision_log_message');
    $revision_created_field_name = static::getRevisionMetadataKey($this->getEntityType(), 'revision_created');

    $new_revision = $this->isNewRevision();

    if (!$new_revision && isset($this->original)
      && (!isset($record->$revision_log_field_name)
        || $record->$revision_log_field_name === '')) {
      $record->$revision_log_field_name = $this->original->getRevisionLogMessage();
    }

    if ($new_revision
      && (!isset($record->$revision_created_field_name)
        || empty($record->$revision_created_field_name))) {
      $record->$revision_created_field_name = $record->id == $this->id()
        ? $this->getCreatedTime()
        : \Drupal::time()->getRequestTime();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evaluationMethodManual(): bool {
    if ($this->hasField('opigno_evaluation_method') && $this->get('opigno_evaluation_method')->value) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function revisionIds(): array {
    return \Drupal::database()->select('opigno_activity_revision', 'oar')
      ->fields('oar', ['vid'])
      ->condition('oar.id', $this->id())
      ->orderBy('oar.revision_created', 'DESC')
      ->execute()
      ->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentRevision() {
    return $this->get('vid')->value;
  }

}
