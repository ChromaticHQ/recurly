<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessList.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks if the list operation should be accessible.
 */
class RecurlyAccessList extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, EntityInterface $entity) {
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $subscription_plans = \Drupal::config('recurly.settings')->get('recurly_subscription_plans') ?: [];
    $local_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()], TRUE);
    if ($local_account || $subscription_plans) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
