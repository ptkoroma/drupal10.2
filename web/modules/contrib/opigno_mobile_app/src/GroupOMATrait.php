<?php

namespace Drupal\opigno_mobile_app;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\opigno_learning_path\LearningPathAccess;

/**
 * Common helper methods for build data for "group" entities.
 */
trait GroupOMATrait {

  use StringTranslationTrait;

  /**
   * Build training start link info for current user.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The LP group to get the start link for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   Array with text and url.
   *
   * @see \Drupal\opigno_learning_path\TwigExtension\DefaultTwigExtension::get_start_link()
   */
  private function getTrainingStartLink(Group $group, AccountInterface $account) {

    $start_link = [
      'text' => '',
      'url' => '',
    ];

    $visibility = $group->field_learning_path_visibility->value;

    // Check if we need to wait validation.
    $member_pending = !LearningPathAccess::statusGroupValidation($group, $account);
    $module_commerce_enabled = \Drupal::moduleHandler()->moduleExists('opigno_commerce');
    $required_trainings = LearningPathAccess::hasUncompletedRequiredTrainings($group, $account);

    // Training is paid.
    if (
      $module_commerce_enabled
      && $group->hasField('field_lp_price')
      && $group->get('field_lp_price')->value != 0
      && !$group->getMember($account)) {
      // Get currency code.
      $cs = \Drupal::service('commerce_store.current_store');
      $store_default = $cs->getStore();
      $default_currency = $store_default ? $store_default->getDefaultCurrencyCode() : '';
      $text = $this->t('Add to cart / @price @currency', [
        '@price' => $group->get('field_lp_price')->value,
        '@currency' => $default_currency,
      ]);
      $route = 'opigno_commerce.subscribe_with_payment';
    }
    // Training is public.
    elseif ($visibility === 'public') {
      $text = $this->t('Start');
      $route = 'opigno_learning_path.steps.type_start';
    }
    // Training is semi-private.
    elseif (!$group->getMember($account)) {
      if ($group->hasPermission('join group', $account)) {
        $text = $this->t('Learn more');
        $route = 'entity.group.canonical';
      }
      else {
        return $start_link;
      }
    }
    // User should be validated or there are some unfinished required trainings.
    elseif ($member_pending || $required_trainings) {
      $text = $required_trainings ? $this->t('Prerequisites Pending') : $this->t('Approval Pending');
      $route = 'entity.group.canonical';
    }
    // In other cases just get canonical start link.
    else {
      $text = opigno_learning_path_started($group, $account) ? $this->t('Continue training') : $this->t('Start');
      $route = 'opigno_learning_path.steps.type_start';
    }

    $host = \Drupal::request()->getSchemeAndHttpHost();
    $url = Url::fromRoute($route, ['group' => $group->id()]);
    $start_link = [
      'text' => $text,
      'url' => $host . '/' . $url->getInternalPath(),
    ];

    return $start_link;
  }

  /**
   * Get image info from media image entity.
   *
   * @param \Drupal\group\Entity\Group $training
   *   The LP group.
   *
   * @return array
   *   The LP image data.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getTrainingImageInfo(Group $training) {
    $info = [];
    // Get media entity.
    if ($media_ref_item = $training->get('field_learning_path_media_image')->first()) {
      $media_entity = $media_ref_item->get('entity')->getTarget();
      // Get image entity.
      if ($img_entity_list = $media_entity->get('field_media_image')) {
        if ($img_entity = $img_entity_list->first()) {
          // Get file entity.
          if ($file_entity = $img_entity->get('entity')->getTarget()) {
            // Build info.
            $uri = $file_entity->get('uri')->getString();
            $style = $this->entityTypeManager->getStorage('image_style')->load('catalog_thumbnail');
            $url = $style->buildUrl($uri);
            $info = [
              'url' => $url,
            ];
          }
        }
      }
    }
    // Get default image url.
    if (empty($info)) {
      $request = \Drupal::request();
      $path = \Drupal::service('module_handler')
        ->getModule('opigno_catalog')
        ->getPath();
      $info = [
        'url' => $request->getSchemeAndHttpHost() . '/' . $path . '/img/img_training.png',
      ];
    }

    return $info;
  }

  /**
   * Helper function for get private trainings where user is a member.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The user account.
   *
   * @return array
   *   Array with trainings ids.
   */
  private function getPrivateTrainingsIds(AccountProxyInterface $account) {

    $database = \Drupal::database();
    $query = $database->select('groups_field_data', 'g');
    $query->condition('g.type', 'learning_path');
    $query->condition('gfv.field_learning_path_visibility_value', 'private');
    $query->condition('gcfd.type', 'learning_path-group_membership');
    $query->condition('gcfd.entity_id', $account->id());
    $query->addJoin(
      'left',
      'group__field_learning_path_visibility',
      'gfv',
      'g.id = gfv.entity_id'
    );
    $query->leftJoin(
      'group_content_field_data',
      'gcfd',
      'g.id = gcfd.gid'
    );
    $query->fields('g', ['id']);
    $private_trainings = $query->execute()->fetchAllAssoc('id');

    return $private_trainings ? array_keys($private_trainings) : [];
  }

}
