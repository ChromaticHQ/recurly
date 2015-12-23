<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccess.
 */

/**
 * Create a new recurly access check abstract class for shared functionality.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for various operations.
 */
abstract class RecurlyAccess implements AccessInterface {

  /**
   * A fully loaded account object from Recurly if one can be found.
   */
  protected $localAccount = FALSE;

  /**
   * An array of subscription plans.
   */
  protected $subscriptionPlans = [];

  /**
   * The maximum number of subscriptions.
   */
  protected $recurlySubscriptionMax = 0;

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, EntityInterface $entity, $operation) {
    // Store the entity type.
    $this->entity_type = $entity->getEntityType()->getLowercaseLabel();

    // If subscriptions are attached to users, only allow users to view their
    // own subscriptions.
    if ($entity_type == 'user') {
      if (\Drupal::currentUser()->id() != $entity->id()) {
        return AccessResult::forbidden();
      }
    }

    // Set variables used to determine access for various operations.
    $this->localAccount = recurly_account_load(array('entity_type' => $this->entity_type, 'entity_id' => $entity->id()), TRUE);
    $this->subscriptionPlans = \Drupal::config('recurly.settings')->get('recurly_subscription_plans') ?: [];
    $this->recurly_subscription_max = \Drupal::config('recurly.settings')->get('recurly_subscription_max');

  }

  /**
   * Determine if this is a signup path.
   *
   * @param Symfony\Component\Routing\Route $route
   *   A Route object.
   *
   * @return bool
   *   TRUE if the path contains 'signup', else FALSE.
   */
  protected function pathIsSignup(Route $route) {
    if (strpos($route->getPath(), 'signup') !== FALSE) {
      return TRUE;
    }
    return FALSE;
  }

}
