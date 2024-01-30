<?php

namespace Drupal\stripe_registration\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Stripe\Subscription;

/**
 * Defines the Stripe subscription entity.
 *
 * @ingroup stripe_registration
 *
 * @ContentEntityType(
 *   id = "stripe_subscription",
 *   label = @Translation("Stripe subscription"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" =
 *   "Drupal\stripe_registration\StripeSubscriptionEntityListBuilder",
 *     "views_data" =
 *   "Drupal\stripe_registration\Entity\StripeSubscriptionEntityViewsData",
 *
 *     "form" = {
 *       "default" =
 *   "Drupal\stripe_registration\Form\StripeSubscriptionEntityForm",
 *       "add" =
 *   "Drupal\stripe_registration\Form\StripeSubscriptionEntityForm",
 *       "edit" =
 *   "Drupal\stripe_registration\Form\StripeSubscriptionEntityForm",
 *       "delete" =
 *   "Drupal\stripe_registration\Form\StripeSubscriptionEntityDeleteForm",
 *     },
 *     "access" =
 *   "Drupal\stripe_registration\StripeSubscriptionEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" =
 *   "Drupal\stripe_registration\StripeSubscriptionEntityHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "stripe_subscription",
 *   admin_permission = "administer stripe subscription entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" =
 *   "/admin/structure/stripe-registration/stripe-subscription/{stripe_subscription}",
 *     "add-form" =
 *   "/admin/structure/stripe-registration/stripe-subscription/add",
 *     "edit-form" =
 *   "/admin/structure/stripe-registration/stripe-subscription/{stripe_subscription}/edit",
 *     "delete-form" =
 *   "/admin/structure/stripe-registration/stripe-subscription/{stripe_subscription}/delete",
 *     "collection" =
 *   "/admin/structure/stripe-registration/stripe-subscription",
 *   },
 *   field_ui_base_route = "stripe_subscription.settings"
 * )
 */
class StripeSubscriptionEntity extends ContentEntityBase implements StripeSubscriptionEntityInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
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
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The owner of this subscription.'))
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
      ->setRequired(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Stripe subscription entity.'))
      ->setSettings([
        'max_length' => 50,
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
      ]);

    $fields['subscription_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subscription ID'))
      ->setDescription(t('The Stripe ID for this subscription.'))
      ->setSettings([
        'max_length' => 50,
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
      ->setRequired(TRUE);

    $fields['plan_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plan ID'))
      ->setDescription(t('The Stripe ID for this plan.'))
      ->setSettings([
        'max_length' => 50,
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
      ->setRequired(TRUE);

    $fields['plan_price_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plan Price ID'))
      ->setDescription(t('The Stripe Price ID for this plan.'))
      ->setSettings([
        'max_length' => 50,
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
      ->setRequired(TRUE);

    $fields['customer_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Customer ID'))
      ->setDescription(t("The Stripe ID for this subscription's customer."))
      ->setSettings([
        'max_length' => 50,
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
      ->setRequired(TRUE);

    // Possible values are trialing, active, past_due, canceled, or unpaid.
    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The Stripe status for this subscription.'))
      ->setSettings([
        'max_length' => 50,
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
      ->setRequired(TRUE);

    $fields['current_period_end'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Current period end'))
      ->setDescription(t('The end of the current pay period for this subscription.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 4,
      ])
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['cancel_at_period_end'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Cancel at period end'))
      ->setDescription(t('Whether this subscription will be cancelled at the end of the current pay period.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 4,
      ])
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage);

    $this->updateUserRoles();
  }

  /**
   * Update user roles.
   */
  public function updateUserRoles() {
    $plans = $this->entityTypeManager()
      ->getStorage('stripe_plan')
      ->loadByProperties([
        'plan_id' => $this->plan_id->value,
      ]);

    if (!$plans) {
      // Because we switched from using price ids to product id, we should also see if there's a corresponding plan
      // with a Price ID. Later, this should be change with an update hook. Fix the bug below first.
      if ($this->plan_price_id->value) {
        $plans = $this->entityTypeManager()->getStorage('stripe_plan')->loadByProperties([
            'plan_id' => $this->plan_price_id->value,
          ]);
      }
    }

    // There's a bug here. If a plan is deleted, then and expiring subscription will fail to remove the
    // Corresponding user roles. We should probably store the roles on the subscriber object and use that to
    // remove roles when the subscription is deleted.
    if ($plans) {
      $plan = reset($plans);
      $roles = $plan->roles->getIterator();

      // Add roles.
      $status = $this->status->value;
      if (!in_array($status, ['canceled', 'unpaid'])) {
        foreach ($roles as $role) {
          $rid = $role->value;
          // $role_entity = $this->entityTypeManager()->getStorage('user_role')->loadByProperties([''])
          $this->getOwner()->addRole($rid);
          \Drupal::logger('stripe_registration')->info('Adding role @rid to user @user for subscription #@sub.', [
            '@rid' => $rid,
            '@user' => $this->getOwner()->label(),
            '@sub' => $this->id(),
          ]);
        }
      }
      // Remove roles.
      else {
        foreach ($roles as $role) {
          $rid = $role->value;
          $this->getOwner()->removeRole($rid);
          \Drupal::logger('stripe_registration')->info('Removing role @rid from user @user for subscription #@sub', [
            '@rid' => $rid,
            '@user' => $this->getOwner()->label(),
            '@sub' => $this->id(),
          ]);
        }
      }

      $this->getOwner()->save();
    }
    else {
      \Drupal::logger('stripe_registration')->info('Could not find local plan matching remote plan id @plan_id.', [
        '@plan_id' => $this->plan_id->value,
      ]);
    }

  }

  /**
   * Update local subscription from upstream subscription.
   *
   * @param \Stripe\Subscription $remote_subscription
   *   The remote Strip subscription.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function updateFromUpstream(Subscription $remote_subscription = NULL) {
    if (!$remote_subscription) {
      $remote_subscription = Subscription::retrieve($this->subscription_id);
    }

    $this->set('name', $remote_subscription->name);
    $this->set('subscription_id', $remote_subscription->id);
    $this->set('plan_id', $remote_subscription->plan->product);
    $this->set('plan_price_id', $remote_subscription->plan->id);
    $this->set('customer_id', $remote_subscription->customer);
    $this->set('status', $remote_subscription->status);
    $this->set('current_period_end', $remote_subscription->current_period_end);
    return $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    /** @var StripeSubscriptionEntity $entity */
    foreach ($entities as $entity) {
      $entity->updateUserRoles();
    }
  }

}
