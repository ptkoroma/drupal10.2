<?php

namespace Drupal\opigno_social_community\Plugin\views\filter;

use Drupal\views\Plugin\views\query\Sql;

/**
 * Defines the view filter handler to display the user's community invitations.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_invited_to_communities")
 *
 * @package Drupal\opigno_social_community\Plugin\views\filter
 */
class InvitedToCommunities extends JoinedCommunities {

  /**
   * {@inheritdoc}
   */
  public function operatorOptions() {
    return [
      'IN' => $this->t('Invited to'),
      'NOT IN' => $this->t('Not invited to'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->query instanceof Sql) {
      return;
    }

    $communities = $this->communityManager->getInvitedToCommunities();
    if ($communities) {
      // Prepare the query.
      $this->ensureMyTable();
      $this->query->addWhere($this->options['group'], "{$this->tableAlias}.{$this->realField}", $communities, $this->operator);
    }

  }

}
