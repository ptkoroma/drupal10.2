<?php

namespace Drupal\opigno_social_community;

use Drupal\views\EntityViewsData;

/**
 * Defines the Opigno community entity views data handler.
 *
 * @package Drupal\opigno_social_community
 */
class CommunityViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // View filter to display the user's joined communities.
    $data['opigno_community']['opigno_joined_communities'] = [
      'help' => $this->t('Gets the list of communities joined by the current user.'),
      'real field' => 'id',
      'filter' => [
        'title' => $this->t("Opigno joined communities"),
        'id' => 'opigno_joined_communities',
      ],
    ];

    // View filter to display the communities the user is invited to.
    $data['opigno_community']['opigno_invited_to_communities'] = [
      'help' => $this->t('Gets the list of communities the current user is invited to.'),
      'real field' => 'id',
      'filter' => [
        'title' => $this->t("Opigno invited to communities"),
        'id' => 'opigno_invited_to_communities',
      ],
    ];

    // View filter to display the communities depending on visibility.
    $data['opigno_community']['opigno_community_visibility'] = [
      'help' => $this->t('Filters communities depending on their visibility for the current user.'),
      'real field' => 'visibility',
      'filter' => [
        'title' => $this->t("Opigno community visibility"),
        'id' => 'opigno_community_visibility',
      ],
    ];

    // Integrate community statistics table with views.
    $data['opigno_community_statistics']['table']['group'] = $this->t('Community statistics');
    $data['opigno_community_statistics']['table']['join']['opigno_community'] = [
      'type' => 'LEFT',
      'left_field' => 'id',
      'field' => 'community_id',
    ];
    $data['opigno_community_statistics']['last_post_timestamp'] = [
      'title' => $this->t('Last post/comment time'),
      'help' => $this->t('Date and time of when the last community post/comment was created.'),
      'field' => [
        'id' => 'last_post_timestamp',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ];

    return $data;
  }

}
