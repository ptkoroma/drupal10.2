<?php

namespace Drupal\opigno_social_community\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\opigno_social\Entity\OpignoPost;
use Drupal\opigno_social\Services\OpignoPostsManagerInterface;
use Drupal\opigno_social_community\Services\CommunityStatistics;
use Drupal\user\UserInterface;

/**
 * Defines the Opigno community post/comment entity.
 *
 * @ingroup opigno_social_community
 *
 * @ContentEntityType(
 *   id = "opigno_community_post",
 *   label = @Translation("Opigno community post"),
 *   label_singular = @Translation("Opigno community post"),
 *   label_plural = @Translation("Opigno community posts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Opigno community post",
 *     plural = "@count Opigno community posts",
 *   ),
 *   base_table = "opigno_community_post",
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\opigno_social_community\CommunityPostAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *     "community" = "community",
 *     "parent" = "parent",
 *     "created" = "created",
 *   },
 *   links = {
 *     "canonical" = "/community-post/{opigno_community_post}",
 *   },
 * )
 */
class CommunityPost extends OpignoPost implements CommunityPostInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['community'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Community'))
      ->setSetting('target_type', 'opigno_community')
      ->setRequired(TRUE);

    $fields['pinned'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Pinned'))
      ->setDefaultValue(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunityId(): int {
    return $this->get('community')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunity(): ?CommunityInterface {
    $id = $this->getCommunityId();
    $community = Community::load($id);

    return $community instanceof CommunityInterface ? $community : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCommunity(CommunityInterface|int $community): CommunityPostInterface {
    if (!$community instanceof CommunityInterface) {
      Community::load($community);
    }

    if ($community instanceof CommunityInterface) {
      $this->set('community', $community->id());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPinned(): bool {
    return (bool) $this->get('pinned')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setPinned(bool $pinned = TRUE, bool $save = TRUE): CommunityPostInterface {
    $this->set('pinned', $pinned);
    if ($save) {
      $this->save();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPostManagerService(): ?OpignoPostsManagerInterface {
    return \Drupal::service('opigno_social_community.posts_manager');
  }

  /**
   * {@inheritdoc}
   */
  protected function getCommentLikeNotificationMsg(UserInterface $user): TranslatableMarkup {
    return $this->t('@user liked your comment in the community "@community"', [
      '@user' => $user->getDisplayName(),
      '@community' => $this->getCommunity()->getTitle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostLikeNotificationMsg(UserInterface $user): TranslatableMarkup {
    return $this->t('@user liked your message in the community "@community"', [
      '@user' => $user->getDisplayName(),
      '@community' => $this->getCommunity()->getTitle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCommentNotificationMsg(UserInterface $user): TranslatableMarkup {
    return $this->t('@user replied to your post in the community "@community"', [
      '@user' => $user->getDisplayName(),
      '@community' => $this->getCommunity()->getTitle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Update the community statistics record.
    $stats_service = \Drupal::service('opigno_social_community.statistics');
    if ($stats_service instanceof CommunityStatistics) {
      $stats_service->updateAddPost($this->getCommunityId(), $this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();

    // Update the community statistics record.
    $stats_service = \Drupal::service('opigno_social_community.statistics');
    if ($stats_service instanceof CommunityStatistics) {
      $stats_service->updateDeletePost($this->getCommunityId(), $this->id());
    }
  }

}
