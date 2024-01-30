<?php

namespace Drupal\opigno_social_community\Plugin\Block;

use Drupal\opigno_social\Plugin\Block\SocialWallBlock;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Form\CreateCommunityPostForm;
use Drupal\views\Views;

/**
 * Provides the community posts feed block.
 *
 * @Block(
 *   id = "opigno_social_community_feed_block",
 *   admin_label = @Translation("Community feed block"),
 *   category = @Translation("Opigno Social Community"),
 * )
 */
class CommunityFeedBlock extends SocialWallBlock {

  /**
   * Whether the current user is a community member or not.
   *
   * @var bool|null
   */
  private ?bool $isMember = NULL;

  /**
   * Checks if the current user is a community member or not.
   *
   * @return bool
   *   Whether the current user is a community member or not.
   */
  private function isCommunityMember(): bool {
    if (!is_null($this->isMember)) {
      return $this->isMember;
    }

    $community = $this->configuration['community'] ?? NULL;
    $this->isMember = $community instanceof CommunityInterface && $community->isMember($this->user->id());

    return $this->isMember;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostForm(): array {
    return $this->isCommunityMember()
      ? $this->formBuilder->getForm(CreateCommunityPostForm::class)
      : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAttachmentLinks(): array {
    return $this->isCommunityMember() ? parent::getAttachmentLinks() : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getUserInfo(): array {
    return $this->isCommunityMember() ? parent::getUserInfo() : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getView(): ?array {
    $community = $this->configuration['community'] ?? NULL;

    if ($community instanceof CommunityInterface && $community->access('view_feed')) {
      return Views::getView('community_posts')->executeDisplay('feed_block');
    }

    return ['#theme' => 'opigno_community_restricted_feed'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAttachments(array $view_attachments): array {
    return $view_attachments;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getShareableContentRoute(): string {
    return 'opigno_social_community.get_shareable_content';
  }

}
