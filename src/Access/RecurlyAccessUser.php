<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessList.
 */

/**
 * Checks if the list operation should be accessible.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks if the list operation should be accessible.
 */
class RecurlyAccessUser extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, EntityInterface $entity, $operation) {
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    // If subscriptions are attached to users, only allow users to view their
    // own subscriptions.
    if ($entity_type == 'user') {
      if (\Drupal::currentUser()->id() == $entity->id()) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

}
