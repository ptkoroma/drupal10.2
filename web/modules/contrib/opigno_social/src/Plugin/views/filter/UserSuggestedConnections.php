<?php

namespace Drupal\opigno_social\Plugin\views\filter;

use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Filter view handler for the user's suggested connections.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_suggested_connections")
 */
class UserSuggestedConnections extends UserNetworkConnections {

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->query instanceof Sql) {
      return;
    }

    // Get the list of connections that were created by the current user and
    // accepted by invitee.
    $sent = $this->connectionManager->getInvitedByUser(TRUE);
    // Get the current user invitations.
    $received = $this->connectionManager->getUserInvitees();
    $network = array_merge($sent, $received);

    // Possible connections should be limited by the Social settings config.
    $users = opigno_messaging_get_all_recipients(FALSE);
    $recipients = [];
    foreach ($users as $user) {
      if ($user instanceof UserInterface
        && $user->isActive()
        && (int) $user->id() !== $this->connectionManager->currentUid
      ) {
        $recipients[] = $user->id();
      }
    }
    $recipients = $recipients ?: [0];

    // Prepare query.
    $this->ensureMyTable();
    if ($network) {
      $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $network, 'NOT IN');
    }
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $recipients, 'IN');
  }

}
