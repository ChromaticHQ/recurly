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
    $access = parent::access($route, $route_match, $entity, $operation);
    if (get_class($access) == 'AccessResult') {
      return $access;
    }

    // This is a hack to make it so that the list of subscriptions does not
    // show up as a sub-tab when showing the signup page.
    $access = !empty($this->localAccount) && $this->pathIsSignup($route);
    if ($this->recurlySubscriptionMax != 1) {
      $access = $access || (!empty($this->localAccount) && recurly_account_has_active_subscriptions($this->localAccount->account_code));
    }
    if ($access) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();

  }

}
