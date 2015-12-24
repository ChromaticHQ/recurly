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
  public function access(Route $route, RouteMatchInterface $route_match, EntityInterface $entity, $operation) {
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $local_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()], TRUE);
    $recurly_subscription_max = \Drupal::config('recurly.settings')->get('recurly_subscription_max');
    // This is a hack to make it so that the list of subscriptions does not
    // show up as a sub-tab when showing the signup page.
    $access = !empty($local_account) && $this->pathIsSignup($route);
    if ($recurly_subscription_max != 1) {
      $access = $access || (!empty($local_account) && recurly_account_has_active_subscriptions($local_account->account_code));
    }
    return $access ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
