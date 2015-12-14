<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessCheck.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks access for displaying configuration translation page.
 */
class RecurlyAccessCheck implements AccessInterface {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, EntityInterface $entity, $operation) {
    $entity_type = $entity->getEntityType()->getLowercaseLabel();

    // If the operation is anything but subscribe, do not allow access to the page
    // because it does not make logical sense to show invoices/billing/etc. for
    // an object that does not have a subscription at all.
    $local_account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $entity->id()), TRUE);
    $subscription_plans = \Drupal::config('recurly.settings')->get('recurly_subscription_plans') ?: [];

    if ($operation === 'main' && ($local_account || $subscription_plans)) {
      return AccessResult::allowed();
    }
    elseif ($operation === 'select_plan') {
      // This tab is only visible when visited directly or if multiple plans are
      // allowed.
      if (!empty($subscription_plans) && $this->pathIsSignup($route) || \Drupal::config('recurly.settings')->get('recurly_subscription_max') ?: 1 != 1) {
        return AccessResult::allowed();
      }
    }
    elseif ($operation === 'list') {
      // This is a hack to make it so that the list of subscriptions does not show
      // up as a sub-tab when showing the signup page.
      $access = !empty($local_account) && $this->pathIsSignup($route);
      if (\Drupal::config('recurly.settings')->get('recurly_subscription_max') ?: 1 != 1) {
        $access = $access || (!empty($local_account) && recurly_account_has_active_subscriptions($local_account->account_code));
      }
      if ($access) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden();
    }
    // These pages are only accessible if using the single-page mode. This
    // requires loading the latest active account for an entity.
    elseif (\Drupal::config('recurly.settings')->get('recurly_subscription_max') ?: 1 == 1) {
      $active_subscriptions = $local_account ? recurly_account_get_subscriptions($local_account->account_code, 'active') : array();
      $active_subscription = reset($active_subscriptions);
      if ($operation === 'change_plan_latest') {
        if (!empty($local_account) && count($subscription_plans) && !empty($active_subscription)) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      elseif ($operation === 'cancel_latest') {
        if (!empty($local_account) && !empty($active_subscription) && $active_subscription->state == 'active') {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      elseif ($operation === 'reactivate_latest') {
        if (!empty($local_account) && !empty($active_subscription) && $active_subscription->state == 'canceled') {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      // @FIXME: $_POST has been removed.
      // elseif ($operation === 'signup') {
      //   // POST is included here to allow the signup form to finish processing, in
      //   // the event that the push notification comes so fast it finishes before
      //   // Drupal processes the form that contained a Recurly.js element.
      //   return empty($local_account) || empty($active_subscriptions) || !empty($_POST);
      // }
    }
    elseif (in_array($operation, ['change_plan_latest', 'cancel_latest', 'reactivate_latest'])) {
      return AccessResult::forbidden();
    }
    elseif ($operation === 'signup' && \Drupal::config('recurly.settings')->get('recurly_subscription_max') ?: 1 != 1) {
      return AccessResult::allowed();
    }

    if (!empty($local_account)) {
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
