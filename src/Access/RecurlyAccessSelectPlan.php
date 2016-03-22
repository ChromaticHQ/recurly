<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessSelectPlan.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;

/**
 * Checks if the select operation should be accessible.
 */
class RecurlyAccessSelectPlan extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    $route = $this->routeMatch->getCurrentRouteMatch()->getRouteObject();
    if (!empty($this->subscriptionPlans) && $this->pathIsSignup($route) || $this->recurlySubscriptionMax != 1) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
