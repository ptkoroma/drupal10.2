<?php

namespace Drupal\opigno_social;

use Drupal\views\EntityViewsData;

/**
 * User invitations views data handler.
 *
 * @package Drupal\opigno_social
 */
class UserInvitationViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // View filter for the user network.
    $data['users_field_data']['opigno_network_connections'] = [
      'help' => $this->t("Get the list of contacts from the user network."),
      'real field' => 'uid',
      'filter' => [
        'title' => $this->t("Opigno User network contacts"),
        'id' => 'opigno_network_connections',
      ],
    ];

    // View filter for the suggested contacts.
    $data['users_field_data']['opigno_suggested_connections'] = [
      'help' => $this->t("Get the list of the suggested contacts."),
      'real field' => 'uid',
      'filter' => [
        'title' => $this->t("Opigno Suggested contacts"),
        'id' => 'opigno_suggested_connections',
      ],
    ];

    return $data;
  }

}
