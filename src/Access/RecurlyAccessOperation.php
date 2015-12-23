<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessOperation.
 */

/**
 * This class holds logic for all operations except "list".
 *
 * The "list" operation has already been separated out into a new class.
 *
 * Eventually each operation in this class will be put into its own class and
 * the routes will be updated to check services that interface with each of
 * these classes.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for displaying a given operation.
 */
class RecurlyAccessOperation extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, EntityInterface $entity, $operation) {
    $access = parent::access($route, $route_match, $entity, $operation);
    if (get_class($access) == 'AccessResult') {
      return $access;
    }

    // If the operation is anything but subscribe, do not allow access to the
    // page because it does not make logical sense to show invoices/billing/etc.
    // for an object that does not have a subscription at all.
    if ($operation == 'main' && ($this->localAccount || $this->subscriptionPlans)) {
      return AccessResult::allowed();
    }
    elseif ($operation == 'select_plan') {
      // This tab is only visible when visited directly or if multiple plans are
      // allowed.
      if (!empty($this->subscriptionPlans) && $this->pathIsSignup($route) || $this->recurlySubscriptionMax != 1) {
        return AccessResult::allowed();
      }
    }
    elseif ($operation == 'list') {
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
    // These pages are only accessible if using the single-page mode. This
    // requires loading the latest active account for an entity.
    elseif ($this->recurlySubscriptionMax == 1) {
      $active_subscriptions = $this->localAccount ? recurly_account_get_subscriptions($this->localAccount->account_code, 'active') : array();
      $active_subscription = reset($active_subscriptions);
      if ($operation === 'change_plan_latest') {
        if (!empty($this->localAccount) && count($this->subscriptionPlans) && !empty($active_subscription)) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      elseif ($operation == 'cancel_latest') {
        if (!empty($this->localAccount) && !empty($active_subscription) && $active_subscription->state == 'active') {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      elseif ($operation == 'reactivate_latest') {
        if (!empty($this->localAccount) && !empty($active_subscription) && $active_subscription->state == 'canceled') {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      // @FIXME: $_POST has been removed.
      // @see https://www.drupal.org/node/2150267
      elseif ($operation === 'signup') {
        // POST is included here to allow the signup form to finish processing,
        // in the event that the push notification comes so fast it finishes
        // before Drupal processes the form that contained a Recurly.js element.
        // return empty($this->localAccount) || empty($active_subscriptions) || !empty($_POST);
        if (isset($this->localAccount) || isset($active_subscriptions)) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
    }
    elseif (in_array($operation, [
      'change_plan_latest',
      'cancel_latest',
      'reactivate_latest',
    ])) {
      return AccessResult::forbidden();
    }
    elseif ($operation == 'signup' && $this->recurlySubscriptionMax != 1) {
      return AccessResult::allowed();
    }

    if (!empty($this->localAccount)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
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
