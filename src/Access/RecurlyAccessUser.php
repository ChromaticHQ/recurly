<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessList.
 */

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
    $entity = $this->routeMatch->getCurrentRouteMatch()->getParameter($this->entityType);
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    // If subscriptions are attached to users, only allow users to view their
    // own subscriptions.
    if ($entity_type == 'user') {
      if (\Drupal::currentUser()->id() != $entity->id()) {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::allowed();
  }

}
