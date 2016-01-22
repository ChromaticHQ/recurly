<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessList.
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
  public function access(Route $route, RouteMatchInterface $route_match) {
    $entity_type_id = \Drupal::config('recurly.settings')->get('recurly_entity_type') ?: 'user';
    $entity = $route_match->getParameter($entity_type_id);
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
