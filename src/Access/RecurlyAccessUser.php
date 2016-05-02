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
    $entity = $this->routeMatch->getCurrentRouteMatch()->getParameter($this->entityType);
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    // If subscriptions are attached to users and the user does not have the
    // 'administer recurly' permission, only allow them to view their own
    // subscriptions.
    if ($entity_type == 'user') {
      if (\Drupal::currentUser()->id() != $entity->id() && !\Drupal::currentUser()->hasPermission('administer recurly')) {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::allowed();
  }

}
