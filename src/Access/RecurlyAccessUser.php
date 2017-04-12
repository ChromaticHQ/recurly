<?php

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;

/**
 * Checks if the list operation should be accessible.
 */
class RecurlyAccessUser extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    // If subscriptions are attached to users and the user does not have the
    // 'administer recurly' permission, only allow them to view their own
    // subscriptions.
    if ($this->entityType == 'user') {
      $entity_id = $this->routeMatch->getCurrentRouteMatch()->getRawParameter($this->entityType);
      if ($this->currentUser->id() != $entity_id && !$this->currentUser->hasPermission('administer recurly')) {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::allowed();
  }

}
